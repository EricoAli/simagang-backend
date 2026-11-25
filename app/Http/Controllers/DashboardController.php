<?php

namespace App\Http\Controllers;

use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Mahasiswa;
use App\Models\NilaiMitra;
use Illuminate\Http\Request;
use App\Models\Magang;
use App\Models\Dosbing;
use App\Models\Logbook;
use App\Models\Mitra;


class DashboardController extends Controller
{
    public function fetchDashboardDataAdmin(Request $request)
    {
        $user = $request->user();

        // Hanya Admin Prodi / Super Admin yang boleh melihat dashboard admin.
        if (!$user || !in_array($user->role, ['admin', 'super_admin'], true)) {
            abort(403, 'Anda tidak berhak mengakses dashboard admin.');
        }

        $prodiId = null;
        $adminWithoutProdi = false;

        // Admin Prodi: hanya data prodinya. Super Admin: agregat seluruh prodi.
        if ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            $adminWithoutProdi = !$prodiId;
        }

        $magangQuery = Magang::query();
        $mahasiswaQuery = Mahasiswa::query();
        $aktivitasQuery = Magang::query()
            ->with(['mahasiswa.prodi', 'mitra'])
            ->whereHas('mahasiswa')
            ->whereIn('status_magang', ['berlangsung', 'selesai']);

        if ($adminWithoutProdi) {
            // Admin tanpa prodi tetap tidak mendapat data apa pun.
            $jumlahMagangAktif = 0;
            $jumlahMahasiswa = 0;
        } else {
            if ($prodiId) {
                $mahasiswaQuery->where('prodi_id', $prodiId);
                $magangQuery->whereHas('mahasiswa', function ($q) use ($prodiId) {
                    $q->where('prodi_id', $prodiId);
                });
                $aktivitasQuery->whereHas('mahasiswa', function ($q) use ($prodiId) {
                    $q->where('prodi_id', $prodiId);
                });
            }

            $SemesterMagangTerbaru = (clone $magangQuery)
                ->latest('semester_magang')
                ->value('semester_magang');

            $jumlahMagangAktif = $SemesterMagangTerbaru
                ? (clone $magangQuery)->where('semester_magang', $SemesterMagangTerbaru)->count()
                : 0;

            $jumlahMahasiswa = $mahasiswaQuery->count();
        }

        $aktivitasTerbaru = $adminWithoutProdi
            ? collect()
            : $aktivitasQuery
                ->orderByDesc('updated_at')
                ->orderByDesc('magang_id')
                ->limit(5)
                ->get()
                ->map(function (Magang $magang) {
                    $mahasiswa = $magang->mahasiswa;
                    $prodi = $mahasiswa?->prodi;

                    return [
                        'magang_id' => $magang->magang_id,
                        'nama_mahasiswa' => $mahasiswa?->nama,
                        'nim' => $mahasiswa?->nim,
                        'kode_prodi' => $prodi?->kode_prodi,
                        'nama_prodi' => $prodi?->nama_prodi,
                        'nama_mitra' => $magang->mitra?->nama_mitra,
                        'tanggal_mulai' => $magang->tanggal_mulai,
                        'tanggal_selesai' => $magang->tanggal_selesai,
                        'status_magang' => $magang->status_magang,
                        // Magang tidak memiliki timestamp perubahan status khusus.
                        'updated_at' => $magang->updated_at,
                    ];
                })
                ->values();

        // 2. Hitung mitra
        $jumlahMitra = Mitra::count();

        // 3. Hitung dosen pembimbing
        $jumlahDosbing = Dosbing::count();

        // Return JSON gabungan
        return response()->json([
            'jumlah_mahasiswa_aktif_magang' => $jumlahMagangAktif,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'jumlah_mitra' => $jumlahMitra,
            'jumlah_dosbing' => $jumlahDosbing,
            'aktivitas_terbaru' => $aktivitasTerbaru,
        ], 200);
    }

    public function fetchDashboardDataMitra()
    {
        // Hitung jumlah mahasiswa magang untuk mitra yang sedang login
        $user = auth()->user();

        if (!$user || $user->role !== 'mitra' || !$user->mitra) {
            abort(403, 'Anda tidak berhak mengakses dashboard mitra.');
        }

        $mitraId = $user->mitra->mitra_id;

        $jumlahMahasiswaMagangByMitra = Magang::where('mitra_id', $mitraId)
            ->distinct('mahasiswa_id')
            ->count('mahasiswa_id');

        $jumlahPenilaian = NilaiMitra::whereHas('magang', function ($query) use ($mitraId) {
            $query->where('mitra_id', $mitraId);
        })->count();

        $jumlahMagang = Magang::where('mitra_id', $mitraId)->count();

        return response()->json([
            'jumlah_mahasiswa_magang_by_mitra' => $jumlahMahasiswaMagangByMitra,
            'jumlah_penilaian' => $jumlahPenilaian,
            'jumlah_magang' => $jumlahMagang,
        ], 200);
    }


    public function fetchDashboardDataMahasiswa()
    {
        // 1. Ambil Mahasiswa ID dari Auth
        // Pastikan relasi 'mahasiswa' ada di model User
        $user = auth()->user();
        if (!$user || $user->role !== 'mahasiswa') {
            return response()->json(['message' => 'Anda tidak berhak mengakses dashboard mahasiswa.'], 403);
        }
        if (!$user->mahasiswa) {
            return response()->json(['message' => 'Data mahasiswa tidak ditemukan'], 404);
        }
        $mahasiswaId = $user->mahasiswa->mahasiswa_id;

        $data = [
            'magang_semester' => 6,
            'persiapan_magang' => 0,
            'pelaksanaan_magang' => 0,
            'laporan_magang' => 0
        ];


        // Ambil data Magang 2
        $magang2 = Magang::where('mahasiswa_id', $mahasiswaId)
            ->where('semester_magang', 7) // Filter Semester 7
            ->first();


        // Ambil data Magang 1
        $magang1 = Magang::where('mahasiswa_id', $mahasiswaId)
            ->where('semester_magang', 6)
            ->first(); // Penting: gunakan first()


        if ($magang2){
            $data['magang_semester'] = 7;

            // Cek dokumen (return true/false)
            $hasPraKrs = DokumenMagang::where('magang_id', $magang2->magang_id)->where('jenis_dokumen', 'doc_pra_krs')->exists();
            $hasSuratTerima = DokumenMagang::where('magang_id', $magang2->magang_id)->where('jenis_dokumen', 'doc_surat_penerimaan')->exists();
            $hasLogbook = Logbook::where('magang_id', $magang2->magang_id)->exists();
            // Asumsi laporan akhir juga ada di tabel dokumen
            // $hasLaporan = DokumenMagang::where('magang_id', $magang2->magang_id)->where('jenis_dokumen', 'doc_laporan_magang')->exists();
            $hasLaporan = Laporan::where('magang_id', $magang2->magang_id)->exists();

            // Hitung Skor Persiapan

            if ($hasPraKrs) $data['persiapan_magang'] += 50;
            if ($hasSuratTerima) $data['persiapan_magang'] += 50;

            // Hitung Skor Pelaksanaan
            if ($hasLogbook) $data['pelaksanaan_magang'] = 100;

            // Hitung Skor Laporan
            if ($hasLaporan) $data['laporan_magang'] = 100;
        } else if ($magang1) {
            
            $data['magang_semester'] = 6;



            // Cek dokumen (return true/false)
            $hasPraKrs = DokumenMagang::where('magang_id', $magang1->magang_id)->where('jenis_dokumen', 'doc_pra_krs')->exists();
            $hasSuratTerima = DokumenMagang::where('magang_id', $magang1->magang_id)->where('jenis_dokumen', 'doc_surat_penerimaan')->exists();
            $hasLogbook = Logbook::where('magang_id', $magang1->magang_id)->exists();
            // Asumsi laporan akhir juga ada di tabel dokumen
            // $hasLaporan = DokumenMagang::where('magang_id', $magang1->magang_id)->where('jenis_dokumen', 'doc_laporan_magang')->exists();
            $hasLaporan = Laporan::where('magang_id', $magang1->magang_id)->exists();

            // Hitung Skor Persiapan
            if ($hasPraKrs) $data['persiapan_magang'] += 50;
            if ($hasSuratTerima) $data['persiapan_magang'] += 50;

            // Hitung Skor Pelaksanaan
            if ($hasLogbook) $data['pelaksanaan_magang'] = 100;

            // Hitung Skor Laporan
            if ($hasLaporan) $data['laporan_magang'] = 100;
        
        }


        return response()->json([
            'status' => 'success',
            'data' => $data,
            ]
        );
    }
}
