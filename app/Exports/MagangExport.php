<?php

namespace App\Exports;

use App\Models\Magang;
use App\Models\PeriodeMagang;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MagangExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $angkatan;
    protected $semesterMagang;
    protected $prodiId;
    protected $mitraId;
    protected $tahunAjaranId;
    protected $periodeId;
    protected $tahunAjaran;
    protected $jenisSemester;
    protected $dosbingId;
    protected $statusMagang;
    protected $row = 1;

    public function __construct(
        $angkatan = null,
        $semesterMagang = null,
        $prodiId = null,
        $mitraId = null,
        $tahunAjaranId = null,
        $periodeId = null,
        $tahunAjaran = null,
        $jenisSemester = null,
        $dosbingId = null,
        $statusMagang = null
    ) {
        $this->angkatan = $angkatan;
        $this->semesterMagang = $semesterMagang;
        $this->prodiId = $prodiId;
        $this->mitraId = $mitraId;
        $this->tahunAjaranId = $tahunAjaranId;
        $this->periodeId = $periodeId;
        $this->tahunAjaran = $tahunAjaran;
        $this->jenisSemester = $jenisSemester;
        $this->dosbingId = $dosbingId;
        $this->statusMagang = $statusMagang;
    }

    public function collection()
    {
        $query = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'masterTahunAjaran',
            'dokumenMagang' => function ($q) {
                $q->whereIn('jenis_dokumen', ['doc_surat_penerimaan', 'doc_pra_krs']);
            },
            'laporan',
            'logbook.fotoKegiatan',
            'logbooks.fotoKegiatan',
            'penilaianMitra',
        ]);

        if ($this->angkatan) {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('angkatan', $this->angkatan);
            });
        }

        if ($this->semesterMagang) {
            $query->where('semester_magang', (int) $this->semesterMagang);
        }

        if ($this->prodiId) {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('prodi_id', $this->prodiId);
            });
        }

        if ($this->mitraId) {
            $query->where('mitra_id', $this->mitraId);
        }

        if ($this->tahunAjaranId) {
            $query->where('tahun_ajaran_id', $this->tahunAjaranId);
        }

        if ($this->periodeId) {
            $query->where('periode_id', $this->periodeId);
        }

        if ($this->tahunAjaran) {
            $tahunStr = trim((string) $this->tahunAjaran);
            $query->where('tahun_ajaran', 'like', "{$tahunStr}%");
        }

        if ($this->jenisSemester) {
            $normalizedJenis = PeriodeMagang::normalizeJenisSemester((string) $this->jenisSemester);
            if ($normalizedJenis) {
                $query->where('jenis_semester', $normalizedJenis);
            }
        }

        if ($this->dosbingId) {
            $query->where('dosbing_id', $this->dosbingId);
        }

        if ($this->statusMagang) {
            $normalizedStatus = Magang::normalizeStatus((string) $this->statusMagang);
            if ($normalizedStatus) {
                $query->where('status_magang', $normalizedStatus);
            }
        }

        return $query->get()->sort(function (Magang $a, Magang $b) {
            $yearA = (string) ($a->periode?->tahunAjaran?->tahun_ajaran ?? $a->masterTahunAjaran?->tahun_ajaran ?? $a->tahun_ajaran ?? '');
            $yearB = (string) ($b->periode?->tahunAjaran?->tahun_ajaran ?? $b->masterTahunAjaran?->tahun_ajaran ?? $b->tahun_ajaran ?? '');
            if ($yearA !== $yearB) {
                return strcmp($yearB, $yearA);
            }

            $jenisA = (string) ($a->periode?->jenis_semester ?? $a->jenis_semester ?? '');
            $jenisB = (string) ($b->periode?->jenis_semester ?? $b->jenis_semester ?? '');
            if ($jenisA !== $jenisB) {
                return strcmp($jenisA, $jenisB);
            }

            $semCmp = ((int) $a->semester_magang) <=> ((int) $b->semester_magang);
            if ($semCmp !== 0) {
                return $semCmp;
            }

            return strcmp((string) ($a->mahasiswa?->nim ?? ''), (string) ($b->mahasiswa?->nim ?? ''));
        })->values();
    }

    public function headings(): array
    {
        $fotoHeadings = [];
        for ($i = 1; $i <= 5; $i++) {
            $fotoHeadings[] = "Foto Logbook $i";
        }

        return array_merge([
            'No',
            'Nama',
            'NIM',
            'Prodi',
            'Tahun Ajaran',
            'Periode',
            'Semester',
            'Status Magang',
            'Perusahaan',
            'Dosen Pembimbing',
            'Dokumen Penerimaan',
            'Dokumen Pra-KRS',
        ], $fotoHeadings, [
            'Nilai Teknis',
            'Nilai Profesionalisme dan Etika',
            'Nilai Komunikasi dan Presentasi',
            'Nilai Proyek dan Pengalaman Industri',
            'Dokumen Penilaian',
            'Dokumen Laporan Magang',
        ]);
    }

    public function map($magang): array
    {
        $baseUrl = rtrim((string) config('app.url'), '/').'/storage/';

        $dokPenerimaan = $magang->dokumenMagang->firstWhere('jenis_dokumen', 'doc_surat_penerimaan');
        $dokPraKRS = $magang->dokumenMagang->firstWhere('jenis_dokumen', 'doc_pra_krs');

        // Ambil maks 5 foto dari logbooks (mendukung multi-logbook & legacy single logbook)
        $fotoUrls = [];
        $logbookList = $magang->logbooks && $magang->logbooks->isNotEmpty()
            ? $magang->logbooks->sortByDesc('logbook_id')
            : ($magang->logbook ? collect([$magang->logbook]) : collect());

        foreach ($logbookList as $logbook) {
            if ($logbook->fotoKegiatan) {
                foreach ($logbook->fotoKegiatan as $foto) {
                    $fotoUrls[] = $foto->file_path ? $baseUrl.$foto->file_path : '-';
                    if (count($fotoUrls) >= 5) {
                        break 2;
                    }
                }
            }
        }
        while (count($fotoUrls) < 5) {
            $fotoUrls[] = '-';
        }

        $penilaian = $magang->penilaianMitra;
        $laporan = $magang->laporan;

        $tahunAjaranLabel = $magang->periode?->tahunAjaran?->tahun_ajaran
            ?? $magang->masterTahunAjaran?->tahun_ajaran
            ?? ($magang->tahun_ajaran ?: '-');
        $jenisSemester = $magang->periode?->jenis_semester ?? $magang->jenis_semester ?? '-';
        $periodeLabel = $magang->periode_label ?? '-';

        return array_merge([
            $this->row++,
            $magang->mahasiswa?->nama ?? '-',
            $magang->mahasiswa?->nim ?? '-',
            $magang->mahasiswa?->prodi?->kode_prodi ?? '-',
            $tahunAjaranLabel,
            $periodeLabel,
            $magang->semester_magang ? 'Semester '.$magang->semester_magang.($jenisSemester !== '-' ? ' ('.$jenisSemester.')' : '') : '-',
            $magang->status_label ?? '-',
            $magang->mitra?->nama_mitra ?? '-',
            $magang->dosenPembimbing?->nama ?? '-',
            $dokPenerimaan?->file_path ? $baseUrl.$dokPenerimaan->file_path : '-',
            $dokPraKRS?->file_path ? $baseUrl.$dokPraKRS->file_path : '-',
        ], $fotoUrls, [
            $penilaian?->nilai_teknis ?? '-',
            $penilaian?->nilai_profesionalisme_etika ?? '-',
            $penilaian?->nilai_komunikasi_presentasi ?? '-',
            $penilaian?->nilai_proyek_pengalaman_industri ?? '-',
            $penilaian?->file_path ? $baseUrl.$penilaian->file_path : '-',
            $laporan?->file_path ? $baseUrl.$laporan->file_path : '-',
        ]);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
