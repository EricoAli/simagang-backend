<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SuperAdminSeeder — provisions the default Super Admin account.
 *
 * Security & Environment Rules:
 * - Only executed in 'local' and 'testing' environments.
 * - Idempotent: checks if a user with username 'superadmin' already exists.
 * - If the account already exists, it DOES NOT overwrite the password.
 * - Does NOT create a data_admin record or assign any prodi_id.
 */
class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Lingkungan aman: tolak eksekusi di luar local dan testing.
        if (!app()->environment(['local', 'testing'])) {
            $this->command?->warn('SuperAdminSeeder hanya dapat dijalankan di environment local atau testing.');
            return;
        }

        $username = 'superadmin';
        $email = 'superadmin@simagang.dev';
        $defaultPassword = 'superadmin123';

        // 2. Cek apakah user sudah ada (idempotent)
        $existingUser = DB::table('users')->where('username', $username)->first();

        if ($existingUser) {
            $this->command?->info("User '{$username}' sudah ada (user_id: {$existingUser->user_id}). Password yang ada tidak ditimpa.");

            // Pastikan role tetap super_admin jika sebelumnya belum diset
            if ($existingUser->role !== 'super_admin') {
                DB::table('users')
                    ->where('user_id', $existingUser->user_id)
                    ->update([
                        'role' => 'super_admin',
                        'updated_at' => now(),
                    ]);
                $this->command?->info("Role user '{$username}' diperbarui menjadi 'super_admin'.");
            }
            return;
        }

        // 3. Buat akun Super Admin baru dengan password terenkripsi Hash::make
        DB::table('users')->insert([
            'username'   => $username,
            'email'      => $email,
            'password'   => Hash::make($defaultPassword),
            'role'       => 'super_admin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command?->info("Akun Super Admin berhasil dibuat (username: {$username}).");
    }
}
