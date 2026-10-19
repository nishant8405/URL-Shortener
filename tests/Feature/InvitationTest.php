<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role_id' => Role::where('name', $role)->sole()->id,
            'company_id' => Company::create(['name' => 'Company '.$role])->id]);
    }

    private function invite(User $user, string $role = 'Member'): string
    {
        $payload = ['email' => 'invited@example.com', 'role' => $role];
        if ($user->role->name === 'SuperAdmin') {
            $payload['company_name'] = 'New travel company';
        }
        $response = $this->actingAs($user)->post('/invitations', $payload)->assertRedirect('/invitations');
        $link = session('invitation_url');
        $this->assertNotEmpty($link);

        return parse_url($link, PHP_URL_PATH);
    }

    public function test_superadmin_invites_admin_in_new_company_and_acceptance_is_single_use(): void
    {
        $super = $this->user('SuperAdmin');
        $path = $this->invite($super, 'Admin');
        $invitation = Invitation::sole();
        $this->assertNotSame($super->company_id, $invitation->company_id);
        $this->assertNotSame(basename($path), $invitation->token_hash);
        $this->post('/logout');
        $this->get($path)->assertOk()->assertSee('invited@example.com');
        $this->post($path, ['name' => 'Invitee', 'password' => 'password123', 'password_confirmation' => 'password123',
            'email' => 'attacker@example.com', 'role' => 'SuperAdmin', 'company_id' => $super->company_id])->assertRedirect('/login');
        $user = User::where('email', 'invited@example.com')->sole();
        $this->assertSame('Admin', $user->role->name);
        $this->assertSame($invitation->company_id, $user->company_id);
        $this->assertTrue(Hash::check('password123', $user->password));
        $this->get($path)->assertStatus(410);
        $this->post($path, ['name' => 'Replay', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertStatus(410);
        $this->post('/login', ['email' => $user->email, 'password' => 'password123'])->assertRedirect('/short-urls');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_invite_admin_and_member_into_own_company(): void
    {
        $admin = $this->user('Admin');
        foreach (['Admin', 'Member'] as $role) {
            $this->actingAs($admin)->post('/invitations', ['email' => strtolower($role).'@example.com', 'role' => $role])->assertRedirect('/invitations');
            $this->assertDatabaseHas('invitations', ['email' => strtolower($role).'@example.com', 'role' => $role, 'company_id' => $admin->company_id]);
        }
        $this->assertDatabaseCount('companies', 1);
        $this->get('/invitations')->assertOk()->assertSee('Create invitation');
    }

    public function test_admin_cannot_choose_another_company_or_escalate_role(): void
    {
        $admin = $this->user('Admin');
        $other = Company::create(['name' => 'Other']);
        foreach ([['company_id' => $other->id], ['company_name' => 'Injected'], ['role' => 'SuperAdmin'], ['role' => 'Unsupported']] as $extra) {
            $this->actingAs($admin)->post('/invitations', array_merge(['email' => 'new@example.com', 'role' => 'Member'], $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('invitations', 0);
        $this->assertDatabaseCount('companies', 2);
    }

    public function test_superadmin_requires_new_company_and_admin_role(): void
    {
        $super = $this->user('SuperAdmin');
        foreach ([['role' => 'Member', 'company_name' => 'New'], ['role' => 'Admin'], ['role' => 'Admin', 'company_name' => $super->company->name]] as $extra) {
            $this->actingAs($super)->post('/invitations', array_merge(['email' => 'new@example.com'], $extra))->assertSessionHasErrors();
        }
        $this->assertDatabaseCount('invitations', 0);
        $this->assertDatabaseCount('companies', 1);
    }

    public function test_guests_and_members_cannot_invite(): void
    {
        $this->get('/invitations')->assertRedirect('/login');
        $this->post('/invitations')->assertRedirect('/login');
        foreach (['Member'] as $role) {
            $this->actingAs($this->user($role))->get('/invitations')->assertForbidden();
            $this->post('/invitations', ['email' => 'new@example.com', 'role' => 'Admin'])->assertForbidden();
        }
        $this->assertDatabaseCount('invitations', 0);
    }

    public function test_expired_invalid_and_existing_email_invitations_are_rejected(): void
    {
        $admin = $this->user('Admin');
        $path = $this->invite($admin);
        Invitation::sole()->update(['expires_at' => now()->subSecond()]);
        $this->get($path)->assertStatus(410);
        $this->post($path, ['name' => 'Test', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertStatus(410);
        $this->get('/invitations/invalid')->assertNotFound();
        $this->post('/invitations', ['email' => $admin->email, 'role' => 'Member'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_password_confirmation_is_required_and_invitation_remains_unused(): void
    {
        $path = $this->invite($this->user('Admin'));
        $this->post($path, ['name' => 'Invitee', 'password' => 'password123', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertNull(Invitation::sole()->accepted_at);
        $this->assertDatabaseCount('users', 1);
    }
}
