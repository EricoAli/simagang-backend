<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Expand role enum to include super_admin without breaking existing roles
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'mahasiswa', 'mitra', 'dosbing') NOT NULL");

        // 2. Add is_active column with default true
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Handle rollback safety: cannot safely revert if users with role super_admin exist
        if (DB::table('users')->where('role', 'super_admin')->exists()) {
            throw new \RuntimeException('Cannot rollback migration: users with role super_admin still exist in the database. Please remove or reassign those accounts before rolling back.');
        }

        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'mahasiswa', 'mitra', 'dosbing') NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
