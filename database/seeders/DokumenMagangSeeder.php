<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Magang;
use App\Models\DokumenMagang;
use Illuminate\Support\Facades\Storage;

class DokumenMagangSeeder extends Seeder
{
    public function run(): void
    {
        $sourcePath = 'dokumen_magang/sampel_doc.pdf';

        // Jika belum ada di disk public, salin dari database/seeders/sampel_doc.pdf
        if (!Storage::disk('public')->exists($sourcePath)) {
            $rawPath = database_path('seeders/sampel_doc.pdf');
            if (file_exists($rawPath)) {
                Storage::disk('public')->put($sourcePath, file_get_contents($rawPath));
            }
        }

        $dokumenTypes = [
            'doc_surat_penerimaan' => 'Surat Penerimaan Perusahaan',
            'doc_pra_krs'          => 'Form Pra KRS Magang',
            'doc_laporan_magang'   => 'Laporan Magang Tengah Periode',
        ];

        $magangs = Magang::all();

        foreach ($magangs as $magang) {
            foreach ($dokumenTypes as $type => $label) {
                $namaFile = $type . '_' . $magang->magang_id . '.pdf';
                $destPath = 'dokumen_magang/' . $namaFile;

                if (Storage::disk('public')->exists($sourcePath) && !Storage::disk('public')->exists($destPath)) {
                    Storage::disk('public')->copy($sourcePath, $destPath);
                }

                $size = Storage::disk('public')->exists($destPath)
                    ? Storage::disk('public')->size($destPath)
                    : 30175;

                DokumenMagang::updateOrCreate(
                    [
                        'magang_id'     => $magang->magang_id,
                        'jenis_dokumen' => $type,
                    ],
                    [
                        'nama_file'      => $namaFile,
                        'file_path'      => $destPath,
                        'ukuran_file'    => $size,
                        'status_dokumen' => 'approved',
                        'keterangan'     => "Dokumen {$label} telah diverifikasi.",
                        'uploaded_at'    => now(),
                        'updated_at'     => now(),
                    ]
                );
            }
        }
    }
}
