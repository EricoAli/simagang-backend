<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SuperAdminSeeder — idempotent provisioning for Super Admin.
 *
 * Rules:
 * - Does NOT overwrite password if account already exists.
 * - Does NOT require data_admin or prodi_id relation.
 * - Can be configured via environment variables (SUPER_ADMIN_USERNAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD).
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = env('SUPER_ADMIN_USERNAME', 'superadmin');
        $email = env('SUPER_ADMIN_EMAIL', 'superadmin@simagang.dev');
        $password = env('SUPER_ADMIN_PASSWORD');

        $existing = DB::table('users')->where('username', $username)->first();

        if ($existing) {
            // Ensure role and active status are intact, but NEVER overwrite password
            DB::table('users')->where('user_id', $existing->user_id)->update([
                'role' => 'super_admin',
                'is_active' => true,
                'updated_at' => now(),
            ]);
            return;
        }

        if (empty($password)) {
            throw new \RuntimeException('Variabel lingkungan SUPER_ADMIN_PASSWORD wajib dikonfigurasi secara eksplisit untuk membuat akun Super Admin baru via seeder.');
        }

        DB::table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'password'   => Hash::make($password),
            'role'       => 'super_admin',
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
