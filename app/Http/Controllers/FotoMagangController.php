<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\FotoMagang;
use App\Models\Logbook;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

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

        $storedPath = null;
        try {
            $foto = DB::transaction(function () use ($request, &$storedPath) {
                $lockedLogbook = Logbook::whereKey($request->logbook_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedLogbook->fotoKegiatan()->count() >= 5) {
                    throw new \DomainException('Entri logbook ini sudah memiliki 5 foto.');
                }

                $path = $request->file('file')->store('foto_magang', 'public');
                if (!$path) {
                    throw new \RuntimeException('Foto gagal disimpan.');
                }
                $storedPath = $path;

                return FotoMagang::create([
                    'logbook_id' => $lockedLogbook->logbook_id,
                    'nama_file' => $request->file('file')->getClientOriginalName(),
                    'file_path' => $path,
                    'keterangan' => $request->keterangan,
                ]);
            });
        } catch (\DomainException $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            return response()->json([
                'message' => 'Maksimal 5 foto untuk setiap entri logbook.',
                'errors' => ['file' => [$exception->getMessage()]],
            ], 422);
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('public')->delete($storedPath);
            }
            report($exception);
            return response()->json([
                'message' => 'Gagal menyimpan foto kegiatan.',
            ], 500);
        }

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

        $oldPath = $foto->file_path;
        $newPath = null;

        if ($request->hasFile('file')) {
            $newPath = $request->file('file')->store('foto_magang', 'public');
            if (!$newPath) {
                return response()->json(['message' => 'Foto gagal disimpan.'], 500);
            }
        }

        try {
            DB::transaction(function () use ($foto, $request, $newPath) {
                $updates = [];
                if ($newPath) {
                    $updates += [
                        'nama_file' => $request->file('file')->getClientOriginalName(),
                        'file_path' => $newPath,
                        'uploaded_at' => now(),
                    ];
                }
                if ($request->has('keterangan')) {
                    $updates['keterangan'] = $request->keterangan;
                }
                if ($updates) {
                    $foto->update($updates);
                }
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newPath);
            report($exception);
            return response()->json(['message' => 'Gagal memperbarui foto kegiatan.'], 500);
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
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
