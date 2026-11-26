<?php

namespace Database\Seeders;

use App\Models\PeriodeMagang;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;

class PeriodeMagangSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            '2025/2026' => [
                ['semester_magang' => 6, 'jenis_semester' => 'Genap', 'is_active' => true],
                ['semester_magang' => 7, 'jenis_semester' => 'Gasal', 'is_active' => true],
            ],
            '2026/2027' => [
                ['semester_magang' => 6, 'jenis_semester' => 'Genap', 'is_active' => true],
                ['semester_magang' => 7, 'jenis_semester' => 'Gasal', 'is_active' => true],
            ],
            '2027/2028' => [
                ['semester_magang' => 6, 'jenis_semester' => 'Genap', 'is_active' => true],
                ['semester_magang' => 7, 'jenis_semester' => 'Gasal', 'is_active' => true],
            ],
        ];

        foreach ($catalog as $tahunStr => $periods) {
            $tahun = TahunAjaran::firstOrCreate(
                ['tahun_ajaran' => $tahunStr],
                ['is_active' => true]
            );

            foreach ($periods as $period) {
                PeriodeMagang::firstOrCreate(
                    [
                        'tahun_ajaran_id' => $tahun->tahun_ajaran_id,
                        'semester_magang' => $period['semester_magang'],
                        'jenis_semester' => $period['jenis_semester'],
                    ],
                    ['is_active' => $period['is_active']]
                );
            }
        }
    }
}
