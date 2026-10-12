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
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $queryMahasiswa = Mahasiswa::query();
        $queryMagang = Magang::query();

        if ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            if (!$prodiId) {
                return response()->json(['message' => 'Admin tidak memiliki prodi'], 403);
            }
            $queryMahasiswa->where('prodi_id', $prodiId);
            $queryMagang->whereHas('mahasiswa', fn($q) => $q->where('prodi_id', $prodiId));
        }

        $SemesterMagangTerbaru = (clone $queryMagang)->latest('semester_magang')->value('semester_magang');

        if ($SemesterMagangTerbaru) {
            $jumlahMagangAktif = (clone $queryMagang)->where('semester_magang', $SemesterMagangTerbaru)
                ->count();
        } else {
            $jumlahMagangAktif = 0;
        }

        $jumlahMahasiswa = $queryMahasiswa->count();
        $jumlahMitra = Mitra::count();
        $jumlahDosbing = Dosbing::count();

        return response()->json([
            'jumlah_mahasiswa_aktif_magang' => $jumlahMagangAktif,
            'jumlah_mahasiswa' => $jumlahMahasiswa,
            'jumlah_mitra' => $jumlahMitra,
            'jumlah_dosbing' => $jumlahDosbing,
        ], 200);
    }

    public function fetchDashboardDataMitra(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'mitra' || !$user->mitra) {
            return response()->json(['message' => 'Hanya mitra yang dapat mengakses dashboard mitra'], 403);
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
        if (!$user || !$user->mahasiswa) {
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
