<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demo accounts are only available in local/testing environments.');
        }

        $this->call(DatabaseSeeder::class);
        foreach (['Sembark Tech' => '', 'Example Travel' => 'travel.'] as $companyName => $emailPrefix) {
            $company = Company::firstOrCreate(['name' => $companyName]);
            foreach (['Admin', 'Member'] as $roleName) {
                $email = $emailPrefix.strtolower($roleName).'@example.com';
                if (User::where('email', $email)->exists()) {
                    continue;
                }
                $user = new User;
                $user->name = $roleName.' Demo';
                $user->email = $email;
                $user->password = 'ChangeMe@12345';
                $user->role_id = Role::where('name', $roleName)->sole()->id;
                $user->company_id = $company->id;
                $user->save();
            }
        }
    }
}
