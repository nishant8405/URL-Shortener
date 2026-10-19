<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\ShortUrl;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ShortUrlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role, ?Company $company = null): User
    {
        return User::factory()->create([
            'role_id' => Role::where('name', $role)->sole()->id,
            'company_id' => ($company ?? Company::create(['name' => 'Test company']))->id,
        ]);
    }

    private function url(User $user): ShortUrl
    {
        return ShortUrl::create([
            'original_url' => 'https://example.com/'.Str::uuid(),
            'short_code' => Str::random(8),
            'user_id' => $user->id,
            'company_id' => $user->company_id,
        ]);
    }

    public static function forbiddenRoles(): array
    {
        return [['SuperAdmin']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_forbidden_roles_cannot_create(string $role): void
    {
        $this->actingAs($this->user($role))->post('/short-urls', ['original_url' => 'https://example.com'])->assertForbidden();
        $this->assertDatabaseCount('short_urls', 0);
        $this->get('/short-urls')->assertStatus($role === 'SuperAdmin' ? 200 : 403)->assertDontSee('Create Short URL');
    }

    public static function creators(): array
    {
        return [['Admin'], ['Member']];
    }

    #[DataProvider('creators')]
    public function test_creation_uses_authenticated_ownership(string $role): void
    {
        $user = $this->user($role);
        $other = $this->user('Admin');
        $this->actingAs($user)->post('/short-urls', [
            'original_url' => 'https://example.com/path?q=1',
            'user_id' => $other->id, 'company_id' => $other->company_id,
        ])->assertRedirect('/short-urls');
        $this->assertDatabaseHas('short_urls', [
            'user_id' => $user->id, 'company_id' => $user->company_id,
            'original_url' => 'https://example.com/path?q=1',
        ]);
        $this->get('/short-urls')->assertOk()->assertSee('Create Short URL');
    }

    public function test_admin_only_lists_own_company_urls(): void
    {
        $admin = $this->user('Admin');
        $own = $this->url($this->user('Admin', $admin->company));
        $other = $this->url($this->user('Admin'));
        $this->actingAs($admin)->get('/short-urls')->assertOk()
            ->assertSee($own->original_url)->assertDontSee($other->original_url);
    }

    public function test_member_only_lists_own_urls(): void
    {
        $member = $this->user('Member');
        $own = $this->url($member);
        $colleague = $this->url($this->user('Admin', $member->company));
        $other = $this->url($this->user('Admin'));
        $this->actingAs($member)->get('/short-urls')->assertOk()
            ->assertDontSee($colleague->original_url)->assertSee($own->original_url)->assertDontSee($other->original_url);
    }

    public function test_superadmin_lists_all_company_urls(): void
    {
        $url = $this->url($this->user('Admin'));
        $second = $this->url($this->user('Member'));
        $this->actingAs($this->user('SuperAdmin'))->get('/short-urls')->assertOk()
            ->assertSee($url->original_url)->assertSee($second->original_url);
    }

    #[DataProvider('creators')]
    public function test_creators_list_only_their_company(string $role): void
    {
        $user = $this->user($role);
        $own = $this->url($user);
        $other = $this->url($this->user('Admin'));
        $this->actingAs($user)->get('/short-urls')->assertSee($own->original_url)->assertDontSee($other->original_url);
    }

    public function test_short_links_are_public_and_redirect_exactly(): void
    {
        $url = $this->url($this->user('Admin'));
        $this->get('/s/'.$url->short_code)->assertRedirect($url->original_url);
        $this->assertGuest();
        $this->get('/s/unknown')->assertNotFound();
    }

    public function test_guest_cannot_list_or_create(): void
    {
        $this->get('/short-urls')->assertRedirect('/login');
        $this->post('/short-urls', ['original_url' => 'https://example.com'])->assertRedirect('/login');
        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_login_logout_and_dashboard(): void
    {
        $user = $this->user('Admin');
        $this->get('/login')->assertOk();
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/short-urls');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect('/short-urls');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
        $this->get('/short-urls')->assertRedirect('/login');
    }

    public function test_login_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertRedirect();
        }
        $this->post('/login', ['email' => 'missing@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public static function invalidUrls(): array
    {
        return [[''], ['not a URL'], ['javascript:alert(1)'], ['ftp://example.com/file'], ['https://example.com/'.str_repeat('a', 2048)]];
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_destinations_are_rejected(string $destination): void
    {
        $this->actingAs($this->user('Admin'))->post('/short-urls', ['original_url' => $destination])->assertSessionHasErrors('original_url');
        $this->assertDatabaseCount('short_urls', 0);
    }

    public function test_missing_or_unknown_membership_is_denied(): void
    {
        $user = $this->user('Admin');
        $url = $this->url($user);
        $salesRoleId = $user->role_id;
        foreach ([['role_id' => null], ['role_id' => Role::create(['name' => 'Unknown'])->id], ['company_id' => null]] as $attributes) {
            $user->forceFill(array_merge(['role_id' => $salesRoleId], $attributes))->save();
            $user->refresh();
            $this->actingAs($user)->get('/short-urls')->assertForbidden();
            $this->post('/short-urls', ['original_url' => 'https://example.com'])->assertForbidden();
            $this->get('/s/'.$url->short_code)->assertRedirect($url->original_url);
        }
        $this->assertDatabaseCount('short_urls', 1);
    }

    public function test_collision_is_retried(): void
    {
        $user = $this->user('Admin');
        $existing = $this->url($user);
        $codes = [$existing->short_code, 'newcode1'];
        Str::createRandomStringsUsing(function ($length) use (&$codes) {
            return $length === 8 ? array_shift($codes) : str_repeat('x', $length);
        });
        try {
            $this->actingAs($user)->post('/short-urls', ['original_url' => 'https://example.com/new'])->assertRedirect('/short-urls');
            $this->assertDatabaseHas('short_urls', ['short_code' => 'newcode1']);
        } finally {
            Str::createRandomStringsNormally();
        }
    }

    public function test_listing_is_paginated(): void
    {
        $user = $this->user('Admin');
        for ($i = 0; $i < 16; $i++) {
            $this->url($user);
        }
        $this->actingAs($user)->get('/short-urls')->assertViewHas('urls', fn ($urls) => $urls->count() === 15 && $urls->total() === 16);
        $this->get('/short-urls?page=2')->assertViewHas('urls', fn ($urls) => $urls->count() === 1);
    }

    public function test_seeders_are_repeatable_and_schema_has_role_constraint(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('roles', 3);
        $this->assertDatabaseCount('companies', 2);
        $this->assertDatabaseCount('users', 5);
        $this->assertSame(3, Company::where('name', 'Sembark Tech')->sole()->users()->count());
        $this->assertSame(2, Company::where('name', 'Example Travel')->sole()->users()->count());
        $admin = User::where('email', 'superadmin@example.com')->sole();
        $this->assertSame('SuperAdmin', $admin->role->name);
        $this->assertTrue(Hash::check('ChangeMe@12345', $admin->password));
        $this->assertTrue(collect(Schema::getForeignKeys('users'))->contains(fn ($key) => $key['columns'] === ['role_id']));
        $admin->role->delete();
        $this->assertNull($admin->fresh()->role_id);
    }
}
