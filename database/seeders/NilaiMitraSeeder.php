<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\NilaiMitra;
use App\Models\Magang;

class NilaiMitraSeeder extends Seeder
{
    public function run(): void
    {
        $magangList = Magang::whereIn('status_magang', ['berlangsung', 'selesai'])->get();

        foreach ($magangList as $index => $magang) {
            $namaFile = 'penilaian_mitra_' . $magang->magang_id . '.pdf';

            NilaiMitra::updateOrCreate(
                ['magang_id' => $magang->magang_id],
                [
                    'nilai_teknis'                     => 88.50 + ($index % 10),
                    'nilai_profesionalisme_etika'      => 90.00 + ($index % 8),
                    'nilai_komunikasi_presentasi'      => 86.00 + ($index % 9),
                    'nilai_proyek_pengalaman_industri' => 89.00 + ($index % 10),
                    'keterangan'                       => 'Mahasiswa aktif, cepat beradaptasi dengan stack teknologi tim, dan menyelesaikan modul tepat waktu.',
                    'supervisor'                       => 'Kevin Sanjaya, S.Kom.',
                    'jabatan_supervisor'               => 'Lead Engineering Supervisor',
                    'nama_file'                        => $namaFile,
                    'file_path'                        => 'penilaian_mitra/sampel_penilaian.pdf',
                    'created_at'                       => now(),
                    'updated_at'                       => now(),
                ]
            );
        }
    }
}
