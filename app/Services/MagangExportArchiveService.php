<?php

namespace App\Services;

use App\Models\Magang;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class MagangExportArchiveService
{
    private const CHUNK_SIZE = 100;
    private const MAX_MAGANG_ROWS = 10000;

    /**
     * Build a ZIP archive without changing source files or database records.
     *
     * @return array{path:string, filename:string, work_directory:string}
     */
    public function create(array $filters): array
    {
        $token = (string) Str::uuid();
        $baseDirectory = storage_path('app/temp/exports');
        $workDirectory = $baseDirectory . DIRECTORY_SEPARATOR . $token;
        $zipPath = $baseDirectory . DIRECTORY_SEPARATOR . "{$token}.zip";
        $xlsxPath = $workDirectory . DIRECTORY_SEPARATOR . 'Data_Magang.xlsx';

        File::ensureDirectoryExists($workDirectory);

        try {
            $spreadsheet = new Spreadsheet();
            $dataSheet = $spreadsheet->getActiveSheet();
            $dataSheet->setTitle('Data Magang');
            $logbookSheet = $spreadsheet->createSheet()->setTitle('Logbook');
            $documentSheet = $spreadsheet->createSheet()->setTitle('Dokumen');
            $photoSheet = $spreadsheet->createSheet()->setTitle('Foto Logbook');

            $this->writeHeaders($dataSheet, [
                'No', 'ID Magang', 'Nama', 'NIM', 'Prodi', 'Angkatan',
                'Perusahaan', 'Periode Magang', 'Status Magang',
                'Dosen Pembimbing', 'Role Magang', 'Jobdesk', 'Periode Durasi',
                'Dokumen Penerimaan', 'Dokumen Pra-KRS',
                'Nilai Teknis', 'Nilai Profesionalisme dan Etika',
                'Nilai Komunikasi dan Presentasi',
                'Nilai Proyek dan Pengalaman Industri',
                'Dokumen Penilaian', 'Dokumen Laporan Magang',
            ]);
            $this->writeHeaders($logbookSheet, [
                'ID Logbook', 'ID Magang', 'Nama', 'NIM', 'Periode Magang',
                'Tanggal Kegiatan', 'Kegiatan', 'Jumlah Foto',
            ]);
            $this->writeHeaders($documentSheet, [
                'ID Dokumen', 'ID Magang', 'Nama', 'NIM', 'Periode Magang',
                'Sumber Dokumen', 'Jenis Dokumen', 'Nama File', 'Ukuran File',
                'Path Relatif dalam ZIP', 'Status File', 'Status Dokumen',
            ]);
            $this->writeHeaders($photoSheet, [
                'ID Foto', 'ID Logbook', 'ID Magang', 'Nama', 'NIM',
                'Periode Magang', 'Nama File', 'Keterangan',
                'Path Relatif dalam ZIP', 'Status File',
            ]);

            $rowNumbers = [
                'data' => 2,
                'logbook' => 2,
                'document' => 2,
                'photo' => 2,
            ];
            $missingFiles = [];
            $seenDocuments = [];
            $magangNumber = 1;

            $query = Magang::with([
                'mahasiswa.prodi',
                'mitra',
                'dosenPembimbing',
                'periode.tahunAjaran',
                'dokumenMagang',
                'laporan',
                'penilaianMitra',
                'logbook.fotoKegiatan',
            ]);

            $this->applyFilters($query, $filters);

            if ((clone $query)->count() > self::MAX_MAGANG_ROWS) {
                throw new \LengthException(
                    'Export terlalu besar. Gunakan filter untuk membatasi maksimal '
                    . self::MAX_MAGANG_ROWS . ' riwayat magang.'
                );
            }

            $query->orderBy('magang_id')->chunkById(
                self::CHUNK_SIZE,
                function ($magangs) use (
                    $dataSheet,
                    $logbookSheet,
                    $documentSheet,
                    $photoSheet,
                    &$rowNumbers,
                    &$missingFiles,
                    &$seenDocuments,
                    &$magangNumber
                ) {
                    foreach ($magangs as $magang) {
                        $period = $this->periodLabel($magang);
                        $name = $magang->mahasiswa?->nama ?? '-';
                        $nim = $magang->mahasiswa?->nim ?? '-';
                        $documentReferences = [
                            'doc_surat_penerimaan' => '-',
                            'doc_pra_krs' => '-',
                            'doc_penilaian_mitra' => '-',
                            'doc_laporan_magang' => '-',
                        ];

                        foreach ($this->documentsFor($magang) as $document) {
                            $reference = $this->addDocument(
                                $documentSheet,
                                $rowNumbers['document'],
                                $document,
                                $magang,
                                $period,
                                $missingFiles,
                                $seenDocuments
                            );
                            if ($reference && isset($documentReferences[$document['jenis']])) {
                                $documentReferences[$document['jenis']] = $reference;
                            }
                        }

                        foreach ($magang->logbook as $logbook) {
                            $logbookSheet->fromArray([
                                $logbook->logbook_id,
                                $magang->magang_id,
                                $name,
                                $nim,
                                $period,
                                optional($logbook->tanggal_kegiatan)->format('Y-m-d'),
                                $logbook->kegiatan,
                                $logbook->fotoKegiatan->count(),
                            ], null, 'A' . $rowNumbers['logbook']++);

                            foreach ($logbook->fotoKegiatan as $photo) {
                                $reference = $this->addPhoto(
                                    $photoSheet,
                                    $rowNumbers['photo'],
                                    $photo,
                                    $logbook,
                                    $magang,
                                    $period,
                                    $missingFiles
                                );
                                $photoSheet->setCellValue(
                                    'I' . ($rowNumbers['photo'] - 1),
                                    $reference['relative_path']
                                );
                            }
                        }

                        $dataSheet->fromArray([
                            $magangNumber++,
                            $magang->magang_id,
                            $name,
                            $nim,
                            $magang->mahasiswa?->prodi?->kode_prodi
                                ?? $magang->mahasiswa?->prodi?->nama_prodi ?? '-',
                            $magang->mahasiswa?->angkatan ?? '-',
                            $magang->mitra?->nama_mitra ?? '-',
                            $period,
                            $magang->status_magang ?? '-',
                            $magang->dosenPembimbing?->nama ?? '-',
                            $magang->role_magang ?? '-',
                            $magang->jobdesk ?? '-',
                            $magang->periode_bulan ?? '-',
                            $documentReferences['doc_surat_penerimaan'],
                            $documentReferences['doc_pra_krs'],
                            $magang->penilaianMitra?->nilai_teknis ?? '-',
                            $magang->penilaianMitra?->nilai_profesionalisme_etika ?? '-',
                            $magang->penilaianMitra?->nilai_komunikasi_presentasi ?? '-',
                            $magang->penilaianMitra?->nilai_proyek_pengalaman_industri ?? '-',
                            $documentReferences['doc_penilaian_mitra'],
                            $documentReferences['doc_laporan_magang'],
                        ], null, 'A' . $rowNumbers['data']++);
                    }
                },
                'magang_id'
            );

            foreach ([$dataSheet, $logbookSheet, $documentSheet, $photoSheet] as $sheet) {
                $sheet->getStyle('1:1')->getFont()->setBold(true);
                $sheet->freezePane('A2');
            }

            (new Xlsx($spreadsheet))->save($xlsxPath);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException('Arsip ZIP tidak dapat dibuat.');
            }

            $zip->addFile($xlsxPath, 'Data_Magang.xlsx');
            $this->addAttachmentsToZip($zip, $missingFiles);
            if ($missingFiles) {
                $zip->addFromString('missing_files.txt', implode(PHP_EOL, $missingFiles) . PHP_EOL);
            }
            $zip->close();

            File::deleteDirectory($workDirectory);

            return [
                'path' => $zipPath,
                'filename' => 'export-magang-' . now()->format('Ymd-His') . '.zip',
                'work_directory' => $workDirectory,
            ];
        } catch (\Throwable $exception) {
            File::deleteDirectory($workDirectory);
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            throw $exception;
        }
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['angkatan'])) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('angkatan', $filters['angkatan']));
        }
        if (!empty($filters['semester_magang'])) {
            $query->where('semester_magang', $filters['semester_magang']);
        }
        if (!empty($filters['periode_id'])) {
            $query->where('periode_id', $filters['periode_id']);
        }
        if (!empty($filters['mitra_id'])) {
            $query->where('mitra_id', $filters['mitra_id']);
        }
        if (!empty($filters['prodi_id'])) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $filters['prodi_id']));
        }
        if (!empty($filters['mahasiswa_id'])) {
            $query->where('mahasiswa_id', $filters['mahasiswa_id']);
        }
    }

    private function writeHeaders($sheet, array $headers): void
    {
        $sheet->fromArray($headers, null, 'A1');
        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }
    }

    private function periodLabel(Magang $magang): string
    {
        if ($magang->periode?->tahunAjaran?->tahun_ajaran) {
            return $magang->periode->tahunAjaran->tahun_ajaran
                . ' - Semester ' . $magang->periode->semester_magang
                . ' ' . $magang->periode->jenis_semester;
        }
        if ($magang->tahun_ajaran || $magang->semester_magang) {
            return ($magang->tahun_ajaran ?: 'Tahun ajaran belum ditentukan')
                . ' - Semester ' . ($magang->semester_magang ?: '-');
        }
        return 'Belum ditentukan';
    }

    private function documentsFor(Magang $magang): array
    {
        $documents = [];
        foreach ($magang->dokumenMagang as $document) {
            $documents[] = [
                'id' => $document->dokumen_id,
                'source' => 'dokumen_magang',
                'jenis' => $document->jenis_dokumen,
                'label' => $document->jenis_dokumen_label,
                'name' => $document->nama_file,
                'path' => $document->file_path,
                'size' => $document->ukuran_file,
                'status' => $document->status_dokumen ?? '-',
            ];
        }
        if ($magang->laporan) {
            $documents[] = [
                'id' => $magang->laporan->laporan_id,
                'source' => 'laporan',
                'jenis' => 'doc_laporan_magang',
                'label' => 'Laporan Magang',
                'name' => $magang->laporan->nama_file ?: 'Laporan Magang',
                'path' => $magang->laporan->file_path,
                'size' => null,
                'status' => '-',
            ];
        }
        if ($magang->penilaianMitra?->file_path) {
            $documents[] = [
                'id' => $magang->penilaianMitra->penilaian_id,
                'source' => 'penilaian_mitra',
                'jenis' => 'doc_penilaian_mitra',
                'label' => 'Penilaian Mitra',
                'name' => $magang->penilaianMitra->nama_file ?: 'Penilaian Mitra',
                'path' => $magang->penilaianMitra->file_path,
                'size' => null,
                'status' => '-',
            ];
        }
        return $documents;
    }

    private function addDocument($sheet, int &$row, array $document, Magang $magang, string $period, array &$missing, array &$seen): ?string
    {
        $resolved = $this->resolveFile($document['path']);
        $documentKey = $resolved['normalized'] ?? ($document['source'] . ':' . $document['id']);
        $key = $magang->magang_id . '|' . $documentKey;
        if (isset($seen[$key])) {
            return $seen[$key];
        }

        $relative = 'Lampiran/Dokumen_Magang/Magang_' . $magang->magang_id
            . '/Dokumen_' . $document['id'] . '_' . $this->safeFilename($document['name']);
        $status = 'Tersedia';
        if (!$resolved) {
            $status = 'Tidak ditemukan';
            $missing[] = "[Dokumen] id={$document['id']}; magang_id={$magang->magang_id}; path={$document['path']}";
        }

        $sheet->fromArray([
            $document['id'], $magang->magang_id, $magang->mahasiswa?->nama ?? '-',
            $magang->mahasiswa?->nim ?? '-', $period, $document['source'],
            $document['label'], $document['name'] ?: '-', $document['size'] ?? '-',
            $relative, $status, $document['status'],
        ], null, 'A' . $row++);

        $seen[$key] = $relative;
        if ($resolved) {
            $this->attachments[] = [$resolved['absolute'], $relative];
        }
        return $relative;
    }

    private function addPhoto($sheet, int &$row, $photo, $logbook, Magang $magang, string $period, array &$missing): array
    {
        $relative = 'Lampiran/Foto_Logbook/Magang_' . $magang->magang_id
            . '/Logbook_' . $logbook->logbook_id
            . '/Foto_' . $photo->foto_id . '_' . $this->safeFilename($photo->nama_file);
        $resolved = $this->resolveFile($photo->file_path);
        $status = $resolved ? 'Tersedia' : 'Tidak ditemukan';
        if (!$resolved) {
            $missing[] = "[Foto] id={$photo->foto_id}; logbook_id={$logbook->logbook_id}; path={$photo->file_path}";
        } else {
            $this->attachments[] = [$resolved['absolute'], $relative];
        }
        $sheet->fromArray([
            $photo->foto_id, $logbook->logbook_id, $magang->magang_id,
            $magang->mahasiswa?->nama ?? '-', $magang->mahasiswa?->nim ?? '-',
            $period, $photo->nama_file ?: '-', $photo->keterangan ?: '-',
            $relative, $status,
        ], null, 'A' . $row++);
        return ['relative_path' => $relative];
    }

    private array $attachments = [];

    private function addAttachmentsToZip(ZipArchive $zip, array $missing): void
    {
        foreach ($this->attachments as [$absolute, $relative]) {
            if (is_file($absolute)) {
                $zip->addFile($absolute, $relative);
            }
        }
        $this->attachments = [];
    }

    private function resolveFile(?string $path): ?array
    {
        $normalized = $this->normalizePath($path);
        if (!$normalized) {
            return null;
        }
        $disk = Storage::disk('public');
        if (!$disk->exists($normalized)) {
            return null;
        }
        $root = realpath($disk->path(''));
        $absolute = realpath($disk->path($normalized));
        if (!$root || !$absolute || !is_file($absolute)) {
            return null;
        }
        $root = rtrim(str_replace('\\', '/', $root), '/') . '/';
        if (!str_starts_with(str_replace('\\', '/', $absolute), $root)) {
            return null;
        }
        return ['normalized' => $normalized, 'absolute' => $absolute];
    }

    private function normalizePath(?string $path): ?string
    {
        $path = trim(str_replace('\\', '/', (string) $path));
        if ($path === '' || preg_match('/^(https?:)?\\/\\//i', $path)) {
            return null;
        }
        $path = ltrim($path, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }
        if ($path === '' || str_contains($path, '../') || str_contains($path, '..\\')) {
            return null;
        }
        return $path;
    }

    private function safeFilename(?string $name): string
    {
        $name = basename((string) $name);
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'file';
        return trim($name, '._') ?: 'file';
    }
}
