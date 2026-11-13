<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Laporan;
use App\Models\Magang;

class LaporanSeeder extends Seeder
{
    public function run(): void
    {
        $magangList = Magang::whereIn('status_magang', ['berlangsung', 'selesai'])->get();

        foreach ($magangList as $magang) {
            $namaFile = 'laporan_akhir_magang_' . $magang->magang_id . '.pdf';

            Laporan::updateOrCreate(
                ['magang_id' => $magang->magang_id],
                [
                    'nama_file'  => $namaFile,
                    'file_path'  => 'laporan/sampel_laporan.pdf',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}

