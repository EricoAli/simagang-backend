<?php

namespace App\Http\Controllers;

use App\Models\FotoMagang;
use App\Models\Logbook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


use Illuminate\Http\Request;

class LogbookController extends Controller
{
    /**
     * Tampilkan daftar logbook (dengan filter dan pagination).
     */
    public function index(Request $request)
    {
        $query = Logbook::with(['fotoKegiatan', 'magang:magang_id,mahasiswa_id,semester_magang']);

        $user = $request->user();

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
            return response()->json(['message' => 'Akses logbook untuk dosen pembimbing belum didukung.'], 403);
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // 🔎 Filter berdasarkan magang_id (opsional)
        if ($magangId = $request->query('magang_id')) {
            $query->where('magang_id', $magangId);
        }

        $logbooks = $query->get();

        return response()->json($logbooks, 200);
    }

    /**
     * Tampilkan detail logbook berdasarkan id.
     */
    public function show(Request $request, $id)
    {
        try {
            $logbook = Logbook::with(['fotoKegiatan', 'magang.mahasiswa'])->where('logbook_id', $id)->first();

            if (!$logbook) {
                return response()->json(['message' => 'Logbook tidak ditemukan'], 404);
            }

            $user = $request->user();
            if ($user->role === 'mahasiswa') {
                if ($logbook->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                    return response()->json(['message' => 'Unauthorized. Logbook bukan milik anda.'], 403);
                }
            } elseif ($user->role === 'admin') {
                $adminProdiId = optional($user->admin)->prodi_id;
                if (optional($logbook->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Logbook bukan dari prodi anda.'], 403);
                }
            } elseif ($user->role === 'mitra') {
                if ($logbook->magang->mitra_id !== optional($user->mitra)->mitra_id) {
                    return response()->json(['message' => 'Unauthorized. Logbook bukan di mitra anda.'], 403);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $logbook
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logbook tidak ditemukan'
            ], 404);
        }
    }

    /**
     * Simpan logbook baru.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'magang_id' => 'required|exists:magang,magang_id',
            'kegiatan' => 'required|string',
            'foto' => 'required|array|min:1|max:5',
            'foto.*' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'keterangan.*' => 'nullable|string|max:255',
        ], [
            'foto.required' => 'Minimal harus upload 1 foto',
            'foto.min' => 'Minimal harus upload 1 foto',
            'foto.max' => 'Maksimal hanya bisa upload 5 foto',
            'foto.*.image' => 'File harus berupa gambar',
            'foto.*.mimes' => 'Format foto harus jpeg, png, atau jpg',
            'foto.*.max' => 'Ukuran foto maksimal 2MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        if ($user->role !== 'mahasiswa' || !$user->mahasiswa) {
            return response()->json(['message' => 'Hanya mahasiswa yang dapat mengisi logbook'], 403);
        }

        $magang = Magang::findOrFail($request->magang_id);
        if ($magang->mahasiswa_id !== $user->mahasiswa->mahasiswa_id) {
            return response()->json(['message' => 'Unauthorized. Magang bukan milik anda.'], 403);
        }

        DB::beginTransaction();

        try {
            $logbook = Logbook::create([
                'magang_id' => $request->magang_id,
                'kegiatan' => $request->kegiatan,
            ]);

            if ($request->has('foto')) {
                $fotos = $request->file('foto');

                foreach ($fotos as $index => $file) {
                    $namaFileAsli = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $namaFileTanpaExt = pathinfo($namaFileAsli, PATHINFO_FILENAME);
                    $fileName = $namaFileTanpaExt . "_" . time() . "_" . $index . "." . $extension;

                    $path = $file->storeAs("foto_kegiatan/{$request->magang_id}", $fileName, 'public');

                    FotoMagang::create([
                        'logbook_id' => $logbook->logbook_id,
                        'nama_file' => $namaFileAsli,
                        'file_path' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Logbook berhasil dibuat',
                'data' => $logbook->load(['magang', 'fotoKegiatan']),
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();

            if (isset($logbook)) {
                Storage::disk('public')->deleteDirectory("foto_kegiatan/{$request->magang_id}");
            }

            Log::error('LogbookController@store error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat menyimpan logbook.'
            ], 500);
        }

    }

    /**
     * Update logbook.
     */
    public function update(Request $request, $logbook_id)
    {
        $logbook = Logbook::with('magang.mahasiswa')->findOrFail($logbook_id);

        $user = $request->user();
        if ($user->role === 'mahasiswa') {
            if ($logbook->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Logbook bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($logbook->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Logbook bukan dari prodi anda.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan mengubah logbook.'], 403);
        }

        // Validasi
        $validator = Validator::make($request->all(), [
            'kegiatan' => 'required|string',
            'delete_foto_ids' => 'nullable|array',
            'delete_foto_ids.*' => 'exists:foto_kegiatan,foto_id',
            'foto' => 'nullable|array|max:5',
            'foto.*' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $magang_id = $logbook->magang_id;

            // Update kegiatan
            $logbook->update([
                'kegiatan' => $request->kegiatan,
            ]);

            // Hapus foto yang diminta untuk dihapus
            if ($request->has('delete_foto_ids')) {
                $fotosToDelete = FotoMagang::whereIn('foto_id', $request->delete_foto_ids)
                    ->where('logbook_id', $logbook_id)
                    ->get();

                foreach ($fotosToDelete as $foto) {
                    // Hapus file fisik
                    if (Storage::disk('public')->exists($foto->file_path)) {
                        Storage::disk('public')->delete($foto->file_path);
                    }
                }

                FotoMagang::whereIn('foto_id', $request->delete_foto_ids)
                    ->where('logbook_id', $logbook_id)
                    ->delete();
            }

            // Upload foto baru
            if ($request->hasFile('foto')) {
                // Cek total foto setelah update
                $currentPhotoCount = FotoMagang::where('logbook_id', $logbook_id)->count();
                $newPhotoCount = count($request->file('foto'));
                $totalPhotos = $currentPhotoCount + $newPhotoCount;

                if ($totalPhotos > 5) {
                    throw new \Exception('Total foto tidak boleh lebih dari 5');
                }

                if ($totalPhotos < 1) {
                    throw new \Exception('Minimal harus ada 1 foto');
                }

                $fotos = $request->file('foto');

                foreach ($fotos as $index => $file) {
                    $namaFileAsli = $file->getClientOriginalName();
                    $extension = $file->getClientOriginalExtension();
                    $namaFileTanpaExt = pathinfo($namaFileAsli, PATHINFO_FILENAME);
                    $fileName = $namaFileTanpaExt . "_" . time() . "_" . $index . "." . $extension;

                    $path = $file->storeAs(
                        "foto_kegiatan/{$magang_id}",
                        $fileName,
                        'public'
                    );

                    FotoMagang::create([
                        'logbook_id' => $logbook->logbook_id,
                        'nama_file' => $namaFileAsli,
                        'file_path' => $path,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Logbook berhasil diupdate',
                'data' => $logbook->load('fotoKegiatan')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('LogbookController@update error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat memperbarui logbook.'
            ], 500);
        }
    }

    /**
     * Hapus logbook.
     */
    public function destroy(Request $request, $logbook_id)
    {
        $logbook = Logbook::with('magang.mahasiswa')->findOrFail($logbook_id);

        $user = $request->user();
        if ($user->role === 'mahasiswa') {
            if ($logbook->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Logbook bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($logbook->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Logbook bukan dari prodi anda.'], 403);
            }
        } else {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan menghapus logbook.'], 403);
        }

        DB::beginTransaction();

        try {
            $fotoKegiatan = FotoMagang::where('logbook_id', $logbook_id)->get();

            foreach ($fotoKegiatan as $foto) {
                if (Storage::disk('public')->exists($foto->file_path)) {
                    Storage::disk('public')->delete($foto->file_path);
                }
            }

            FotoMagang::where('logbook_id', $logbook_id)->delete();

            $logbook->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Logbook dan foto kegiatan berhasil dihapus'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();

            Log::error('LogbookController@destroy error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server saat menghapus logbook.'
            ], 500);
        }
    }
}
