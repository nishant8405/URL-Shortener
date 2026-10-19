<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $legacyIds = DB::table('roles')->whereIn('name', ['Sales', 'Manager'])->pluck('id');
            if ($legacyIds->isEmpty()) {
                return;
            }
            DB::table('roles')->insertOrIgnore([
                'name' => 'Member', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $memberId = DB::table('roles')->where('name', 'Member')->value('id');
            DB::table('users')->whereIn('role_id', $legacyIds)->update([
                'role_id' => $memberId, 'updated_at' => now(),
            ]);
            DB::table('roles')->whereIn('id', $legacyIds)->delete();
        });
    }

    public function down(): void
    {
        // Data normalization is intentionally retained: the old per-user role
        // cannot be reconstructed reliably, and obsolete roles must not return.
    }
};
