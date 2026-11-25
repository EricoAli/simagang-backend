<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\FotoMagang;
use App\Models\Logbook;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

use Illuminate\Http\Request;

class FotoMagangController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Tampilkan daftar foto kegiatan
     */
    public function index(Request $request)
    {
        $query = FotoMagang::with('logbook');

        // 🔐 Batasi data sesuai prodi Admin / kepemilikan mahasiswa / mitra.
        $this->scopeByMagangAccess($query, $request->user(), 'logbook.magang');

        // Filter berdasarkan logbook_id
        if ($logbookId = $request->query('logbook_id')) {
            $query->where('logbook_id', $logbookId);
        }

        // $perPage = (int) $request->query('per_page', 15);
        // $fotos = $query->latest('uploaded_at')->paginate($perPage);
        $fotos = $query->latest('uploaded_at')->get();


        return response()->json($fotos, 200);
    }

    /**
     * Detail foto kegiatan
     */
    public function show(Request $request, $id)
    {
        $foto = FotoMagang::with('logbook.magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), optional($foto->logbook)->magang);

        return response()->json($foto, 200);
    }

    /**
     * Upload foto kegiatan baru
     */
    public function store(Request $request)
    {
        $rules = [
            'logbook_id' => ['required', 'exists:logbook,logbook_id'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:2048'], // max 2MB
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $logbook = Logbook::with('magang')->findOrFail($request->logbook_id);

        $this->authorizeMagangAccess($request->user(), $logbook->magang);

        // Simpan file
        $path = $request->file('file')->store('foto_magang', 'public');

        $foto = FotoMagang::create([
            'logbook_id' => $request->logbook_id,
            'nama_file' => $request->file('file')->getClientOriginalName(),
            'file_path' => $path,
            'keterangan' => $request->keterangan,
        ]);

        return response()->json([
            'message' => 'Foto kegiatan berhasil diupload',
            'data' => $foto->load('logbook'),
        ], 201);
    }

    /**
     * Update keterangan atau ganti file foto
     */
    public function update(Request $request, $id)
    {
        $foto = FotoMagang::with('logbook.magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), optional($foto->logbook)->magang);

        $rules = [
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png', 'max:2048'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Jika ada file baru, hapus file lama lalu simpan yang baru
        if ($request->hasFile('file')) {
            if ($foto->file_path && Storage::disk('public')->exists($foto->file_path)) {
                Storage::disk('public')->delete($foto->file_path);
            }

            $path = $request->file('file')->store('foto_magang', 'public');

            $foto->update([
                'nama_file' => $request->file('file')->getClientOriginalName(),
                'path_file' => $path,
                'uploaded_at' => now(),
            ]);
        }

        if ($request->has('keterangan')) {
            $foto->update(['keterangan' => $request->keterangan]);
        }

        return response()->json([
            'message' => 'Foto kegiatan berhasil diperbarui',
            'data' => $foto->fresh()->load('logbook'),
        ], 200);
    }

    /**
     * Hapus foto kegiatan
     */
    public function destroy(Request $request, $id)
    {
        $foto = FotoMagang::with('logbook.magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), optional($foto->logbook)->magang);

        // Hapus file fisik
        if ($foto->file_path && Storage::disk('public')->exists($foto->file_path)) {
            Storage::disk('public')->delete($foto->file_path);
        }

        $foto->delete();

        return response()->json([
            'message' => 'Foto kegiatan berhasil dihapus',
        ], 200);
    }
}
