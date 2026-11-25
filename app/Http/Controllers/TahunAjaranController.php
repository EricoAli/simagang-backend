<?php

namespace App\Http\Controllers;

use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahunAjaranController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = TahunAjaran::query()->orderByDesc('tahun_ajaran');
        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'tahun_ajaran' => [
                'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('tahun_ajaran', 'tahun_ajaran'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (!$this->hasConsecutiveYears($data['tahun_ajaran'])) {
            return response()->json([
                'message' => 'Tahun ajaran harus menggunakan dua tahun yang berurutan, misalnya 2026/2027.',
                'errors' => ['tahun_ajaran' => ['Tahun ajaran harus berurutan.']],
            ], 422);
        }

        $tahunAjaran = TahunAjaran::create($data + ['is_active' => true]);

        return response()->json([
            'message' => 'Tahun ajaran berhasil ditambahkan.',
            'data' => $tahunAjaran->fresh(),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $tahunAjaran = TahunAjaran::findOrFail($id);

        if (!$request->hasAny(['tahun_ajaran', 'is_active'])) {
            return response()->json([
                'message' => 'Isi minimal satu perubahan.',
            ], 422);
        }

        $data = $request->validate([
            'tahun_ajaran' => [
                'sometimes', 'required', 'string', 'regex:/^\d{4}\/\d{4}$/',
                Rule::unique('tahun_ajaran', 'tahun_ajaran')->ignore($id, 'tahun_ajaran_id'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['tahun_ajaran']) && !$this->hasConsecutiveYears($data['tahun_ajaran'])) {
            return response()->json([
                'message' => 'Tahun ajaran harus menggunakan dua tahun yang berurutan, misalnya 2026/2027.',
                'errors' => ['tahun_ajaran' => ['Tahun ajaran harus berurutan.']],
            ], 422);
        }

        $tahunAjaran->update($data);

        return response()->json([
            'message' => 'Tahun ajaran berhasil diperbarui.',
            'data' => $tahunAjaran->fresh(),
        ], 200);
    }

    private function hasConsecutiveYears(string $value): bool
    {
        [$start, $end] = array_map('intval', explode('/', $value));

        return $end === $start + 1;
    }
}
