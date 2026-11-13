<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Mitra;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class MitraSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $mitraList = [
            [
                'username'   => 'mitra_tokopedia',
                'email'      => 'internship@tokopedia.com',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT GoTo Gojek Tokopedia',
                'narahubung' => 'Kevin Sanjaya (Engineering Lead)',
            ],
            [
                'username'   => 'mitra_telkom',
                'email'      => 'internship@telkom.co.id',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT Telkom Indonesia',
                'narahubung' => 'Andi Pratama (Talent Acquisition)',
            ],
            [
                'username'   => 'mitra_traveloka',
                'email'      => 'hr@traveloka.com',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT Traveloka Indonesia',
                'narahubung' => 'Dewi Lestari (HR Manager)',
            ],
            [
                'username'   => 'mitra_mandiri',
                'email'      => 'magang@bankmandiri.co.id',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT Bank Mandiri (Persero) Tbk',
                'narahubung' => 'Rudi Hartono (VP IT Operation)',
            ],
            [
                'username'   => 'mitra_bukalapak',
                'email'      => 'career@bukalapak.com',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT Bukalapak.com Tbk',
                'narahubung' => 'Maya Sari (Tech Recruiter)',
            ],
            [
                'username'   => 'mitra_shopee',
                'email'      => 'intern@shopee.co.id',
                'password'   => 'mitrapassword123',
                'nama_mitra' => 'PT Shopee International Indonesia',
                'narahubung' => 'Bambang Pamungkas (Campus Relations)',
            ],
        ];

        foreach ($mitraList as $data) {
            $user = User::updateOrCreate(
                ['username' => $data['username']],
                [
                    'email'     => $data['email'],
                    'password'  => Hash::make($data['password']),
                    'role'      => 'mitra',
                    'is_active' => true,
                ]
            );

            Mitra::updateOrCreate(
                ['user_id' => $user->user_id],
                [
                    'nama_mitra' => $data['nama_mitra'],
                    'email'      => $data['email'],
                    'narahubung' => $data['narahubung'],
                ]
            );
        }
    }
}
