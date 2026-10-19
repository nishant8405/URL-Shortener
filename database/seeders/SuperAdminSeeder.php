<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. SuperAdmin role ki ID nikalo
            $role = DB::selectOne(
                'SELECT id FROM roles WHERE name = ?',
                ['SuperAdmin']
            );

            if (! $role) {
                throw new RuntimeException(
                    'SuperAdmin role nahi mila. Pehle RoleSeeder run karo.'
                );
            }

            // 2. Company check karo; nahi hai to create karo
            $company = DB::selectOne(
                'SELECT id FROM companies WHERE name = ? LIMIT 1',
                ['Sembark Tech']
            );

            if (! $company) {
                DB::insert(
                    'INSERT INTO companies (name, created_at, updated_at)
                     VALUES (?, ?, ?)',
                    ['Sembark Tech', now(), now()]
                );

                $company = DB::selectOne(
                    'SELECT id FROM companies WHERE name = ? LIMIT 1',
                    ['Sembark Tech']
                );
            }

            // 3. SuperAdmin user ka email
            $email = 'superadmin@example.com';

            $user = DB::selectOne(
                'SELECT id FROM users WHERE email = ?',
                [$email]
            );

            if (! $user) {
                // 4. Raw SQL se user create karo
                DB::insert(
                    'INSERT INTO users
                    (name, email, password, company_id, role_id,
                     created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        'Super Admin',
                        $email,
                        Hash::make('ChangeMe@12345'),
                        $company->id,
                        $role->id,
                        now(),
                        now(),
                    ]
                );
            } else {
                // Existing user ko correct company aur role se link karo
                DB::update(
                    'UPDATE users SET company_id = ?, role_id = ?,
                     updated_at = ? WHERE email = ?',
                    [$company->id, $role->id, now(), $email]
                );
            }
        });
    }
}
