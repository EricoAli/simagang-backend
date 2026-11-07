<?php

namespace App\Http\Controllers;

use App\Models\Laporan;
use App\Models\Magang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Laporan::with('magang.mahasiswa');

        if ($user->role === 'mahasiswa') {
            $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswa_id) {
                $query->whereHas('magang', function ($q) use ($mahasiswa_id) {
                    $q->where('mahasiswa_id', $mahasiswa_id);
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
        }

        $laporan = $query->get();

        return response()->json($laporan);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'mahasiswa' || !$user->mahasiswa) {
            return response()->json(['message' => 'Hanya mahasiswa yang dapat mengunggah laporan magang'], 403);
        }

        $rules = [
            'magang_id' => 'required|exists:magang,magang_id',
            'file' => 'required|file|mimes:pdf|max:5048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $magang = Magang::findOrFail($request->magang_id);
        if ($magang->mahasiswa_id !== $user->mahasiswa->mahasiswa_id) {
            return response()->json(['message' => 'Unauthorized. Magang bukan milik anda.'], 403);
        }

        $magang_id = $request->magang_id;
        $file = $request->file('file');
        $namaFile = $file->getClientOriginalName();
        $extFile = $file->getClientOriginalExtension();
        $base = pathinfo($namaFile, PATHINFO_FILENAME);
        $fileName = $base . "_" . time() . "." . $extFile;
        $path = $file->storeAs("laporan-magang/{$magang_id}", $fileName, 'local');

        $laporan = Laporan::create([
            'magang_id' => $magang_id,
            'file_path' => $path,
            'nama_file' => $namaFile,
        ]);

        return response()->json([
            'message' => 'Laporan magang berhasil diunggah',
            'data' => $laporan
        ], 201);
    }

    /**
     * Display the specified resource.
     * Mendukung pencarian berdasarkan laporan_id maupun magang_id untuk menjaga backwards compatibility.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $query = Laporan::with('magang.mahasiswa')
            ->where(function ($q) use ($id) {
                $q->where('laporan_id', $id)
                  ->orWhere('magang_id', $id);
            });

        if ($user->role === 'mahasiswa') {
            $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;
            $query->whereHas('magang', fn($q) => $q->where('mahasiswa_id', $mahasiswa_id));
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            $query->whereHas('magang.mahasiswa', fn($q) => $q->where('prodi_id', $prodiId));
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $laporan = $query->first();

        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        return response()->json($laporan);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        if ($user->role !== 'mahasiswa' || !$user->mahasiswa) {
            return response()->json(['message' => 'Unauthorized. Hanya mahasiswa yang dapat memperbarui laporan.'], 403);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5048',
        ]);

        $mahasiswa_id = $user->mahasiswa->mahasiswa_id;

        $laporan = Laporan::whereHas('magang', function ($q) use ($mahasiswa_id) {
            $q->where('mahasiswa_id', $mahasiswa_id);
        })->where('laporan_id', $id)->first();

        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        // hapus file lama (cek disk local lalu public untuk legacy)
        if (!empty($laporan->file_path)) {
            if (Storage::disk('local')->exists($laporan->file_path)) {
                Storage::disk('local')->delete($laporan->file_path);
            } elseif (Storage::disk('public')->exists($laporan->file_path)) {
                Storage::disk('public')->delete($laporan->file_path);
            }
        }

        $file = $request->file('file');
        $namaFile = $file->getClientOriginalName();
        $extFile = $file->getClientOriginalExtension();
        $base = pathinfo($namaFile, PATHINFO_FILENAME);
        $fileName = $base . "_" . time() . "." . $extFile;
        $path = $file->storeAs("laporan-magang/{$laporan->magang_id}", $fileName, 'local');

        $laporan->update([
            'file_path' => $path,
            'nama_file' => $fileName
        ]);

        return response()->json([
            'message' => 'Laporan magang berhasil diperbarui',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $query = Laporan::with('magang.mahasiswa')->where('laporan_id', $id);

        if ($user->role === 'mahasiswa') {
            $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;
            $query->whereHas('magang', fn($q) => $q->where('mahasiswa_id', $mahasiswa_id));
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            $query->whereHas('magang.mahasiswa', fn($q) => $q->where('prodi_id', $prodiId));
        } else {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $laporan = $query->first();
        if (!$laporan) {
            return response()->json(['message' => 'Laporan tidak ditemukan'], 404);
        }

        // Hapus file fisik (cek local lalu public)
        if (!empty($laporan->file_path)) {
            if (Storage::disk('local')->exists($laporan->file_path)) {
                Storage::disk('local')->delete($laporan->file_path);
            } elseif (Storage::disk('public')->exists($laporan->file_path)) {
                Storage::disk('public')->delete($laporan->file_path);
            }
        }

        $laporan->delete();

        return response()->json([
            'message' => 'Laporan magang berhasil dihapus'
        ]);
    }

    /**
     * Unduh file fisik laporan magang secara terotorisasi.
     */
    public function download(Request $request, $id)
    {
        $laporan = Laporan::with('magang.mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($laporan->magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Laporan bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($laporan->magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Laporan bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $disk = null;
        if (!empty($laporan->file_path)) {
            if (Storage::disk('local')->exists($laporan->file_path)) {
                $disk = 'local';
            } elseif (Storage::disk('public')->exists($laporan->file_path)) {
                $disk = 'public';
            }
        }

        if (!$disk) {
            return response()->json(['message' => 'File fisik tidak ditemukan di storage.'], 404);
        }

        return Storage::disk($disk)->download($laporan->file_path, $laporan->nama_file);
    }
}
