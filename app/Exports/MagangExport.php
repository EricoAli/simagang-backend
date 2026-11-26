<?php

namespace App\Exports;

use App\Models\Magang;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MagangExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /**
     * @return \Illuminate\Support\Collection
     */

    protected $angkatan;
    protected $semesterMagang;
    protected $mitraId;
    protected $prodiId;
    protected $mahasiswaId;
    protected $periodeId;
    protected $row = 1;

    public function __construct($angkatan = null, $semesterMagang = null, $mitraId = null, $prodiId = null, $mahasiswaId = null, $periodeId = null)
    {
        $this->angkatan = $angkatan;
        $this->semesterMagang = $semesterMagang;
        $this->mitraId = $mitraId;
        $this->prodiId = $prodiId;
        $this->mahasiswaId = $mahasiswaId;
        $this->periodeId = $periodeId;
    }
    public function collection()
    {
        $query = Magang::with([
            'mahasiswa',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'dokumenMagang' => function ($q) {
                $q->whereIn('jenis_dokumen', ['doc_surat_penerimaan', 'doc_pra_krs']);
            },
            'laporan',
            'logbook.fotoKegiatan',
            'penilaianMitra',
        ]);

        if ($this->angkatan) {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('angkatan', $this->angkatan);
            });
        }

        if ($this->semesterMagang) {
            $query->where('semester_magang', $this->semesterMagang);
        }

        if ($this->periodeId) {
            $query->where('periode_id', $this->periodeId);
        }

        if ($this->mitraId) {
            $query->where('mitra_id', $this->mitraId);
        }

        if ($this->prodiId) {
            $query->whereHas('mahasiswa', function ($q) {
                $q->where('prodi_id', $this->prodiId);
            });
        }

        if ($this->mahasiswaId) {
            $query->where('mahasiswa_id', $this->mahasiswaId);
        }

        return $query->get();
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
            'Perusahaan',
            'Periode Magang',
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
        $baseUrl = rtrim(config('app.url'), '/') . '/storage/';

        $dokPenerimaan = $magang->dokumenMagang->firstWhere('jenis_dokumen', 'doc_surat_penerimaan');
        $dokPraKRS = $magang->dokumenMagang->firstWhere('jenis_dokumen', 'doc_pra_krs');

        // Foto logbook
        $fotoUrls = [];
        foreach ($magang->logbook->flatMap(fn ($entry) => $entry->fotoKegiatan)->take(5) as $foto) {
            $fotoUrls[] = $foto->file_path ? $baseUrl . $foto->file_path : '-';
        }
        while (count($fotoUrls) < 5) {
            $fotoUrls[] = '-';
        }

        $penilaian = $magang->penilaianMitra;
        $laporan = $magang->laporan?->first();

        return array_merge([
            $this->row++,
            $magang->mahasiswa?->nama ?? '-',
            $magang->mahasiswa?->nim ?? '-',
            $magang->mitra?->nama_mitra ?? '-',
            $this->periodeLabel($magang),
            $magang->dosenPembimbing?->nama ?? '-',
            $dokPenerimaan?->file_path ? $baseUrl . $dokPenerimaan->file_path : '-',
            $dokPraKRS?->file_path ? $baseUrl . $dokPraKRS->file_path : '-',
        ], $fotoUrls, [
            $penilaian?->nilai_teknis ?? '-',
            $penilaian?->nilai_profesionalisme_etika ?? '-',
            $penilaian?->nilai_komunikasi_presentasi ?? '-',
            $penilaian?->nilai_proyek_pengalaman_indsutri ?? '-',
            $penilaian?->file_path ? $baseUrl . $penilaian->file_path : '-',
            $laporan?->file_path ? $baseUrl . $laporan->file_path : '-',
        ]);
    }

    private function periodeLabel(Magang $magang): string
    {
        $periode = $magang->periode;
        if ($periode?->tahunAjaran?->tahun_ajaran) {
            return $periode->tahunAjaran->tahun_ajaran
                . ' - Semester ' . $periode->semester_magang
                . ' ' . $periode->jenis_semester;
        }

        if ($magang->tahun_ajaran || $magang->semester_magang) {
            return ($magang->tahun_ajaran ?: 'Tahun ajaran belum ditentukan')
                . ' - Semester ' . ($magang->semester_magang ?: '-');
        }

        return 'Belum ditentukan';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
