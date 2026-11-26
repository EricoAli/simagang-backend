<?php

namespace App\Http\Controllers;

use App\Models\Magang;
use App\Models\PeriodeMagang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PeriodeMagangController extends Controller
{
    /**
     * Tampilkan daftar master periode magang beserta relasi tahun ajaran.
     */
    public function index(Request $request)
    {
        $query = PeriodeMagang::query()
            ->with('tahunAjaran')
            ->leftJoin('tahun_ajaran', 'periode_magang.tahun_ajaran_id', '=', 'tahun_ajaran.tahun_ajaran_id')
            ->select('periode_magang.*');

        if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
            $query->where('periode_magang.tahun_ajaran_id', $tahunAjaranId);
        }

        if ($semester = $request->query('semester_magang')) {
            $query->where('periode_magang.semester_magang', (int) $semester);
        }

        if ($jenis = $request->query('jenis_semester')) {
            $normalizedJenis = PeriodeMagang::normalizeJenisSemester($jenis);
            if ($normalizedJenis) {
                $query->where('periode_magang.jenis_semester', $normalizedJenis);
            }
        }

        if ($request->has('is_active') && $request->query('is_active') !== '') {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('periode_magang.is_active', $isActive);
                if ($isActive) {
                    $query->where('tahun_ajaran.is_active', true);
                }
            }
        }

        $query->orderByDesc('tahun_ajaran.tahun_ajaran')
            ->orderByDesc('periode_magang.semester_magang')
            ->orderByDesc('periode_magang.periode_id');

        if ($request->boolean('all')) {
            return response()->json([
                'data' => $query->get(),
            ], 200);
        }

        $perPage = min(max((int) $request->query('per_page', 100), 1), 500);

        return response()->json($query->paginate($perPage), 200);
    }

    /**
     * Tampilkan detail periode magang.
     */
    public function show($id)
    {
        $periode = PeriodeMagang::with('tahunAjaran')->findOrFail($id);

        return response()->json([
            'data' => $periode,
        ], 200);
    }

    /**
     * Tambah master periode magang (khusus Super Admin).
     */
    public function store(Request $request)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengelola periode magang.'], 403);
        }

        $input = $request->all();
        if (isset($input['jenis_semester'])) {
            $input['jenis_semester'] = PeriodeMagang::normalizeJenisSemester((string) $input['jenis_semester']);
        }

        $validator = Validator::make($input, [
            'tahun_ajaran_id' => ['required', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            'semester_magang' => ['required', 'integer', 'min:1', 'max:14'],
            'jenis_semester' => ['required', Rule::in(['Gasal', 'Genap'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'tahun_ajaran_id.required' => 'Tahun ajaran wajib dipilih.',
            'tahun_ajaran_id.exists' => 'Tahun ajaran yang dipilih tidak ditemukan.',
            'semester_magang.required' => 'Semester magang wajib diisi.',
            'semester_magang.integer' => 'Semester magang harus berupa angka.',
            'semester_magang.min' => 'Semester magang minimal 1.',
            'jenis_semester.required' => 'Jenis semester wajib dipilih.',
            'jenis_semester.in' => 'Jenis semester harus Gasal atau Genap.',
        ]);

        $validator->after(function ($v) use ($input) {
            if ($v->errors()->any()) {
                return;
            }

            $semester = (int) $input['semester_magang'];
            $jenis = $input['jenis_semester'];
            $this->validateSemesterParity($v, $semester, $jenis);

            $exists = PeriodeMagang::where('tahun_ajaran_id', $input['tahun_ajaran_id'])
                ->where('semester_magang', $semester)
                ->where('jenis_semester', $jenis)
                ->exists();

            if ($exists) {
                $v->errors()->add('periode', 'Kombinasi tahun ajaran, semester magang, dan jenis semester tersebut sudah ada.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $periode = PeriodeMagang::create([
            'tahun_ajaran_id' => $validated['tahun_ajaran_id'],
            'semester_magang' => $validated['semester_magang'],
            'jenis_semester' => $validated['jenis_semester'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Periode magang berhasil ditambahkan.',
            'data' => $periode->fresh()->load('tahunAjaran'),
        ], 201);
    }

    /**
     * Perbarui periode magang atau toggle status aktif (khusus Super Admin).
     */
    public function update(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengelola periode magang.'], 403);
        }

        $periode = PeriodeMagang::findOrFail($id);

        $input = $request->all();
        if (array_key_exists('jenis_semester', $input) && $input['jenis_semester'] !== null) {
            $input['jenis_semester'] = PeriodeMagang::normalizeJenisSemester((string) $input['jenis_semester']);
        }

        $validator = Validator::make($input, [
            'tahun_ajaran_id' => ['sometimes', 'required', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            'semester_magang' => ['sometimes', 'required', 'integer', 'min:1', 'max:14'],
            'jenis_semester' => ['sometimes', 'required', Rule::in(['Gasal', 'Genap'])],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'tahun_ajaran_id.exists' => 'Tahun ajaran yang dipilih tidak ditemukan.',
            'semester_magang.integer' => 'Semester magang harus berupa angka.',
            'semester_magang.min' => 'Semester magang minimal 1.',
            'jenis_semester.in' => 'Jenis semester harus Gasal atau Genap.',
        ]);

        $validator->after(function ($v) use ($input, $periode) {
            if ($v->errors()->any()) {
                return;
            }

            $targetTahunAjaranId = (int) ($input['tahun_ajaran_id'] ?? $periode->tahun_ajaran_id);
            $targetSemester = (int) ($input['semester_magang'] ?? $periode->semester_magang);
            $targetJenis = $input['jenis_semester'] ?? $periode->jenis_semester;

            $this->validateSemesterParity($v, $targetSemester, $targetJenis);

            $exists = PeriodeMagang::where('tahun_ajaran_id', $targetTahunAjaranId)
                ->where('semester_magang', $targetSemester)
                ->where('jenis_semester', $targetJenis)
                ->where('periode_id', '!=', $periode->periode_id)
                ->exists();

            if ($exists) {
                $v->errors()->add('periode', 'Kombinasi tahun ajaran, semester magang, dan jenis semester tersebut sudah ada.');
            }
        });

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
                'message' => 'Tidak ada perubahan data periode yang dikirim.',
            ], 422);
        }

        DB::transaction(function () use ($periode, $validated) {
            $periode->update($validated);
            $periode->load('tahunAjaran');

            if (array_intersect(['tahun_ajaran_id', 'semester_magang', 'jenis_semester'], array_keys($validated))) {
                Magang::where('periode_id', $periode->periode_id)->update([
                    'tahun_ajaran_id' => $periode->tahun_ajaran_id,
                    'tahun_ajaran' => $periode->tahunAjaran?->tahun_ajaran,
                    'semester_magang' => $periode->semester_magang,
                    'jenis_semester' => $periode->jenis_semester,
                ]);
            }
        });

        return response()->json([
            'message' => 'Periode magang berhasil diperbarui.',
            'data' => $periode->fresh()->load('tahunAjaran'),
        ], 200);
    }

    /**
     * Toggle status aktif/nonaktif periode magang.
     */
    public function toggleStatus(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat mengubah status periode magang.'], 403);
        }

        $periode = PeriodeMagang::findOrFail($id);

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
        $periode->update(['is_active' => $isActive]);

        return response()->json([
            'message' => $isActive ? 'Periode magang berhasil diaktifkan.' : 'Periode magang berhasil dinonaktifkan.',
            'data' => $periode->fresh()->load('tahunAjaran'),
        ], 200);
    }

    /**
     * Hapus periode magang jika belum dipakai data magang.
     */
    public function destroy(Request $request, $id)
    {
        if ($request->user()?->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya Super Admin yang dapat menghapus periode magang.'], 403);
        }

        $periode = PeriodeMagang::withCount('magang')->findOrFail($id);

        if ($periode->magang_count > 0) {
            return response()->json([
                'message' => 'Periode magang tidak dapat dihapus karena masih digunakan pada data magang.',
            ], 409);
        }

        $periode->delete();

        return response()->json([
            'message' => 'Periode magang berhasil dihapus.',
        ], 200);
    }

    private function validateSemesterParity($validator, int $semester, string $jenis): void
    {
        if ($jenis === 'Gasal' && $semester % 2 === 0) {
            $validator->errors()->add('semester_magang', 'Semester Gasal harus menggunakan nomor semester ganjil.');
        }

        if ($jenis === 'Genap' && $semester % 2 !== 0) {
            $validator->errors()->add('semester_magang', 'Semester Genap harus menggunakan nomor semester genap.');
        }
    }
}
