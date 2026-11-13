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

        // 🔐 Filter otomatis untuk role mahasiswa
        if ($user && $user->mahasiswa) {
            // Jika user adalah mahasiswa, filter berdasarkan mahasiswa_id mereka
            $mahasiswaId = $user->mahasiswa->mahasiswa_id;

            $query->whereHas('magang', function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId);
            });
        }
        // Jika bukan mahasiswa (admin, pembimbing, dll), tampilkan semua dokumen

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
    public function show($id)
    {
        $dokumen = DokumenMagang::with('magang')->findOrFail($id);

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

        // Upload file
        $path = $request->file('file')->store('dokumen_magang', 'public');

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
        $dokumen = DokumenMagang::findOrFail($id);

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
            if (!empty($dokumen->file_path) && Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            }

            $path = $request->file('file')->store('dokumen_magang', 'public');
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
    public function destroy($id)
    {
        $dokumen = DokumenMagang::findOrFail($id);

        // Hapus file fisik
        if (!empty($dokumen->file_path) && Storage::disk('public')->exists($dokumen->file_path)) {
            Storage::disk('public')->delete($dokumen->file_path);
        }

        $dokumen->delete();

        return response()->json([
            'message' => 'Dokumen berhasil dihapus',
        ], 200);
    }

    public function getDocMagangByMagang(Request $request, $id)
    {
        // $user = $request->user();
        // $mahasiswa = $user->mahasiswa;
        $magang = Magang::findOrFail($id);

        // if ($user->role === 'mahasiswa' && $mahasiswa->mahasiswa_id !== $magang->mahasiswa_id) {
        //     abort(403, 'Access denied.');
        // }

        $documents = DokumenMagang::where('magang_id', $magang->magang_id)->get();
        return response()->json(['dokumen' => $documents], 200);


    }
}
