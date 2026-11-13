<?php

namespace Database\Seeders;

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
            DosbingSeeder::class,
            MitraSeeder::class,
            MahasiswaSeeder::class,
            MagangSeeder::class,
            DokumenMagangSeeder::class,
            LogbookSeeder::class,
            FotoMagangSeeder::class,
            JadwalPresentasiSeeder::class,
            NilaiMitraSeeder::class,
            LaporanSeeder::class,
        ]);
    }
}
