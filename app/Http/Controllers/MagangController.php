<?php

namespace App\Http\Controllers;

use App\Exports\MagangExport;
use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\NilaiMitra;
use App\Models\PeriodeMagang;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class MagangController extends Controller
{
    /**
     * Tampilkan daftar magang (dengan pagination, filter, dan pencarian).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'masterTahunAjaran',
            'penilaianMitra',
        ]);

        // Filter otomatis sesuai role
        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->where('mahasiswa_id', $mahasiswaId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            if ($prodiId) {
                $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                    $q->where('prodi_id', $prodiId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if ($mitraId) {
                $query->where('mitra_id', $mitraId);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'dosbing') {
            $query->whereRaw('1 = 0');
        }

        // Filter opsional
        if ($mahasiswaId = $request->query('mahasiswa_id')) {
            $query->where('mahasiswa_id', $mahasiswaId);
        }

        if ($mitraId = $request->query('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($dosbingId = $request->query('dosbing_id')) {
            $query->where('dosbing_id', $dosbingId);
        }

        if ($periodeId = $request->query('periode_id')) {
            $query->where('periode_id', $periodeId);
        }

        if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($tahunAjaran = trim((string) $request->query('tahun_ajaran', ''))) {
            $query->where('tahun_ajaran', 'like', "{$tahunAjaran}%");
        }

        if ($jenisSemester = $request->query('jenis_semester')) {
            $normalizedJenis = PeriodeMagang::normalizeJenisSemester((string) $jenisSemester);
            if ($normalizedJenis) {
                $query->where('jenis_semester', $normalizedJenis);
            }
        }

        if ($angkatan = $request->query('angkatan')) {
            $query->whereHas('mahasiswa', function ($q) use ($angkatan) {
                $q->where('angkatan', $angkatan);
            });
        }

        if ($semesterMagang = $request->query('semester_magang')) {
            $query->where('semester_magang', (int) $semesterMagang);
        }

        if ($statusFilter = $request->query('status_magang')) {
            $normalizedStatus = Magang::normalizeStatus((string) $statusFilter);
            if ($normalizedStatus) {
                $query->where('status_magang', $normalizedStatus);
            }
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('jobdesk', 'like', "%{$search}%")
                    ->orWhere('role_magang', 'like', "%{$search}%")
                    ->orWhereHas('mahasiswa', function ($mq) use ($search) {
                        $mq->where('nama', 'like', "%{$search}%")
                            ->orWhere('nim', 'like', "%{$search}%");
                    })
                    ->orWhereHas('mitra', function ($mtq) use ($search) {
                        $mtq->where('nama_mitra', 'like', "%{$search}%");
                    });
            });
        }

        // Filter status penilaian (nilai mitra)
        if ($penilaian = $request->query('penilaian')) {
            $mitraIdToken = optional($user->mitra)->mitra_id;

            if ($penilaian === 'none') {
                $query->whereDoesntHave('penilaianMitra');
                if ($mitraIdToken) {
                    $query->where('mitra_id', $mitraIdToken);
                }
            } elseif ($penilaian === 'exist') {
                $query->whereHas('penilaianMitra');
                if ($mitraIdToken) {
                    $query->where('mitra_id', $mitraIdToken);
                }
            }
        }

        $query->orderByDesc('magang_id');

        $perPage = min(max((int) $request->query('per_page', 10), 1), 500);
        $magang = $query->paginate($perPage);

        $magang->getCollection()->transform(function (Magang $item) use ($user) {
            $item->setAttribute('can_delete', $this->canDeleteMagang($user, $item));

            return $item;
        });

        return response()->json($magang, 200);
    }

    /**
     * Tampilkan detail magang berdasarkan id (magang_id).
     */
    public function show(Request $request, $id)
    {
        $magang = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'masterTahunAjaran',
        ])->findOrFail($id);

        if ($denied = $this->authorizeAccess($request->user(), $magang)) {
            return $denied;
        }

        $magang->setAttribute('can_delete', $this->canDeleteMagang($request->user(), $magang));

        return response()->json($magang, 200);
    }

    /**
     * Simpan magang baru (Mahasiswa mendaftar sendiri, atau Admin/Super Admin membuat penempatan).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isMahasiswa = $user->role === 'mahasiswa';
        $isAdminOrSuper = in_array($user->role, ['admin', 'super_admin'], true);

        if ($isMahasiswa) {
            if (! $user->mahasiswa) {
                return response()->json(['message' => 'Profil mahasiswa tidak ditemukan.'], 403);
            }
            $mahasiswaId = $user->mahasiswa->mahasiswa_id;
        } elseif ($isAdminOrSuper) {
            $mahasiswaId = (int) $request->input('mahasiswa_id');
        } else {
            return response()->json(['message' => 'Role anda tidak diizinkan mendaftarkan magang.'], 403);
        }

        $input = $request->all();
        if (isset($input['status_magang']) || isset($input['status'])) {
            $input['status_magang'] = Magang::normalizeStatus((string) ($input['status_magang'] ?? $input['status']));
        }
        if (isset($input['jenis_semester'])) {
            $input['jenis_semester'] = PeriodeMagang::normalizeJenisSemester((string) $input['jenis_semester']);
        }

        $rules = [
            'mahasiswa_id' => $isAdminOrSuper
                ? ['required', 'integer', 'exists:mahasiswa,mahasiswa_id']
                : ['nullable'],
            'mitra_id' => ['nullable', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable', 'exists:dosen_pembimbing,dosbing_id'],
            'periode_id' => ['nullable', 'integer', 'exists:periode_magang,periode_id'],
            'tahun_ajaran_id' => ['nullable', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            'tahun_ajaran' => ['nullable', 'string', 'max:20'],
            'jenis_semester' => ['nullable', Rule::in(['Gasal', 'Genap'])],
            'semester_magang' => ['required_without:periode_id', 'integer', 'min:1', 'max:14'],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'status_magang' => $isAdminOrSuper
                ? ['nullable', Rule::in(['draft', 'berlangsung', 'selesai', 'ditolak'])]
                : ['nullable'],
            'dokumenPenerimaan' => $isMahasiswa
                ? ['required', 'file', 'mimes:pdf', 'max:5120']
                : ['nullable', 'file', 'mimes:pdf', 'max:5120'],
            'dokumenPraKRS' => ['nullable', 'file', 'mimes:pdf', 'max:5120'],
        ];

        $messages = [
            'mahasiswa_id.required' => 'Mahasiswa wajib dipilih.',
            'mahasiswa_id.exists' => 'Data mahasiswa tidak ditemukan.',
            'periode_id.exists' => 'Periode magang yang dipilih tidak ditemukan.',
            'semester_magang.required_without' => 'Periode atau semester magang wajib diisi.',
            'semester_magang.min' => 'Semester magang minimal 1.',
            'status_magang.in' => 'Status magang tidak valid.',
            'dokumenPenerimaan.required' => 'Dokumen surat penerimaan magang wajib diunggah.',
            'dokumenPenerimaan.mimes' => 'Dokumen surat penerimaan harus berformat PDF.',
            'dokumenPraKRS.mimes' => 'Dokumen Pra-KRS harus berformat PDF.',
        ];

        $validator = Validator::make($input, $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $targetMahasiswa = Mahasiswa::findOrFail($isMahasiswa ? $mahasiswaId : $data['mahasiswa_id']);

        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($targetMahasiswa->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }
        }

        // Cek duplikat pendaftaran periode (mengecualikan status ditolak)
        if ($dupError = $this->checkDuplicatePeriod($targetMahasiswa->mahasiswa_id, $data)) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => $dupError,
                'errors' => ['periode_id' => [$dupError]],
            ], 422);
        }

        $data['mahasiswa_id'] = $targetMahasiswa->mahasiswa_id;
        // Status magang selalu dimulai dari 'draft' (Belum Mulai) saat didaftarkan mahasiswa
        $data['status_magang'] = $isMahasiswa ? 'draft' : ($data['status_magang'] ?? 'draft');

        $storedPaths = [];
        try {
            $magang = DB::transaction(function () use ($request, $data, $targetMahasiswa, &$storedPaths) {
                $magangData = collect($data)->except(['dokumenPenerimaan', 'dokumenPraKRS'])->toArray();
                $magang = Magang::create($magangData);

                if ($request->hasFile('dokumenPenerimaan')) {
                    $file = $request->file('dokumenPenerimaan');
                    $stored = $this->storePdfDocument($file, 'dokumen-penerimaan/'.$targetMahasiswa->mahasiswa_id);
                    $storedPaths[] = $stored['file_path'];

                    DokumenMagang::create([
                        'magang_id' => $magang->magang_id,
                        'jenis_dokumen' => 'doc_surat_penerimaan',
                        'file_path' => $stored['file_path'],
                        'nama_file' => $stored['nama_file'],
                        'ukuran_file' => $stored['ukuran_file'],
                    ]);
                }

                if ($request->hasFile('dokumenPraKRS')) {
                    $file = $request->file('dokumenPraKRS');
                    $stored = $this->storePdfDocument($file, 'dokumen-pra-krs/'.$targetMahasiswa->mahasiswa_id);
                    $storedPaths[] = $stored['file_path'];

                    DokumenMagang::create([
                        'magang_id' => $magang->magang_id,
                        'jenis_dokumen' => 'doc_pra_krs',
                        'file_path' => $stored['file_path'],
                        'nama_file' => $stored['nama_file'],
                        'ukuran_file' => $stored['ukuran_file'],
                    ]);
                }

                return $magang;
            });

            $magang->load(['mahasiswa.prodi', 'mitra', 'dosenPembimbing', 'periode.tahunAjaran', 'masterTahunAjaran']);
            $magang->setAttribute('can_delete', $this->canDeleteMagang($user, $magang));

            return response()->json([
                'message' => 'Data magang berhasil dibuat',
                'data' => $magang,
            ], 201);
        } catch (\Throwable $th) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            Log::error('MagangController@store error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menyimpan data magang.',
            ], 500);
        }
    }

    /**
     * Update data magang.
     */
    public function update(Request $request, $id)
    {
        $magang = Magang::with(['dokumenMagang', 'mahasiswa'])->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Data magang bukan milik anda.'], 403);
            }
            if ($request->has('status_magang') || $request->has('status')) {
                return response()->json([
                    'message' => 'Status magang hanya dapat dikelola oleh Admin.',
                ], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Magang bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan mengubah data magang.'], 403);
        }

        // Mahasiswa tidak dapat mengubah mitra atau periode setelah magang dikonfirmasi/berlangsung/selesai
        if ($user->role === 'mahasiswa' && in_array($magang->status_magang, ['berlangsung', 'selesai'], true)) {
            $lockedFields = ['mitra_id', 'periode_id', 'tahun_ajaran_id', 'tahun_ajaran', 'semester_magang', 'jenis_semester'];
            foreach ($lockedFields as $field) {
                if ($request->exists($field) && (string) $request->input($field) !== (string) $magang->{$field}) {
                    return response()->json([
                        'message' => 'Magang yang sedang berlangsung atau selesai tidak dapat diubah mitra atau periodenya.',
                    ], 422);
                }
            }
        }

        $input = $request->all();
        if ($request->has('status_magang') || $request->has('status')) {
            $input['status_magang'] = Magang::normalizeStatus((string) ($request->input('status_magang') ?? $request->input('status')));
        }
        if ($request->has('jenis_semester') && $request->input('jenis_semester') !== null) {
            $input['jenis_semester'] = PeriodeMagang::normalizeJenisSemester((string) $request->input('jenis_semester'));
        }

        $rules = [
            'mahasiswa_id' => ['sometimes', 'exists:mahasiswa,mahasiswa_id'],
            'mitra_id' => ['nullable', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable', 'exists:dosen_pembimbing,dosbing_id'],
            'periode_id' => ['nullable', 'integer', 'exists:periode_magang,periode_id'],
            'tahun_ajaran_id' => ['nullable', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            'tahun_ajaran' => ['sometimes', 'nullable', 'string', 'max:20'],
            'jenis_semester' => ['nullable', Rule::in(['Gasal', 'Genap'])],
            'semester_magang' => ['sometimes', 'integer', 'min:1', 'max:14'],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'status_magang' => ['sometimes', Rule::in(['draft', 'berlangsung', 'selesai', 'ditolak'])],
            'dokumenPenerimaan' => 'sometimes|file|mimes:pdf|max:5120',
            'dokumenPraKRS' => 'sometimes|file|mimes:pdf|max:5120',
        ];

        $validator = Validator::make($input, $rules, [
            'status_magang.in' => 'Status magang tidak valid.',
            'periode_id.exists' => 'Periode magang tidak ditemukan.',
            'semester_magang.min' => 'Semester magang minimal 1.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['mahasiswa_id']) && $user->role === 'admin') {
            $targetMhs = Mahasiswa::findOrFail($data['mahasiswa_id']);
            if ($targetMhs->prodi_id !== optional($user->admin)->prodi_id) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }
        }

        // Cek duplikat periode jika periode/semester diubah (mengecualikan baris ini & ditolak)
        $targetStatusAfterUpdate = $data['status_magang'] ?? $magang->status_magang;
        if ($targetStatusAfterUpdate !== 'ditolak' && array_intersect(['periode_id', 'semester_magang', 'tahun_ajaran_id', 'tahun_ajaran', 'jenis_semester'], array_keys($data))) {
            $effectiveData = [
                'periode_id' => $data['periode_id'] ?? $magang->periode_id,
                'semester_magang' => $data['semester_magang'] ?? $magang->semester_magang,
                'tahun_ajaran_id' => $data['tahun_ajaran_id'] ?? $magang->tahun_ajaran_id,
                'tahun_ajaran' => $data['tahun_ajaran'] ?? $magang->tahun_ajaran,
                'jenis_semester' => $data['jenis_semester'] ?? $magang->jenis_semester,
            ];
            if ($dupError = $this->checkDuplicatePeriod($magang->mahasiswa_id, $effectiveData, $magang->magang_id)) {
                return response()->json([
                    'title' => 'Validasi Gagal',
                    'message' => $dupError,
                    'errors' => ['periode_id' => [$dupError]],
                ], 422);
            }
        }

        $mahasiswaId = $magang->mahasiswa_id;
        $magang->update(collect($data)->except(['dokumenPenerimaan', 'dokumenPraKRS'])->toArray());

        if ($request->hasFile('dokumenPenerimaan')) {
            $this->replaceMagangDocument($magang, $request->file('dokumenPenerimaan'), 'doc_surat_penerimaan', 'dokumen-penerimaan/'.$mahasiswaId);
        }

        if ($request->hasFile('dokumenPraKRS')) {
            $this->replaceMagangDocument($magang, $request->file('dokumenPraKRS'), 'doc_pra_krs', 'dokumen-pra-krs/'.$mahasiswaId);
        }

        $fresh = $magang->fresh()->load([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'masterTahunAjaran',
            'dokumenMagang',
        ]);
        $fresh->setAttribute('can_delete', $this->canDeleteMagang($user, $fresh));

        return response()->json([
            'message' => 'Data magang berhasil diperbarui',
            'data' => $fresh,
        ], 200);
    }

    /**
     * Hapus data magang dengan aturan perlindungan berjenjang (Prioritas 5).
     */
    public function destroy(Request $request, $id)
    {
        $magang = Magang::with('mahasiswa')->findOrFail($id);
        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Data magang bukan milik anda.'], 403);
            }

            if (in_array($magang->status_magang, ['berlangsung', 'selesai'], true)) {
                return response()->json([
                    'message' => 'Magang yang sudah dikonfirmasi (Sedang Magang atau Selesai) tidak dapat dibatalkan atau dihapus.',
                ], 422);
            }

            if ($magang->penilaianMitra()->exists() || $magang->laporan()->exists() || $magang->jadwalPresentasi()->exists() || $magang->jadwalPresentasiPeserta()->exists()) {
                return response()->json([
                    'message' => 'Data magang tidak dapat dihapus karena sudah memiliki laporan, penilaian mitra, atau jadwal presentasi.',
                ], 422);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Magang bukan dari prodi anda.'], 403);
            }

            if (in_array($magang->status_magang, ['berlangsung', 'selesai'], true)) {
                return response()->json([
                    'message' => 'Data magang berstatus Sedang Magang atau Selesai tidak dapat dihapus. Ubah status ke Ditolak atau edit data bila diperlukan.',
                ], 422);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan menghapus data magang.'], 403);
        }

        try {
            DB::transaction(function () use ($magang) {
                $magang->delete();
            });

            return response()->json([
                'message' => 'Data magang berhasil dihapus',
            ], 200);
        } catch (\Throwable $th) {
            Log::error('MagangController@destroy error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menghapus data magang.',
            ], 500);
        }
    }

    public function showDetail(Request $request, $id)
    {
        $magang = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'masterTahunAjaran',
        ])->findOrFail($id);

        if ($denied = $this->authorizeAccess($request->user(), $magang)) {
            return $denied;
        }

        $magang->setAttribute('can_delete', $this->canDeleteMagang($request->user(), $magang));

        // Logbook
        $logbook = Logbook::with('fotoKegiatan')
            ->where('magang_id', $id)
            ->orderByDesc('logbook_id')
            ->get();

        // Penilaian mitra
        $penilaian = NilaiMitra::where('magang_id', $id)->first();

        // Dokumen magang (kembalikan semua tipe agar kompatibel dengan tipe dokumen BE 2)
        $dokumen = DokumenMagang::where('magang_id', $id)
            ->orderByDesc('dokumen_id')
            ->get();

        // Laporan magang
        $laporan = Laporan::where('magang_id', $id)->get();

        // Jadwal presentasi (ambil dari relasi langsung maupun pivot peserta multi-mahasiswa)
        $legacyJadwals = $magang->jadwalPresentasi()->with('penguji')->get();
        $pivotJadwals = $magang->jadwalPresentasiPeserta()->with('penguji')->get();
        $allJadwals = $legacyJadwals
            ->merge($pivotJadwals)
            ->unique('jadwal_id')
            ->sortByDesc('jadwal_id')
            ->values();

        $jadwalPresentasi = $allJadwals->first();

        return response()->json([
            'detail' => $magang,
            'logbook' => $logbook,
            'penilaian' => $penilaian,
            'dokumen' => $dokumen,
            'laporan' => $laporan,
            'jadwal_presentasi' => $jadwalPresentasi,
            'jadwal_presentasi_list' => $allJadwals,
        ], 200);
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();
        $angkatan = $request->query('angkatan');
        $semesterMagang = $request->query('semester_magang');
        $tahunAjaranId = $request->query('tahun_ajaran_id');
        $periodeId = $request->query('periode_id');
        $tahunAjaran = $request->query('tahun_ajaran');
        $jenisSemester = $request->query('jenis_semester');
        $dosbingId = $request->query('dosbing_id');
        $statusMagang = $request->query('status_magang');
        $prodiId = null;
        $mitraId = null;

        if ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if (! $mitraId) {
                return response()->json(['message' => 'Unauthorized. Profil mitra tidak ditemukan.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            if (! $prodiId) {
                return response()->json(['message' => 'Unauthorized. Admin tidak memiliki prodi.'], 403);
            }
            if ($request->has('mitra_id')) {
                $mitraId = $request->query('mitra_id');
            }
        } elseif ($user->role === 'super_admin') {
            if ($request->has('prodi_id')) {
                $prodiId = $request->query('prodi_id');
            }
            if ($request->has('mitra_id')) {
                $mitraId = $request->query('mitra_id');
            }
        } else {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $safePart = fn ($val, $fallback = 'all') => preg_replace('/[^A-Za-z0-9._-]+/', '-', str_replace(['/', '\\'], '-', (string) ($val ?: $fallback))) ?: $fallback;
        $yearPart = $safePart($tahunAjaran ?: ($tahunAjaranId ? 'ta-'.$tahunAjaranId : ($angkatan ?: 'all')));
        $periodPart = $safePart($periodeId ? 'p-'.$periodeId : ($semesterMagang ? 'sem-'.$semesterMagang : 'all'));
        $fileName = 'data-magang-'.$yearPart.'-'.$periodPart.'-'.Carbon::now()->format('ymdHi').'.xlsx';

        return Excel::download(
            new MagangExport(
                $angkatan,
                $semesterMagang,
                $prodiId,
                $mitraId,
                $tahunAjaranId,
                $periodeId,
                $tahunAjaran,
                $jenisSemester,
                $dosbingId,
                $statusMagang
            ),
            $fileName
        );
    }

    private function authorizeAccess(User $user, Magang $magang)
    {
        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return response()->json(['message' => 'Unauthorized. Data magang bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Magang bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role === 'mitra') {
            if ($magang->mitra_id !== optional($user->mitra)->mitra_id) {
                return response()->json(['message' => 'Unauthorized. Magang bukan di mitra anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return null;
    }

    private function canDeleteMagang(User $user, Magang $magang): bool
    {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role === 'admin') {
            if (optional($magang->mahasiswa)->prodi_id !== optional($user->admin)->prodi_id) {
                return false;
            }

            return ! in_array($magang->status_magang, ['berlangsung', 'selesai'], true);
        }

        if ($user->role === 'mahasiswa') {
            if ($magang->mahasiswa_id !== optional($user->mahasiswa)->mahasiswa_id) {
                return false;
            }

            return ! in_array($magang->status_magang, ['berlangsung', 'selesai'], true);
        }

        return false;
    }

    private function checkDuplicatePeriod(int $mahasiswaId, array $data, ?int $ignoreMagangId = null): ?string
    {
        $query = Magang::where('mahasiswa_id', $mahasiswaId)
            ->where('status_magang', '!=', 'ditolak');

        if ($ignoreMagangId) {
            $query->where('magang_id', '!=', $ignoreMagangId);
        }

        if (! empty($data['periode_id'])) {
            $periode = PeriodeMagang::with('tahunAjaran')->find($data['periode_id']);
            if ($periode) {
                $yearLabel = $periode->tahunAjaran?->tahun_ajaran;
                $startYear = $yearLabel && preg_match('/^(\d{4})/', $yearLabel, $m) ? $m[1] : null;

                $exists = $query->where(function ($q) use ($periode, $yearLabel, $startYear) {
                    $q->where('periode_id', $periode->periode_id)
                        ->orWhere(function ($legacy) use ($periode, $yearLabel, $startYear) {
                            $legacy->whereNull('periode_id')
                                ->where('semester_magang', $periode->semester_magang);
                            if ($yearLabel || $startYear) {
                                $legacy->where(function ($yq) use ($yearLabel, $startYear) {
                                    if ($yearLabel) {
                                        $yq->where('tahun_ajaran', $yearLabel);
                                    }
                                    if ($startYear) {
                                        $yq->orWhere('tahun_ajaran', $startYear);
                                    }
                                });
                            }
                        });
                })->exists();

                if ($exists) {
                    return "Magang untuk periode {$periode->label} sudah pernah didaftarkan.";
                }
            }

            return null;
        }

        if (! empty($data['semester_magang'])) {
            $semester = (int) $data['semester_magang'];
            $query->where('semester_magang', $semester);

            if (! empty($data['tahun_ajaran_id'])) {
                $query->where('tahun_ajaran_id', $data['tahun_ajaran_id']);
            } elseif (! empty($data['tahun_ajaran'])) {
                $year = trim((string) $data['tahun_ajaran']);
                $startYear = preg_match('/^(\d{4})/', $year, $m) ? $m[1] : $year;
                $query->where(function ($yq) use ($year, $startYear) {
                    $yq->where('tahun_ajaran', $year)
                        ->orWhere('tahun_ajaran', $startYear)
                        ->orWhere('tahun_ajaran', 'like', "{$startYear}/%");
                });
            }

            if (! empty($data['jenis_semester'])) {
                $query->where('jenis_semester', $data['jenis_semester']);
            }

            if ($query->exists()) {
                return "Magang untuk semester {$semester} pada periode tersebut sudah pernah didaftarkan.";
            }
        }

        return null;
    }

    private function storePdfDocument($file, string $directory): array
    {
        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $fileName = $base.'_'.time().'_'.mt_rand(100, 999).'.'.$ext;
        $path = $file->storeAs($directory, $fileName, 'public');

        return [
            'file_path' => $path,
            'nama_file' => $originalName,
            'ukuran_file' => $file->getSize(),
        ];
    }

    private function replaceMagangDocument(Magang $magang, $file, string $jenisDokumen, string $directory): void
    {
        $stored = $this->storePdfDocument($file, $directory);
        $dokumen = $magang->dokumenMagang()->where('jenis_dokumen', $jenisDokumen)->first();

        if ($dokumen && ! empty($dokumen->file_path)) {
            if (Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            } elseif (Storage::disk('local')->exists($dokumen->file_path)) {
                Storage::disk('local')->delete($dokumen->file_path);
            }
        }

        $magang->dokumenMagang()->updateOrCreate(
            ['jenis_dokumen' => $jenisDokumen],
            $stored
        );
    }
}
