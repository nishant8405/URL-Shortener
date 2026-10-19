<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (Schema::getForeignKeys('users') as $foreignKey) {
            if ($foreignKey['columns'] === ['role_id']) {
                return;
            }
        }

        if (DB::table('users')->whereNotNull('role_id')
            ->whereNotIn('role_id', DB::table('roles')->select('id'))->exists()) {
            throw new RuntimeException('Users contain unknown role IDs. Repair these assignments before migrating.');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};
