<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Logbook;
use App\Models\Magang;

class LogbookSeeder extends Seeder
{
    public function run(): void
    {
        $magangList = Magang::where('status_magang', '!=', 'draft')->get();

        foreach ($magangList as $magang) {
            Logbook::updateOrCreate(
                ['magang_id' => $magang->magang_id],
                [
                    'kegiatan'   => "Mengikuti daily standup meeting bersama tim pengembang, melakukan implementasi endpoint REST API, penulisan unit testing, serta penyusunan dokumentasi arsitektur sistem.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
}
