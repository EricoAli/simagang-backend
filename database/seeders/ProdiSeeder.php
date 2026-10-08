<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ProdiSeeder — idempotent master data seeder for Prodi.
 *
 * nama_prodi CONFIRMED: Hanya TRPL yang memiliki nama resmi dari dokumentasi 2026.
 * TRIK, TRI, TRE: NEED REVIEW — nama resmi belum tersedia di docs/.
 * Jangan ubah tanpa konfirmasi stakeholder.
 */
class ProdiSeeder extends Seeder
{
    public function run(): void
    {
        $prodis = [
            [
                'kode_prodi' => 'TRPL',
                // CONFIRMED — sumber: docs/00-context.md, docs/01-current-requirements.md
                'nama_prodi' => 'Teknologi Rekayasa Perangkat Lunak',
            ],
            [
                'kode_prodi' => 'TRIK',
                // NEED REVIEW — nama resmi belum dikonfirmasi dari dokumen 2026
                'nama_prodi' => 'TRIK',
            ],
            [
                'kode_prodi' => 'TRI',
                // NEED REVIEW — nama resmi belum dikonfirmasi dari dokumen 2026
                'nama_prodi' => 'TRI',
            ],
            [
                'kode_prodi' => 'TRE',
                // NEED REVIEW — nama resmi belum dikonfirmasi dari dokumen 2026
                'nama_prodi' => 'TRE',
            ],
        ];

        foreach ($prodis as $prodi) {
            // Idempotent: updateOrInsert berdasarkan kode_prodi
            DB::table('prodi')->updateOrInsert(
                ['kode_prodi' => $prodi['kode_prodi']],
                array_merge($prodi, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
