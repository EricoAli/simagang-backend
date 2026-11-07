<?php

namespace App\Http\Controllers;

use App\Models\DokumenMagang;
use App\Models\Magang;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

use Illuminate\Http\Request;

class DokumenMagangController extends Controller
{
    /**
     * Tampilkan daftar dokumen magang (dengan filter & pagination).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = DokumenMagang::with('magang:magang_id,mahasiswa_id,semester_magang');

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->whereHas('magang', function ($q) use ($mahasiswaId) {
                    $q->where('mahasiswa_id', $mahasiswaId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            if ($prodiId) {
                $query->whereHas('magang.mahasiswa', function ($q) use ($prodiId) {
                    $q->where('prodi_id', $prodiId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if ($mitraId) {
                $query->whereHas('magang', function ($q) use ($mitraId) {
                    $q->where('mitra_id', $mitraId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'dosbing') {
            return response()->json(['message' => 'Akses dokumen magang untuk dosen pembimbing belum didukung.'], 403);
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Filter berdasarkan magang_id
        if ($magangId = $request->query('magang_id')) {
            $query->where('magang_id', $magangId);
        }

        // Filter berdasarkan jenis dokumen
        if ($jenis = $request->query('jenis_dokumen')) {
            $query->where('jenis_dokumen', $jenis);
        }

        $dokumen = $query->orderByDesc('dokumen_id')->get();

        return response()->json($dokumen, 200);
    }

    /**
     * Tampilkan detail dokumen.
     */
    public function show(Request $request, $id)
    {
        $dokumen = DokumenMagang::with('magang.mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($dokumen->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($dokumen->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if (!$mitraId || $dokumen->magang->mitra_id !== $mitraId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari mitra anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($dokumen, 200);
    }

    /**
     * Simpan dokumen baru dengan upload file.
     */
    public function store(Request $request)
    {
        $rules = [
            'magang_id' => ['required', 'exists:magang,magang_id'],
            'jenis_dokumen' => ['required', 'string', 'max:100'],
            'file' => ['required', 'file', 'max:5120'], // max 5MB
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $magang = Magang::with('mahasiswa')->findOrFail($data['magang_id']);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Magang bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Magang bukan dari prodi anda.'], 403);
            }
        }

        // Upload file ke storage privat (local)
        $path = $request->file('file')->store('dokumen_magang', 'local');

        $dokumen = DokumenMagang::create([
            'magang_id' => $data['magang_id'],
            'jenis_dokumen' => $data['jenis_dokumen'],
            'nama_file' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'ukuran_file' => $request->file('file')->getSize(),
            'uploaded_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Dokumen berhasil diupload',
            'data' => $dokumen->load('magang'),
        ], 201);
    }

    /**
     * Update metadata dokumen (opsional ganti file).
     */
    public function update(Request $request, $id)
    {
        $dokumen = DokumenMagang::with('magang.mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($dokumen->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($dokumen->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari prodi anda.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $rules = [
            'jenis_dokumen' => ['nullable', 'string', 'max:100'],
            'file' => ['nullable', 'file', 'max:5120'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Jika ada file baru, hapus lama & upload baru
        if ($request->hasFile('file')) {
            if (!empty($dokumen->file_path)) {
                if (Storage::disk('local')->exists($dokumen->file_path)) {
                    Storage::disk('local')->delete($dokumen->file_path);
                } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                    Storage::disk('public')->delete($dokumen->file_path);
                }
            }

            $path = $request->file('file')->store('dokumen_magang', 'local');
            $dokumen->nama_file = $request->file('file')->getClientOriginalName();
            $dokumen->file_path = $path;
            $dokumen->ukuran_file = $request->file('file')->getSize();
        }

        $dokumen->jenis_dokumen = $data['jenis_dokumen'] ?? $dokumen->jenis_dokumen;
        $dokumen->updated_at = Carbon::now();

        $dokumen->save();

        return response()->json([
            'message' => 'Dokumen berhasil diperbarui',
            'data' => $dokumen->fresh()->load('magang'),
        ], 200);
    }

    /**
     * Hapus dokumen.
     */
    public function destroy(Request $request, $id)
    {
        $dokumen = DokumenMagang::with('magang.mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($dokumen->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($dokumen->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari prodi anda.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Hapus file fisik (cek disk local lalu fallback public)
        if (!empty($dokumen->file_path)) {
            if (Storage::disk('local')->exists($dokumen->file_path)) {
                Storage::disk('local')->delete($dokumen->file_path);
            } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            }
        }

        $dokumen->delete();

        return response()->json([
            'message' => 'Dokumen berhasil dihapus',
        ], 200);
    }

    public function getDocMagangByMagang(Request $request, $id)
    {
        $magang = Magang::with('mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if (!$mitraId || $magang->mitra_id !== $mitraId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $documents = DokumenMagang::where('magang_id', $magang->magang_id)->get();
        return response()->json(['dokumen' => $documents], 200);
    }

    /**
     * Unduh file fisik dokumen magang secara terotorisasi.
     */
    public function download(Request $request, $id)
    {
        $dokumen = DokumenMagang::with('magang.mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($dokumen->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($dokumen->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if (!$mitraId || $dokumen->magang->mitra_id !== $mitraId) {
                return response()->json(['message' => 'Unauthorized. Dokumen bukan dari mitra anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $disk = null;
        if (!empty($dokumen->file_path)) {
            if (Storage::disk('local')->exists($dokumen->file_path)) {
                $disk = 'local';
            } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                $disk = 'public';
            }
        }

        if (!$disk) {
            return response()->json(['message' => 'File fisik tidak ditemukan di storage.'], 404);
        }

        return Storage::disk($disk)->download($dokumen->file_path, $dokumen->nama_file);
    }
}
