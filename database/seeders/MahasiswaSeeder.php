<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Illuminate\Support\Facades\Hash;

class MahasiswaSeeder extends Seeder
{
    public function run(): void
    {
        $prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
        $prodiTRI  = Prodi::where('kode_prodi', 'TRI')->first();
        $prodiTRE  = Prodi::where('kode_prodi', 'TRE')->first();

        // 1. Akun utama untuk demo tiap prodi (Angkatan 2024 sebagai angkatan default/aktif di Frontend)
        $keyStudents = [
            [
                'username' => 'mhs_trpl',
                'password' => 'password123',
                'email'    => 'budi.pratama@mail.ugm.ac.id',
                'nim'      => '24/491234/SV/20001',
                'nama'     => 'Budi Pratama',
                'angkatan' => 2024,
                'prodi_id' => $prodiTRPL ? $prodiTRPL->prodi_id : 1,
            ],
            [
                'username' => 'mhs_trik',
                'password' => 'password123',
                'email'    => 'siti.nurhaliza@mail.ugm.ac.id',
                'nim'      => '24/492345/SV/20002',
                'nama'     => 'Siti Nurhaliza',
                'angkatan' => 2024,
                'prodi_id' => $prodiTRIK ? $prodiTRIK->prodi_id : 2,
            ],
            [
                'username' => 'mhs_tri',
                'password' => 'password123',
                'email'    => 'ahmad.zaki@mail.ugm.ac.id',
                'nim'      => '24/493456/SV/20003',
                'nama'     => 'Ahmad Zaki',
                'angkatan' => 2024,
                'prodi_id' => $prodiTRI ? $prodiTRI->prodi_id : 3,
            ],
            [
                'username' => 'mhs_tre',
                'password' => 'password123',
                'email'    => 'rian.kurniawan@mail.ugm.ac.id',
                'nim'      => '24/494567/SV/20004',
                'nama'     => 'Rian Kurniawan',
                'angkatan' => 2024,
                'prodi_id' => $prodiTRE ? $prodiTRE->prodi_id : 4,
            ],
        ];

        foreach ($keyStudents as $item) {
            $user = User::updateOrCreate(
                ['username' => $item['username']],
                [
                    'email'     => $item['email'],
                    'password'  => Hash::make($item['password']),
                    'role'      => 'mahasiswa',
                    'is_active' => true,
                ]
            );

            Mahasiswa::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'prodi_id' => $item['prodi_id'],
                    'nim'      => $item['nim'],
                    'nama'     => $item['nama'],
                    'angkatan' => $item['angkatan'],
                ]
            );
        }

        // 2. Mahasiswa tambahan TRPL & TRIK (Angkatan 2024, 2023, 2022) dengan NIM unik
        $additionalStudents = [
            // TRPL - 2024
            ['nim' => '24/491235/SV/20005', 'nama' => 'Aditya Wijaya', 'angkatan' => 2024, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],
            ['nim' => '24/491236/SV/20006', 'nama' => 'Cantika Putri', 'angkatan' => 2024, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],
            // TRPL - 2023
            ['nim' => '23/501203/SV/21003', 'nama' => 'Dimas Bagus', 'angkatan' => 2023, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],
            ['nim' => '23/501204/SV/21004', 'nama' => 'Eka Safitri', 'angkatan' => 2023, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],
            // TRPL - 2022
            ['nim' => '22/491237/SV/20007', 'nama' => 'Fajar Ramadhan', 'angkatan' => 2022, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],
            ['nim' => '22/491238/SV/20008', 'nama' => 'Gita Gutawa', 'angkatan' => 2022, 'prodi_id' => $prodiTRPL->prodi_id ?? 1],

            // TRIK - 2024
            ['nim' => '24/492346/SV/20009', 'nama' => 'Hendra Setiawan', 'angkatan' => 2024, 'prodi_id' => $prodiTRIK->prodi_id ?? 2],
            // TRIK - 2023
            ['nim' => '23/502302/SV/21005', 'nama' => 'Indah Permata', 'angkatan' => 2023, 'prodi_id' => $prodiTRIK->prodi_id ?? 2],
            // TRIK - 2022
            ['nim' => '22/492347/SV/20010', 'nama' => 'Joko Susilo', 'angkatan' => 2022, 'prodi_id' => $prodiTRIK->prodi_id ?? 2],
        ];

        foreach ($additionalStudents as $idx => $mhs) {
            $username = 'mhs' . ($idx + 1);
            $user = User::updateOrCreate(
                ['username' => $username],
                [
                    'email'     => "{$username}@mail.ugm.ac.id",
                    'password'  => Hash::make('password123'),
                    'role'      => 'mahasiswa',
                    'is_active' => true,
                ]
            );

            Mahasiswa::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'prodi_id' => $mhs['prodi_id'],
                    'nim'      => $mhs['nim'],
                    'nama'     => $mhs['nama'],
                    'angkatan' => $mhs['angkatan'],
                ]
            );
        }
    }
}
