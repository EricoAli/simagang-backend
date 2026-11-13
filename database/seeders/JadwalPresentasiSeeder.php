<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Magang;
use Carbon\Carbon;

class JadwalPresentasiSeeder extends Seeder
{
    public function run(): void
    {
        $magangList = Magang::where('status_magang', '!=', 'draft')->get();

        if ($magangList->isEmpty()) {
            return;
        }

        $ruanganList = [
            'Lab Rekayasa Perangkat Lunak (Ruang 201)',
            'Lab Jaringan & Sistem Cerdas (Ruang 202)',
            'Ruang Seminar DTEDI Lantai 3',
            'Auditorium Gedung Herman Johannes',
        ];

        foreach ($magangList->take(6) as $index => $magang) {
            $ruangan = $ruanganList[$index % count($ruanganList)];
            $tgl = Carbon::now()->addDays($index * 2 + 1)->format('Y-m-d');
            $status = $index % 3 === 0 ? 'selesai' : 'terjadwal';

            DB::table('jadwal_presentasi')->updateOrInsert(
                ['magang_id' => $magang->magang_id],
                [
                    'tanggal_presentasi' => $tgl,
                    'waktu_mulai'        => '09:00',
                    'waktu_selesai'      => '10:30',
                    'tempat'             => 'Departemen DTEDI SV UGM',
                    'ruangan'            => $ruangan,
                    'keterangan'         => 'Presentasi Kemajuan dan Evaluasi Laporan Proyek Industri Magang.',
                    'status'             => $status,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]
            );
        }
    }
}
