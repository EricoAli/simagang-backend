<?php

namespace App\Http\Controllers;

use App\Models\PeriodeMagang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PeriodeMagangController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'tahun_ajaran_id' => ['sometimes', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            'is_active' => ['sometimes', 'boolean'],
            'available_only' => ['sometimes', 'boolean'],
        ]);

        $query = PeriodeMagang::with('tahunAjaran')
            ->orderByDesc('tahun_ajaran_id')
            ->orderBy('semester_magang');

        if (isset($filters['tahun_ajaran_id'])) {
            $query->where('tahun_ajaran_id', $filters['tahun_ajaran_id']);
        }

        if (array_key_exists('is_active', $filters)) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['available_only'])) {
            $query->where('is_active', true)
                ->whereHas('tahunAjaran', fn ($yearQuery) => $yearQuery->where('is_active', true));
        }

        return response()->json(['data' => $query->get()], 200);
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $period = PeriodeMagang::create($data + ['is_active' => true]);

        return response()->json([
            'message' => 'Periode magang berhasil ditambahkan.',
            'data' => $period->load('tahunAjaran'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $period = PeriodeMagang::findOrFail($id);

        if (!$request->hasAny(['tahun_ajaran_id', 'semester_magang', 'jenis_semester', 'is_active'])) {
            return response()->json(['message' => 'Isi minimal satu perubahan.'], 422);
        }

        $data = $this->validatedData($request, $period);
        if ($data instanceof \Illuminate\Http\JsonResponse) {
            return $data;
        }

        $period->update($data);

        return response()->json([
            'message' => 'Periode magang berhasil diperbarui.',
            'data' => $period->fresh()->load('tahunAjaran'),
        ], 200);
    }

    private function validatedData(Request $request, ?PeriodeMagang $current = null)
    {
        $isUpdate = $current !== null;
        $data = $request->validate([
            'tahun_ajaran_id' => [
                $isUpdate ? 'sometimes' : 'required', 'required_with:semester_magang,jenis_semester',
                'integer', 'exists:tahun_ajaran,tahun_ajaran_id',
            ],
            'semester_magang' => [$isUpdate ? 'sometimes' : 'required', 'required_with:tahun_ajaran_id,jenis_semester', 'integer', 'min:1'],
            'jenis_semester' => [$isUpdate ? 'sometimes' : 'required', 'required_with:tahun_ajaran_id,semester_magang', Rule::in(['Gasal', 'Genap'])],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $tahunAjaranId = (int) ($data['tahun_ajaran_id'] ?? $current?->tahun_ajaran_id);
        $semester = (int) ($data['semester_magang'] ?? $current?->semester_magang);
        $type = $data['jenis_semester'] ?? $current?->jenis_semester;
        $expectedType = $semester % 2 === 0 ? 'Genap' : 'Gasal';

        if ($type !== $expectedType) {
            return response()->json([
                'message' => 'Jenis semester tidak sesuai dengan nomor semester.',
                'errors' => ['jenis_semester' => ["Semester {$semester} harus berjenis {$expectedType}."]],
            ], 422);
        }

        $duplicate = PeriodeMagang::query()
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('semester_magang', $semester)
            ->when($current, fn ($query) => $query->where('periode_id', '!=', $current->periode_id))
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'Semester tersebut sudah terdaftar pada tahun ajaran ini.',
                'errors' => ['semester_magang' => ['Periode yang sama tidak boleh didaftarkan dua kali.']],
            ], 422);
        }

        return $data;
    }
}
