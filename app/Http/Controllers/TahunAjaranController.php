<?php

namespace App\Http\Controllers;

use App\Models\Magang;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TahunAjaranController extends Controller
{
    /**
     * Tampilkan daftar tahun ajaran.
     */
    public function index(Request $request)
    {
        $query = TahunAjaran::query();

        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('tahun_ajaran', 'like', "%{$search}%");
        }

        $query->orderByDesc('tahun_ajaran');

        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->get(),
            ], 200);
        }

        $perPage = min(max((int) $request->query('per_page', 100), 1), 500);

        return response()->json($query->paginate($perPage), 200);
    }

    /**
     * Tampilkan detail tahun ajaran beserta periode terkait.
     */
    public function show($id)
    {
        $tahunAjaran = TahunAjaran::with('periodeMagang')->findOrFail($id);

        return response()->json([
            'data' => $tahunAjaran,
        ], 200);
    }

    /**
     * Tambah master tahun ajaran baru (khusus Super Admin).
     */
    public function store(Request $request)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengelola tahun ajaran.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
                'regex:/^\d{4}\/\d{4}$/',
                'unique:tahun_ajaran,tahun_ajaran',
                function ($attribute, $value, $fail) {
                    $this->validateConsecutiveYears($value, $fail);
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh 2026/2027.',
            'tahun_ajaran.unique' => 'Tahun ajaran tersebut sudah terdaftar.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $tahunAjaran = TahunAjaran::create([
            'tahun_ajaran' => $data['tahun_ajaran'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Tahun ajaran berhasil ditambahkan.',
            'data' => $tahunAjaran->fresh(),
        ], 201);
    }

    /**
     * Perbarui tahun ajaran atau toggle status aktif (khusus Super Admin).
     */
    public function update(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengelola tahun ajaran.'], 403);
        }

        $tahunAjaran = TahunAjaran::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'tahun_ajaran' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('tahun_ajaran', 'tahun_ajaran')->ignore($tahunAjaran->tahun_ajaran_id, 'tahun_ajaran_id'),
                function ($attribute, $value, $fail) {
                    $this->validateConsecutiveYears($value, $fail);
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY, contoh 2026/2027.',
            'tahun_ajaran.unique' => 'Tahun ajaran tersebut sudah terdaftar.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (empty($validated)) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => 'Tidak ada perubahan data tahun ajaran yang dikirim.',
            ], 422);
        }

        DB::transaction(function () use ($tahunAjaran, $validated) {
            $labelChanged = array_key_exists('tahun_ajaran', $validated)
                && $validated['tahun_ajaran'] !== $tahunAjaran->tahun_ajaran;

            $tahunAjaran->update($validated);

            if ($labelChanged) {
                Magang::where('tahun_ajaran_id', $tahunAjaran->tahun_ajaran_id)
                    ->update(['tahun_ajaran' => $tahunAjaran->tahun_ajaran]);
            }
        });

        return response()->json([
            'message' => 'Tahun ajaran berhasil diperbarui.',
            'data' => $tahunAjaran->fresh(),
        ], 200);
    }

    /**
     * Toggle status aktif/nonaktif tahun ajaran.
     */
    public function toggleStatus(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah status tahun ajaran.'], 403);
        }

        $tahunAjaran = TahunAjaran::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ], [
            'is_active.required' => 'Parameter is_active wajib disertakan.',
            'is_active.boolean' => 'Parameter is_active harus berupa boolean.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $isActive = $request->boolean('is_active');
        $tahunAjaran->update(['is_active' => $isActive]);

        return response()->json([
            'message' => $isActive ? 'Tahun ajaran berhasil diaktifkan.' : 'Tahun ajaran berhasil dinonaktifkan.',
            'data' => $tahunAjaran->fresh(),
        ], 200);
    }

    /**
     * Hapus tahun ajaran jika tidak lagi direferensikan.
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat menghapus tahun ajaran.'], 403);
        }

        $tahunAjaran = TahunAjaran::withCount(['periodeMagang', 'magang', 'mahasiswa'])->findOrFail($id);

        if ($tahunAjaran->periode_magang_count > 0 || $tahunAjaran->magang_count > 0 || $tahunAjaran->mahasiswa_count > 0) {
            return response()->json([
                'message' => 'Tahun ajaran tidak dapat dihapus karena masih digunakan pada data periode, mahasiswa, atau magang.',
            ], 409);
        }

        $tahunAjaran->delete();

        return response()->json([
            'message' => 'Tahun ajaran berhasil dihapus.',
        ], 200);
    }

    private function validateConsecutiveYears(string $value, callable $fail): void
    {
        if (preg_match('/^(\d{4})\/(\d{4})$/', $value, $matches)) {
            if ((int) $matches[2] !== (int) $matches[1] + 1) {
                $fail('Tahun ajaran harus menggunakan dua tahun berurutan, contoh 2026/2027.');
            }
        }
    }
}
