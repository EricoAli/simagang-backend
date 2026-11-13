<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Logbook;

class FotoMagangSeeder extends Seeder
{
    public function run(): void
    {
        $logbooks = Logbook::all();

        foreach ($logbooks as $logbook) {
            DB::table('foto_kegiatan')->updateOrInsert(
                [
                    'logbook_id' => $logbook->logbook_id,
                    'nama_file'  => 'kegiatan_magang.jpg',
                ],
                [
                    'file_path'   => 'foto_kegiatan/kegiatan_magang.jpg',
                    'keterangan'  => 'Dokumentasi kegiatan koordinasi dan review sprint magang.',
                    'uploaded_at' => now(),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]
            );
        }
    }
}
