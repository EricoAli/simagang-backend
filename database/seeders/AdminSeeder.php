<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * AdminSeeder — provisions default Admin accounts for each Prodi.
 *
 * Idempotent: uses updateOrInsert on username.
 *
 * Password defaults: {kode_prodi_lowercase}admin123
 * Example: TRPL admin → username: admin_trpl, password: trpladmin123
 *
 * IMPORTANT: These are development credentials only.
 * Change all passwords before any staging/production deployment.
 *
 * Prodi assignment is set HERE in the seeder (server side).
 * Admin cannot self-assign a prodi through any public endpoint.
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'kode_prodi' => 'TRPL',
                'username'   => 'admin_trpl',
                'email'      => 'admin.trpl@simagang.dev',
                'password'   => 'trpladmin123',
                'nama'       => 'Admin TRPL',
            ],
            [
                'kode_prodi' => 'TRIK',
                'username'   => 'admin_trik',
                'email'      => 'admin.trik@simagang.dev',
                'password'   => 'trikadmin123',
                'nama'       => 'Admin TRIK',
            ],
            [
                'kode_prodi' => 'TRI',
                'username'   => 'admin_tri',
                'email'      => 'admin.tri@simagang.dev',
                'password'   => 'triadmin123',
                'nama'       => 'Admin TRI',
            ],
            [
                'kode_prodi' => 'TRE',
                'username'   => 'admin_tre',
                'email'      => 'admin.tre@simagang.dev',
                'password'   => 'treadmin123',
                'nama'       => 'Admin TRE',
            ],
        ];

        foreach ($admins as $adminData) {
            $prodi = DB::table('prodi')->where('kode_prodi', $adminData['kode_prodi'])->first();

            if (!$prodi) {
                $this->command->warn("Prodi {$adminData['kode_prodi']} not found. Run ProdiSeeder first.");
                continue;
            }

            // Idempotent user
            $userId = DB::table('users')
                ->where('username', $adminData['username'])
                ->value('user_id');

            if (!$userId) {
                $userId = DB::table('users')->insertGetId([
                    'username'   => $adminData['username'],
                    'email'      => $adminData['email'],
                    'password'   => Hash::make($adminData['password']),
                    'role'       => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Idempotent data_admin
            DB::table('data_admin')->updateOrInsert(
                ['user_id' => $userId],
                [
                    'prodi_id' => $prodi->prodi_id,
                    'nama'     => $adminData['nama'],
                ]
            );
        }
    }
}
