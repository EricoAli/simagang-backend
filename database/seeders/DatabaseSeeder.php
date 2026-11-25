<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProdiSeeder::class,
            AdminSeeder::class,
            SuperAdminSeeder::class,
            // MahasiswaSeeder::class,
            // DosbingSeeder::class,
            // MitraSeeder::class,
            // // SupervisorSeeder::class,
            // MagangSeeder::class,
            // DokumenMagangSeeder::class,
            // LogbookSeeder::class,
            // JadwalPresentasiSeeder::class,
            // NilaiMitraSeeder::class,
            // FotoMagangSeeder::class,
        ]);
    }
}
