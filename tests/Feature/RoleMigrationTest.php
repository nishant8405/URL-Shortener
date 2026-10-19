<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\ShortUrl;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_roles_are_converted_without_losing_users_or_urls(): void
    {
        $this->seed(RoleSeeder::class);
        $company = Company::create(['name' => 'Legacy company']);
        $users = [];
        foreach (['Sales', 'Manager'] as $name) {
            $role = Role::create(['name' => $name]);
            $user = User::factory()->create(['company_id' => $company->id, 'role_id' => $role->id]);
            $users[] = $user;
            ShortUrl::create(['original_url' => 'https://example.com', 'short_code' => $name,
                'user_id' => $user->id, 'company_id' => $company->id]);
        }
        $migration = require database_path('migrations/2026_10_19_010000_remove_obsolete_roles.php');
        $migration->up();
        $migration->up();
        $this->seed(RoleSeeder::class);
        $this->assertEqualsCanonicalizing(['SuperAdmin', 'Admin', 'Member'], Role::pluck('name')->all());
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('short_urls', 2);
        foreach ($users as $user) {
            $this->assertSame('Member', $user->fresh()->role->name);
            $this->assertSame($company->id, $user->fresh()->company_id);
            $this->assertDatabaseHas('short_urls', ['user_id' => $user->id]);
        }
    }
}
