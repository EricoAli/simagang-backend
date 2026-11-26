<?php

namespace App\Http\Controllers;

use App\Models\Magang;
use App\Models\NilaiMitra;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NilaiMitraController extends Controller
{
    /**
     * File penilaian (MVP): hanya PDF, maksimal 5 MB.
     * `mimes` & `mimetypes` diperiksa dari isi file, bukan dari ekstensi nama file klien.
     */
    private const FILE_RULES = ['file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'];

    /**
     * Tampilkan daftar penilaian mitra (dengan filter opsional).
     */
    public function index(Request $request)
    {
        $query = NilaiMitra::with([
            'magang:mitra_id,magang_id,mahasiswa_id,periode_id,tahun_ajaran_id,tahun_ajaran,semester_magang,jenis_semester,status_magang',
            'magang.mahasiswa:mahasiswa_id,nama,nim,prodi_id',
            'magang.periode.tahunAjaran',
        ]);

        // Batasi data sesuai role sebelum filter query dari klien diterapkan.
        $user = $request->user();
        if ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if ($mitraId) {
                $query->whereHas('magang', function ($q) use ($mitraId) {
                    $q->where('mitra_id', $mitraId);
                });
            } else {
                // Tanpa profil mitra: where('mitra_id', null) akan menjadi IS NULL, jadi kosongkan hasil.
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            $query->whereHas('magang.mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', $prodiId);
            });
        } elseif ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            $query->whereHas('magang', function ($q) use ($mahasiswaId) {
                $q->where('mahasiswa_id', $mahasiswaId);
            });
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Filter by magang_id
        if ($request->query('magang_id')) {
            $query->where('magang_id', $request->query('magang_id'));
        }

        // Filter periode / tahun ajaran / semester
        if ($periodeId = $request->query('periode_id')) {
            $query->whereHas('magang', fn ($q) => $q->where('periode_id', $periodeId));
        }

        if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
            $query->whereHas('magang', fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId));
        }

        if ($tahunAjaran = trim((string) $request->query('tahun_ajaran', ''))) {
            $query->whereHas('magang', fn ($q) => $q->where('tahun_ajaran', 'like', "{$tahunAjaran}%"));
        }

        if ($semesterMagang = $request->query('semester_magang')) {
            $query->whereHas('magang', fn ($q) => $q->where('semester_magang', (int) $semesterMagang));
        }

        // Filter by minimum nilai_teknis
        if ($request->query('min_teknis')) {
            $query->where('nilai_teknis', '>=', $request->query('min_teknis'));
        }

        // Filter by maximum nilai_teknis
        if ($request->query('max_teknis')) {
            $query->where('nilai_teknis', '<=', $request->query('max_teknis'));
        }

        // Filter by Mitra
        if ($request->query('mitra_id')) {
            $query->whereHas('magang', function ($q) use ($request) {
                $q->where('mitra_id', $request->query('mitra_id'));
            });
        }

        // Filter by range profesionalisme
        if ($request->query('range_profesional')) {
            $parts = explode('-', (string) $request->query('range_profesional'));
            if (count($parts) === 2) {
                $query->whereBetween('nilai_profesionalisme_etika', [(int) $parts[0], (int) $parts[1]]);
            }
        }

        // Filter by keyword (cari di keterangan, nama/NIM mahasiswa)
        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('magang.mahasiswa', function ($mq) use ($search) {
                        $mq->where('nama', 'like', "%{$search}%")
                            ->orWhere('nim', 'like', "%{$search}%");
                    });
            });
        }

        // Sorting aman (whitelist)
        $allowedSort = [
            'penilaian_id',
            'magang_id',
            'nilai_teknis',
            'nilai_profesionalisme_etika',
            'nilai_komunikasi_presentasi',
            'nilai_proyek_pengalaman_industri',
            'created_at',
        ];
        $sortBy = in_array($request->query('sort_by'), $allowedSort, true)
            ? $request->query('sort_by')
            : 'penilaian_id';
        $sortOrder = strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min(max((int) $request->query('per_page', 15), 1), 500);
        $data = $query->paginate($perPage);

        return response()->json($data, 200);
    }

    /**
     * Simpan penilaian mitra baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!in_array($user->role, ['mitra', 'admin'])) {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan membuat penilaian.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'magang_id' => ['required', 'exists:magang,magang_id'],
            'nilai_teknis' => ['required', 'numeric', 'min:0', 'max:100'],
            'nilai_profesionalisme_etika' => ['required', 'numeric', 'min:0', 'max:100'],
            'nilai_komunikasi_presentasi' => ['required', 'numeric', 'min:0', 'max:100'],
            'nilai_proyek_pengalaman_industri' => ['required', 'numeric', 'min:0', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'supervisor' => ['required', 'string'],
            'jabatan_supervisor' => ['required', 'string'],
            'file' => array_merge(['required'], self::FILE_RULES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $magang_id = $request->magang_id;

        $magang = Magang::with('mahasiswa')->findOrFail($magang_id);
        if ($denied = $this->authorizeMagang($user, $magang, true)) {
            return $denied;
        }

        // penilaian_mitra.magang_id unique: satu penilaian per magang
        if (NilaiMitra::where('magang_id', $magang_id)->exists()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => ['magang_id' => ['Penilaian untuk magang ini sudah ada.']],
            ], 422);
        }

        $file = $request->file('file');
        $path = $this->storeFile($file, $magang_id);
        if (!$path) {
            return response()->json(['message' => 'Gagal menyimpan file penilaian.'], 500);
        }

        $data = $validator->validated();
        $data['file_path'] = $path;
        $data['nama_file'] = $file->getClientOriginalName();

        try {
            $nilaiMitra = NilaiMitra::create($data);
        } catch (\Throwable $th) {
            // Jangan tinggalkan file tanpa referensi database.
            Storage::disk('public')->delete($path);
            Log::error('NilaiMitraController@store error: ' . $th->getMessage(), ['exception' => $th]);

            // Kemungkinan request ganda yang lolos pengecekan duplikat di atas.
            if (NilaiMitra::where('magang_id', $magang_id)->exists()) {
                return response()->json([
                    'message' => 'Validasi gagal',
                    'errors' => ['magang_id' => ['Penilaian untuk magang ini sudah ada.']],
                ], 422);
            }

            return response()->json(['message' => 'Terjadi kesalahan pada server saat menyimpan penilaian mitra.'], 500);
        }

        return response()->json([
            'message' => 'Penilaian mitra berhasil dibuat',
            'data' => $nilaiMitra->load('magang:magang_id,mahasiswa_id', 'magang.mahasiswa:mahasiswa_id,nama'),

        ], 201);
    }

    /**
     * Tampilkan detail penilaian mitra.
     */
    public function show(Request $request, $id)
    {
        $nilaiMitra = NilaiMitra::with('magang.mahasiswa')->findOrFail($id);

        if ($denied = $this->authorizeMagang($request->user(), $nilaiMitra->magang, false)) {
            return $denied;
        }

        return response()->json($nilaiMitra, 200);
    }

    /**
     * Update penilaian mitra.
     */
    public function update(Request $request, $id)
    {
        $nilaiMitra = NilaiMitra::with('magang.mahasiswa')->findOrFail($id);

        if ($denied = $this->authorizeMagang($request->user(), $nilaiMitra->magang, true)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'nilai_teknis' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_profesionalisme_etika' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_komunikasi_presentasi' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'nilai_proyek_pengalaman_industri' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'supervisor' => ['sometimes', 'string', 'max:255'],
            'jabatan_supervisor' => ['sometimes', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string'],
            'file' => array_merge(['sometimes'], self::FILE_RULES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $magang_id = $nilaiMitra->magang_id;
        $oldPath = $nilaiMitra->file_path;
        $newPath = null;

        // 🔁 Kalau file di-update: simpan file baru dulu, file lama baru dihapus setelah DB berhasil diperbarui.
        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $newPath = $this->storeFile($file, $magang_id);
            if (!$newPath) {
                return response()->json(['message' => 'Gagal menyimpan file penilaian.'], 500);
            }

            $data['file_path'] = $newPath;
            $data['nama_file'] = $file->getClientOriginalName();
        }

        try {
            $updated = $nilaiMitra->update($data);
        } catch (\Throwable $th) {
            Log::error('NilaiMitraController@update error: ' . $th->getMessage(), ['exception' => $th]);
            $updated = false;
        }

        if (!$updated) {
            // File lama tetap utuh; buang file baru yang tidak jadi direferensikan.
            if ($newPath) {
                Storage::disk('public')->delete($newPath);
            }
            return response()->json(['message' => 'Terjadi kesalahan pada server saat memperbarui penilaian mitra.'], 500);
        }

        if ($newPath && $oldPath && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json([
            'message' => 'Penilaian mitra berhasil diperbarui',
            'data' => $nilaiMitra->fresh()->load('magang'),
        ], 200);
    }


    /**
     * Hapus penilaian mitra.
     */
    public function destroy(Request $request, $id)
    {
        $nilaiMitra = NilaiMitra::with('magang.mahasiswa')->findOrFail($id);

        if ($denied = $this->authorizeMagang($request->user(), $nilaiMitra->magang, true)) {
            return $denied;
        }
        // Hapus data dulu; file hanya dihapus jika data berhasil dihapus.
        $filePath = $nilaiMitra->file_path;
        $nilaiMitra->delete();

        if ($filePath) {
            Storage::disk('public')->delete($filePath);
        }

        return response()->json([
            'message' => 'Penilaian mitra berhasil dihapus'
        ], 200);
    }

    /**
     * Simpan file penilaian ke disk public (kontrak URL /storage/... dipakai MagangExport).
     * Ekstensi selalu .pdf (sudah divalidasi dari isi file); sufiks acak mencegah file lama tertimpa.
     * Mengembalikan path, atau false jika gagal.
     */
    private function storeFile(UploadedFile $file, $magangId)
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $fileName = $base . '_' . time() . '_' . Str::lower(Str::random(6)) . '.pdf';

        return $file->storeAs("dokumen-penilaian/{$magangId}", $fileName, 'public');
    }

    /**
     * Cek hak akses terhadap magang yang dinilai.
     * - mitra: hanya magang di mitra miliknya
     * - admin: hanya magang mahasiswa prodinya
     * - mahasiswa: hanya magang miliknya, dan hanya baca
     * - role lain: ditolak
     *
     * Mengembalikan response 403 jika ditolak, null jika diizinkan.
     */
    private function authorizeMagang(User $user, ?Magang $magang, bool $write)
    {
        $allowed = false;

        if ($magang) {
            if ($user->role === 'mitra') {
                $mitraId = optional($user->mitra)->mitra_id;
                $allowed = $mitraId && $magang->mitra_id === $mitraId;
            } elseif ($user->role === 'admin') {
                $prodiId = optional($user->admin)->prodi_id;
                $allowed = $prodiId && optional($magang->mahasiswa)->prodi_id === $prodiId;
            } elseif ($user->role === 'mahasiswa' && !$write) {
                $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
                $allowed = $mahasiswaId && $magang->mahasiswa_id === $mahasiswaId;
            }
        }

        if (!$allowed) {
            return response()->json(['message' => 'Unauthorized. Penilaian bukan milik anda.'], 403);
        }

        return null;
    }
}
