<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Prodi;
use App\Models\Mahasiswa;
use App\Models\Magang;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
$prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->firstOrFail();

// Mahasiswa TRPL
$mhsTRPL = Mahasiswa::where('prodi_id', $prodiTRPL->prodi_id)->first();
if (!$mhsTRPL) {
    $userMhs = User::create([
        'username' => 'mhs_trpl_fixture',
        'email' => 'mhs.trpl@fixture.test',
        'password' => Hash::make('password123'),
        'role' => 'mahasiswa',
        'is_active' => true,
    ]);
    $mhsTRPL = Mahasiswa::create([
        'user_id' => $userMhs->user_id,
        'prodi_id' => $prodiTRPL->prodi_id,
        'nim' => '24/111001/SV/11001',
        'nama' => 'Mahasiswa Fixture TRPL',
        'angkatan' => 2024,
    ]);
}

// Mahasiswa TRIK
$mhsTRIK = Mahasiswa::where('prodi_id', $prodiTRIK->prodi_id)->first();
if (!$mhsTRIK) {
    $userMhs2 = User::create([
        'username' => 'mhs_trik_fixture',
        'email' => 'mhs.trik@fixture.test',
        'password' => Hash::make('password123'),
        'role' => 'mahasiswa',
        'is_active' => true,
    ]);
    $mhsTRIK = Mahasiswa::create([
        'user_id' => $userMhs2->user_id,
        'prodi_id' => $prodiTRIK->prodi_id,
        'nim' => '24/222001/TK/22001',
        'nama' => 'Mahasiswa Fixture TRIK',
        'angkatan' => 2024,
    ]);
}

// Magang TRPL
$magangTRPL = Magang::where('mahasiswa_id', $mhsTRPL->mahasiswa_id)->first();
if (!$magangTRPL) {
    $magangTRPL = Magang::create([
        'mahasiswa_id' => $mhsTRPL->mahasiswa_id,
        'semester_magang' => 6,
        'role_magang' => 'Backend Engineer Intern',
        'jobdesk' => 'Mengembangkan API SIMAGANG',
        'periode_bulan' => 6,
        'status_magang' => 'berlangsung',
    ]);
}

// Magang TRIK
$magangTRIK = Magang::where('mahasiswa_id', $mhsTRIK->mahasiswa_id)->first();
if (!$magangTRIK) {
    $magangTRIK = Magang::create([
        'mahasiswa_id' => $mhsTRIK->mahasiswa_id,
        'semester_magang' => 6,
        'role_magang' => 'Embedded Systems Intern',
        'jobdesk' => 'Mengembangkan Firmware IoT',
        'periode_bulan' => 6,
        'status_magang' => 'berlangsung',
    ]);
}

echo json_encode([
    'mhs_trpl_id' => $mhsTRPL->mahasiswa_id,
    'mhs_trik_id' => $mhsTRIK->mahasiswa_id,
    'magang_id' => $magangTRPL->magang_id,
    'magang_trik_id' => $magangTRIK->magang_id,
], JSON_PRETTY_PRINT) . PHP_EOL;
