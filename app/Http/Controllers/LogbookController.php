<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\FotoMagang;
use App\Models\Logbook;
use App\Models\Magang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LogbookController extends Controller
{
    use AuthorizesMagangAccess;

    public function index(Request $request)
    {
        $query = Logbook::with([
                'fotoKegiatan',
                'magang:magang_id,mahasiswa_id,semester_magang,tahun_ajaran,periode_id',
                'magang.periode.tahunAjaran',
            ])
            ->orderByRaw('tanggal_kegiatan IS NULL')
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('logbook_id');

        $this->scopeByMagangAccess($query, $request->user(), 'magang');

        if ($magangId = $request->query('magang_id')) {
            $query->where('magang_id', $magangId);
        }

        return response()->json($query->get(), 200);
    }

    public function show(Request $request, $id)
    {
        $logbook = Logbook::with(['fotoKegiatan', 'magang'])->find($id);
        if (!$logbook) {
            return response()->json(['message' => 'Logbook tidak ditemukan'], 404);
        }

        $this->authorizeMagangAccess($request->user(), $logbook->magang);

        return response()->json(['success' => true, 'data' => $logbook], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'magang_id' => ['required', 'exists:magang,magang_id'],
            'tanggal_kegiatan' => ['required', 'date'],
            'kegiatan' => ['required', 'string', 'max:10000'],
            'foto' => ['required', 'array', 'min:1', 'max:5'],
            'foto.*' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'keterangan.*' => ['nullable', 'string', 'max:255'],
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

        $magang = Magang::findOrFail($request->magang_id);
        $this->authorizeMagangAccess($request->user(), $magang);

        $storedPaths = [];
        try {
            $logbook = DB::transaction(function () use ($request, $magang, &$storedPaths) {
                $logbook = Logbook::create([
                    'magang_id' => $magang->magang_id,
                    'tanggal_kegiatan' => $request->tanggal_kegiatan,
                    'kegiatan' => trim($request->kegiatan),
                ]);

                foreach ($request->file('foto', []) as $file) {
                    $path = $file->store("foto_kegiatan/{$magang->magang_id}", 'public');
                    if (!$path) {
                        throw new \RuntimeException('Foto gagal disimpan.');
                    }
                    $storedPaths[] = $path;

                    FotoMagang::create([
                        'logbook_id' => $logbook->logbook_id,
                        'nama_file' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);
                }

                return $logbook;
            });

            return response()->json([
                'message' => 'Logbook berhasil dibuat',
                'data' => $logbook->load(['magang', 'fotoKegiatan']),
            ], 201);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan logbook.',
            ], 500);
        }
    }

    public function update(Request $request, $logbookId)
    {
        $validator = Validator::make($request->all(), [
            'tanggal_kegiatan' => ['required', 'date'],
            'kegiatan' => ['required', 'string', 'max:10000'],
            'delete_foto_ids' => ['nullable', 'array'],
            'delete_foto_ids.*' => ['integer', 'distinct', 'exists:foto_kegiatan,foto_id'],
            'foto' => ['nullable', 'array', 'max:5'],
            'foto.*' => ['required', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $logbook = Logbook::with('magang')->findOrFail($logbookId);
        $this->authorizeMagangAccess($request->user(), $logbook->magang);

        $deleteIds = $request->input('delete_foto_ids', []);
        $photosToDelete = FotoMagang::where('logbook_id', $logbookId)
            ->whereIn('foto_id', $deleteIds)
            ->get();
        $currentCount = $logbook->fotoKegiatan()->count();
        $newFiles = $request->file('foto', []);
        $resultingCount = $currentCount - $photosToDelete->count() + count($newFiles);

        if ($resultingCount < 1 || $resultingCount > 5) {
            return response()->json([
                'success' => false,
                'message' => $resultingCount < 1
                    ? 'Logbook harus memiliki minimal 1 foto.'
                    : 'Total foto tidak boleh lebih dari 5.',
            ], 422);
        }

        $storedPaths = [];
        try {
            DB::transaction(function () use ($request, $logbook, $photosToDelete, $newFiles, &$storedPaths) {
                $logbook->update([
                    'tanggal_kegiatan' => $request->tanggal_kegiatan,
                    'kegiatan' => trim($request->kegiatan),
                ]);

                foreach ($photosToDelete as $foto) {
                    $foto->delete();
                }

                foreach ($newFiles as $file) {
                    $path = $file->store("foto_kegiatan/{$logbook->magang_id}", 'public');
                    if (!$path) {
                        throw new \RuntimeException('Foto gagal disimpan.');
                    }
                    $storedPaths[] = $path;

                    FotoMagang::create([
                        'logbook_id' => $logbook->logbook_id,
                        'nama_file' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);
                }
            });

            Storage::disk('public')->delete($photosToDelete->pluck('file_path')->all());

            return response()->json([
                'success' => true,
                'message' => 'Logbook berhasil diperbarui',
                'data' => $logbook->fresh()->load('fotoKegiatan'),
            ], 200);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui logbook.',
            ], 500);
        }
    }

    public function destroy(Request $request, $logbookId)
    {
        $logbook = Logbook::with(['magang', 'fotoKegiatan'])->findOrFail($logbookId);
        $this->authorizeMagangAccess($request->user(), $logbook->magang);
        $paths = $logbook->fotoKegiatan->pluck('file_path')->all();

        try {
            DB::transaction(function () use ($logbook) {
                $logbook->fotoKegiatan()->delete();
                $logbook->delete();
            });
            Storage::disk('public')->delete($paths);

            return response()->json([
                'success' => true,
                'message' => 'Logbook dan foto kegiatan berhasil dihapus',
            ], 200);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus logbook.',
            ], 500);
        }
    }
}
