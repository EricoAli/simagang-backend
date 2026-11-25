<?php

namespace App\Http\Controllers;
use App\Exports\MagangExport;
use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\JadwalPresentasi;
use App\Models\NilaiMitra;
use App\Models\PeriodeMagang;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;


use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MagangController extends Controller
{
    use AuthorizesMagangAccess;

    private function denyOutsideProdi()
    {
        return response()->json([
            'message' => 'Unauthorized. Data magang bukan dari prodi anda.'
        ], 403);
    }

    /**
     * Tampilkan daftar magang (dengan pagination, filter, dan pencarian).
     */
    public function index(Request $request)
    {
        $user = $request->user(); // ambil user dari token

        $query = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
        ]);

        $requestedProdiId = null;
        if ($request->filled('prodi_id')) {
            $prodiValidator = Validator::make($request->only('prodi_id'), [
                'prodi_id' => ['integer', 'exists:prodi,prodi_id'],
            ]);

            if ($prodiValidator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $prodiValidator->errors(),
                ], 422);
            }

            $requestedProdiId = (int) $request->query('prodi_id');
        }

        // 🔐 Batasi data sesuai prodi Admin / kepemilikan mahasiswa / mitra.
        $this->scopeByMagangAccess($query, $user, null);

        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($requestedProdiId !== null && (int) $adminProdiId !== $requestedProdiId) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat melihat data program studinya.',
                ], 403);
            }
        }

        if ($requestedProdiId !== null) {
            $query->whereHas('mahasiswa', function ($q) use ($requestedProdiId) {
                $q->where('prodi_id', $requestedProdiId);
            });
        }

        // Filter manual tambahan (tidak dapat memperluas data yang sudah dibatasi).
        if ($mahasiswaId = $request->query('mahasiswa_id')) {
            $query->where('mahasiswa_id', $mahasiswaId);
        }

        if ($mitraId = $request->query('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($angkatan = $request->query('angkatan')) {
            $query->whereHas('mahasiswa', function ($q) use ($angkatan) {
                $q->where('angkatan', $angkatan);
            });
        }

        if ($semester_magang = $request->query('semester_magang')) {
            $query->where('semester_magang', $semester_magang);
        }

        if ($request->filled('periode_id')) {
            $periodeValidator = Validator::make($request->only('periode_id'), [
                'periode_id' => ['integer', 'exists:periode_magang,periode_id'],
            ], [
                'periode_id.exists' => 'Periode magang tidak ditemukan.',
                'periode_id.integer' => 'ID periode magang harus berupa angka.',
            ]);

            if ($periodeValidator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $periodeValidator->errors(),
                ], 422);
            }

            $query->where('periode_id', (int) $request->query('periode_id'));
        }

        if ($request->filled('tahun_ajaran_id')) {
            $tahunValidator = Validator::make($request->only('tahun_ajaran_id'), [
                'tahun_ajaran_id' => ['integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
            ], [
                'tahun_ajaran_id.exists' => 'Tahun ajaran tidak ditemukan.',
                'tahun_ajaran_id.integer' => 'ID tahun ajaran harus berupa angka.',
            ]);

            if ($tahunValidator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $tahunValidator->errors(),
                ], 422);
            }

            $tahunAjaranId = (int) $request->query('tahun_ajaran_id');
            $query->whereHas('periode', function ($q) use ($tahunAjaranId) {
                $q->where('tahun_ajaran_id', $tahunAjaranId);
            });
        }

        if ($tahunAjaran = $request->query('tahun_ajaran')) {
            $query->where('tahun_ajaran', $tahunAjaran);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('jobdesk', 'like', "%{$search}%")
                    ->orWhere('role_magang', 'like', "%{$search}%");
            });
        }

        // 🔎 Filter status penilaian (nilai mitra)
        if ($penilaian = $request->query('penilaian')) {
            $mitra_id = optional($request->user()->mitra)->mitra_id;

            if ($penilaian === 'none') {
                // Magang yang BELUM ada penilaian mitra
                $query->whereDoesntHave('penilaianMitra');

                if ($mitra_id) {
                    $query->where('mitra_id', $mitra_id);
                }
            } elseif ($penilaian === 'exist') {
                // Magang yang SUDAH ada penilaian mitra
                $query->whereHas('penilaianMitra');

                if ($mitra_id) {
                    $query->where('mitra_id', $mitra_id);
                }
            }
        }


        if ($user->role === 'mahasiswa') {
            // Riwayat magang mahasiswa ditampilkan dari periode terbaru.
            $query->orderByDesc('tahun_ajaran')
                ->orderByDesc('semester_magang')
                ->orderByDesc('magang_id');
        }

        $perPage = (int) $request->query('per_page', 10);
        $magang = $query->paginate($perPage);
        // $magang = $query->get();

        // return response()->json([$user, $mahasiswa], 200);



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
        ])->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $magang);

        return response()->json($magang, 200);
    }

    /**
     * Cari periode master berdasarkan ID atau pasangan tahun ajaran/semester.
     */
    private function resolveRequestedPeriode(Request $request, ?Magang $current = null): ?PeriodeMagang
    {
        if ($request->filled('periode_id')) {
            return PeriodeMagang::with('tahunAjaran')
                ->find((int) $request->input('periode_id'));
        }

        $tahunAjaran = $request->input('tahun_ajaran', $current?->tahun_ajaran);
        $semester = $request->input('semester_magang', $current?->semester_magang);

        if ($tahunAjaran === null || $tahunAjaran === ''
            || $semester === null || $semester === '') {
            return null;
        }

        $periodeId = Magang::resolvePeriodeId($tahunAjaran, $semester);

        return $periodeId
            ? PeriodeMagang::with('tahunAjaran')->find($periodeId)
            : null;
    }

    /**
     * Pastikan satu mahasiswa hanya terdaftar sekali pada periode yang sama.
     */
    private function findDuplicateMagang(
        int $mahasiswaId,
        PeriodeMagang $periode,
        ?int $ignoreMagangId = null
    ): ?Magang {
        $tahunAjaran = Magang::academicStartYear(
            $periode->tahunAjaran?->tahun_ajaran
        );

        $query = Magang::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->where(function ($q) use ($periode, $tahunAjaran) {
                $q->where('periode_id', $periode->periode_id);

                // Data lama yang belum memiliki periode_id tetap dicek agar
                // tidak ada duplikasi pada tahun ajaran + semester yang sama.
                if ($tahunAjaran) {
                    $q->orWhere(function ($legacy) use ($periode, $tahunAjaran) {
                        $legacy->whereNull('periode_id')
                            ->where('tahun_ajaran', $tahunAjaran)
                            ->where('semester_magang', $periode->semester_magang);
                    });
                }
            });

        if ($ignoreMagangId) {
            $query->where('magang_id', '!=', $ignoreMagangId);
        }

        return $query->first();
    }

    private function duplicatePeriodResponse(
        Mahasiswa $mahasiswa,
        PeriodeMagang $periode
    ) {
        $label = 'Semester ' . $periode->semester_magang
            . ' ' . $periode->jenis_semester
            . ' - ' . ($periode->tahunAjaran?->tahun_ajaran ?? '-');

        return response()->json([
            'message' => 'Mahasiswa sudah terdaftar magang pada periode tersebut.',
            'errors' => [
                'periode_id' => [
                    "Magang untuk {$mahasiswa->nama} pada {$label} sudah pernah didaftarkan.",
                ],
            ],
        ], 422);
    }

    /**
     * Simpan magang baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $isAdmin = $this->isAdminRole($user);

        if ($isAdmin) {
            // Admin boleh membuat penempatan magang untuk mahasiswa lain.
            $mahasiswa_id = $request->input('mahasiswa_id');
        } else {
            $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;

            if (!$mahasiswa_id) {
                return response()->json([
                    'message' => 'Akun ini tidak terhubung dengan data mahasiswa.'
                ], 403);
            }
        }

        $rules = [
            'mahasiswa_id' => $isAdmin ? ['required', 'exists:mahasiswa,mahasiswa_id'] : ['sometimes'],
            'mitra_id' => ['nullable', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable', 'exists:dosen_pembimbing,dosbing_id'],
            'periode_id' => ['nullable', 'integer', 'exists:periode_magang,periode_id'],
            'semester_magang' => ['sometimes', 'nullable', 'integer', 'min:1'],
            // Kolom magang.tahun_ajaran bertipe YEAR; frontend mengirim
            // tahun awal dari label master, misalnya 2026 untuk 2026/2027.
            'tahun_ajaran' => [
                'sometimes', 'nullable', 'integer', 'between:1901,2155',
            ],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'status_magang' => ['sometimes', Rule::in(['draft', 'berlangsung', 'selesai', 'ditolak'])],
            // Dokumen wajib untuk pendaftaran mahasiswa, opsional saat admin
            // membuat penempatan langsung dari Master Data.
            'dokumenPenerimaan' => $isAdmin
                ? 'nullable|file|mimes:pdf|max:5120'
                : 'required|file|mimes:pdf|max:5120',
            'dokumenPraKRS' => $isAdmin
                ? 'nullable|file|mimes:pdf|max:5120'
                : 'required|file|mimes:pdf|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $data['mahasiswa_id'] = $mahasiswa_id;

        // Admin hanya boleh membuat penempatan untuk mahasiswa prodinya.
        if ($isAdmin
            && !$this->mahasiswaInAdminProdi($user, (int) $mahasiswa_id)) {
            return $this->denyOutsideProdi();
        }

        $mahasiswa = Mahasiswa::findOrFail((int) $mahasiswa_id);
        $periode = $this->resolveRequestedPeriode($request);

        if ($periode) {
            if (!$periode->is_active
                || !($periode->tahunAjaran?->is_active ?? false)) {
                return response()->json([
                    'message' => 'Periode magang yang dipilih tidak aktif.',
                    'errors' => [
                        'periode_id' => [
                            'Pilih periode magang yang masih aktif.',
                        ],
                    ],
                ], 422);
            }

            $data['periode_id'] = $periode->periode_id;
            $data['semester_magang'] = $periode->semester_magang;
            $data['tahun_ajaran'] = Magang::academicStartYear(
                $periode->tahunAjaran?->tahun_ajaran
            );

            if ($this->findDuplicateMagang(
                (int) $mahasiswa_id,
                $periode
            )) {
                return $this->duplicatePeriodResponse($mahasiswa, $periode);
            }
        } else {
            // Kompatibilitas data lama: bila master periode sama sekali
            // belum tersedia, data lama masih boleh dibuat. Setelah master
            // tersedia, periode wajib dipilih agar tidak ada teks semester
            // yang tidak terhubung ke master.
            $hasMasterPeriod = PeriodeMagang::query()->exists();
            if ($hasMasterPeriod) {
                return response()->json([
                    'message' => 'Periode magang wajib dipilih dari master periode.',
                    'errors' => [
                        'periode_id' => [
                            'Pilih tahun ajaran dan semester magang yang tersedia pada master periode.',
                        ],
                    ],
                ], 422);
            }

            // Fallback hanya untuk instalasi lama tanpa master periode.
            if (empty($data['semester_magang'])) {
                $data['semester_magang'] = 6;
            }
        }

        $filePenerimaan = $request->file('dokumenPenerimaan');
        $filePraKrs = $request->file('dokumenPraKRS');

        // Upload dokumen (hanya jika ada).
        $dokumenPenerimaan = null;
        if ($filePenerimaan) {
            $namePenerimaan = $filePenerimaan->getClientOriginalName();
            $extPenerimaan = $filePenerimaan->getClientOriginalExtension();
            $basePenerimaan = pathinfo($namePenerimaan, PATHINFO_FILENAME);
            $fileNamePenerimaan = $basePenerimaan . "_" . time() . "." . $extPenerimaan;
            $pathPenerimaan = $filePenerimaan->storeAs("dokumen-penerimaan/{$mahasiswa_id}", $fileNamePenerimaan, 'public');
            $dokumenPenerimaan = [
                'file_path' => $pathPenerimaan,
                'nama_file' => $namePenerimaan,
                'ukuran_file' => $filePenerimaan->getSize(),
            ];
        }

        $dokumenPraKrs = null;
        if ($filePraKrs) {
            $namaPraKRS = $filePraKrs->getClientOriginalName();
            $extPraKRS = $filePraKrs->getClientOriginalExtension();
            $basePraKRS = pathinfo($namaPraKRS, PATHINFO_FILENAME);
            $fileNamePraKRS = $basePraKRS . "_" . time() . "." . $extPraKRS;
            $pathPraKrs = $filePraKrs->storeAs("dokumen-pra-krs/{$mahasiswa_id}", $fileNamePraKRS, 'public');
            $dokumenPraKrs = [
                'file_path' => $pathPraKrs,
                'nama_file' => $namaPraKRS,
                'ukuran_file' => $filePraKrs->getSize(),
            ];
        }

        // create magang dulu
        unset($data['dokumenPenerimaan'], $data['dokumenPraKRS']);
        $magang = Magang::create($data);

        // insert ke dokumen_magang jika dokumen dikirim
        if ($dokumenPenerimaan) {
            DokumenMagang::create([
                'magang_id' => $magang->magang_id,
                'jenis_dokumen' => 'doc_surat_penerimaan',
                ...$dokumenPenerimaan,
            ]);
        }

        if ($dokumenPraKrs) {
            DokumenMagang::create([
                'magang_id' => $magang->magang_id,
                'jenis_dokumen' => 'doc_pra_krs',
                ...$dokumenPraKrs,
            ]);
        }

        return response()->json([
            'message' => 'Data magang berhasil dibuat',
            'data' => $magang->load([
                'mahasiswa.prodi',
                'mitra',
                'dosenPembimbing',
                'periode.tahunAjaran',
            ])
        ], 201);
    }

    /**
     * Update data magang.
     */
    public function update(Request $request, $id)
    {
        $magang = Magang::with('dokumenMagang')->findOrFail($id);

        $user = $request->user();
        $this->authorizeMagangAccess($user, $magang);

        if ($user->role === 'admin') {
            // Jika admin mencoba memindahkan magang ke mahasiswa lain,
            // mahasiswa tujuan juga harus berada di prodi admin.
            if ($request->filled('mahasiswa_id')
                && (int) $request->input('mahasiswa_id') !== (int) $magang->mahasiswa_id
                && !$this->mahasiswaInAdminProdi($user, (int) $request->input('mahasiswa_id'))) {
                return $this->denyOutsideProdi();
            }
        }

        $rules = [
            'mahasiswa_id' => ['sometimes', 'exists:mahasiswa,mahasiswa_id'],
            'mitra_id' => ['sometimes', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable', 'exists:dosen_pembimbing,dosbing_id'],
            'periode_id' => ['sometimes', 'nullable', 'integer', 'exists:periode_magang,periode_id'],
            'semester_magang' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'tahun_ajaran' => ['sometimes', 'nullable', 'integer', 'between:1901,2155'],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'status_magang' => ['sometimes', Rule::in(['draft', 'berlangsung', 'selesai', 'ditolak'])],
            'dokumenPenerimaan' => 'sometimes|file|mimes:pdf|max:5120',
            'dokumenPraKRS' => 'sometimes|file|mimes:pdf|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $mahasiswa_id = $magang->mahasiswa_id;
        $targetMahasiswaId = (int) ($data['mahasiswa_id'] ?? $mahasiswa_id);
        $targetMahasiswa = Mahasiswa::findOrFail($targetMahasiswaId);
        $periodeChanged = $request->filled('periode_id')
            || $request->hasAny(['semester_magang', 'tahun_ajaran']);
        $periode = $this->resolveRequestedPeriode($request, $magang);

        if ($periode) {
            $isSamePeriode = (int) $magang->periode_id === (int) $periode->periode_id;

            if (!$isSamePeriode
                && (!$periode->is_active
                    || !($periode->tahunAjaran?->is_active ?? false))) {
                return response()->json([
                    'message' => 'Periode magang yang dipilih tidak aktif.',
                    'errors' => [
                        'periode_id' => [
                            'Pilih periode magang yang masih aktif.',
                        ],
                    ],
                ], 422);
            }

            if ($this->findDuplicateMagang(
                $targetMahasiswaId,
                $periode,
                (int) $magang->magang_id
            )) {
                return $this->duplicatePeriodResponse($targetMahasiswa, $periode);
            }

            $data['periode_id'] = $periode->periode_id;
            $data['semester_magang'] = $periode->semester_magang;
            $data['tahun_ajaran'] = Magang::academicStartYear(
                $periode->tahunAjaran?->tahun_ajaran
            );
        } elseif ($periodeChanged && PeriodeMagang::query()->exists()) {
            return response()->json([
                'message' => 'Periode magang tidak ditemukan pada master.',
                'errors' => [
                    'periode_id' => [
                        'Pilih tahun ajaran dan semester magang yang tersedia pada master periode.',
                    ],
                ],
            ], 422);
        }

        $magang->update(collect($data)->except(['dokumenPenerimaan', 'dokumenPraKRS'])->toArray());

        // update dokumen penerimaan
        if ($request->hasFile('dokumenPenerimaan')) {
            $filePenerimaan = $request->file('dokumenPenerimaan');
            $namePenerimaan = $filePenerimaan->getClientOriginalName();
            $extPenerimaan = $filePenerimaan->getClientOriginalExtension();
            $basePenerimaan = pathinfo($namePenerimaan, PATHINFO_FILENAME);
            $fileNamePenerimaan = $basePenerimaan . "_" . time() . "." . $extPenerimaan;
            $pathPenerimaan = $filePenerimaan->storeAs("dokumen-penerimaan/{$mahasiswa_id}", $fileNamePenerimaan, 'public');

            $dokumen = $magang->dokumenMagang()->where('jenis_dokumen', 'doc_surat_penerimaan')->first();

            if ($dokumen && Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            }

            $magang->dokumenMagang()->updateOrCreate(
                ['jenis_dokumen' => 'doc_surat_penerimaan'],
                [
                    'file_path' => $pathPenerimaan,
                    'nama_file' => $namePenerimaan,
                    'ukuran_file' => $filePenerimaan->getSize()
                ]
            );
        }

        // update dokumen pra KRS
        if ($request->hasFile('dokumenPraKRS')) {
            $filePraKrs = $request->file('dokumenPraKRS');
            $namaPraKRS = $filePraKrs->getClientOriginalName();
            $extPraKRS = $filePraKrs->getClientOriginalExtension();
            $basePraKRS = pathinfo($namaPraKRS, PATHINFO_FILENAME);
            $fileNamePraKRS = $basePraKRS . "_" . time() . "." . $extPraKRS;
            $pathPraKrs = $filePraKrs->storeAs("dokumen-pra-krs/{$mahasiswa_id}", $fileNamePraKRS, 'public');

            $dokumen = $magang->dokumenMagang()->where('jenis_dokumen', 'doc_pra_krs')->first();

            if ($dokumen && Storage::disk('public')->exists($dokumen->file_path)) {
                Storage::disk('public')->delete($dokumen->file_path);
            }

            $magang->dokumenMagang()->updateOrCreate(
                ['jenis_dokumen' => 'doc_pra_krs'],
                [
                    'file_path' => $pathPraKrs,
                    'nama_file' => $namaPraKRS,
                    'ukuran_file' => $filePraKrs->getSize()
                ]
            );
        }

        return response()->json([
            'message' => 'Data magang berhasil diperbarui',
            'data' => $magang->fresh()->load([
                'mahasiswa.prodi',
                'mitra',
                'dosenPembimbing',
                'periode.tahunAjaran',
                'dokumenMagang'
            ])
        ], 200);
    }

    /**
     * Hapus data magang.
     */


    // Controller - bersih
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $magang = Magang::findOrFail($id);

        $this->authorizeMagangAccess($user, $magang);

        try {
            DB::transaction(function () use ($magang) {
                $magang->delete();
            });

            return response()->json([
                'message' => 'Data magang berhasil dihapus'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
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
        ])->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $magang);

        // Logbook
        $logbook = Logbook::with('fotoKegiatan')
            ->where('magang_id', $id)
            ->orderByRaw('tanggal_kegiatan IS NULL')
            ->orderByDesc('tanggal_kegiatan')
            ->orderByDesc('logbook_id')
            ->get();

        // Penilaian mitra (ambil first karena per magang biasanya satu)
        $penilaian = NilaiMitra::where('magang_id', $id)->first();

        // Dokumen magang (hanya penerimaan & pra-krs)
        $dokumen = DokumenMagang::where('magang_id', $id)
            ->whereIn('jenis_dokumen', ['doc_surat_penerimaan', 'doc_pra_krs'])
            ->orderByDesc('dokumen_id')
            ->get();

        // Laporan magang
        $laporan = Laporan::where('magang_id', $id)
            ->get();

        // Jadwal presentasi dibaca dari sumber yang sama dengan menu Jadwal PI:
        // sesi hanya terkait dengan magang jika ada baris peserta yang cocok.
        $jadwalPresentasi = JadwalPresentasi::with('dosenPenguji')
            ->whereHas('peserta', function ($query) use ($id, $magang) {
                $query->where('magang_id', $id)
                    ->where('mahasiswa_id', $magang->mahasiswa_id);
            })
            ->orderBy('tanggal_presentasi')
            ->orderBy('waktu_mulai')
            ->get()
            ->map(function (JadwalPresentasi $jadwal) use ($magang) {
                return [
                    'jadwal_id' => $jadwal->jadwal_id,
                    'magang_id' => $magang->magang_id,
                    'mahasiswa_id' => $magang->mahasiswa_id,
                    'nama_mahasiswa' => $magang->mahasiswa?->nama,
                    'nim' => $magang->mahasiswa?->nim,
                    'nama_mitra' => $magang->mitra?->nama_mitra,
                    'dosen_pembimbing' => $magang->dosenPembimbing?->nama,
                    'dosen_penguji_id' => $jadwal->dosenPenguji?->dosbing_id,
                    'dosen_penguji' => $jadwal->dosenPenguji?->nama,
                    'tanggal_presentasi' => $jadwal->tanggal_presentasi,
                    'waktu_mulai' => $jadwal->waktu_mulai,
                    'waktu_selesai' => $jadwal->waktu_selesai,
                    'ruangan' => $jadwal->ruangan ?: $jadwal->tempat,
                    'keterangan' => $jadwal->keterangan,
                ];
            })
            ->values();

        return response()->json([
            'detail' => $magang,
            'logbook' => $logbook,
            'penilaian' => $penilaian,
            'dokumen' => $dokumen,
            'laporan' => $laporan,
            'jadwal_presentasi' => $jadwalPresentasi,
        ], 200);
    }

    public function exportExcel(Request $request)
    {
        $angkatan = $request->query('angkatan');
        $semesterMagang = $request->query('semester_magang');
        $user = $request->user();
        $mitraId = $request->query('mitra_id');
        $mahasiswaId = null;
        $periodeId = null;

        if ($request->filled('periode_id')) {
            $periodeValidator = Validator::make($request->only('periode_id'), [
                'periode_id' => ['integer', 'exists:periode_magang,periode_id'],
            ], [
                'periode_id.exists' => 'Periode magang tidak ditemukan.',
                'periode_id.integer' => 'ID periode magang harus berupa angka.',
            ]);

            if ($periodeValidator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $periodeValidator->errors(),
                ], 422);
            }

            $periodeId = (int) $request->query('periode_id');
        }

        // Mitra hanya boleh mengekspor data magang miliknya sendiri.
        if ($user && $user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
        }

        // Mahasiswa hanya boleh mengekspor data magangnya sendiri.
        if ($user && $user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id ?: -1;
        }

        // Akun dosbing belum terhubung ke dosen pembimbing; tolak export.
        if ($user && $user->role === 'dosbing') {
            abort(403, 'Anda tidak berhak mengekspor data magang.');
        }

        // Admin hanya mengekspor data magang mahasiswa prodinya.
        // Super Admin tidak dibatasi prodi, namun dapat memfilter per prodi.
        $prodiId = null;
        if ($user && $user->role === 'admin') {
            $adminProdiId = $this->adminProdiId($user);
            if (!$adminProdiId) {
                abort(403, 'Admin tidak memiliki program studi.');
            }
            // -1 memastikan admin tanpa prodi tidak mengekspor seluruh data.
            $prodiId = $adminProdiId;
        } elseif ($user && $user->role === 'super_admin') {
            if ($request->has('prodi_id') && $request->query('prodi_id') !== '' && $request->query('prodi_id') !== null) {
                $validator = Validator::make($request->only('prodi_id'), [
                    'prodi_id' => ['integer', 'exists:prodi,prodi_id'],
                ], [
                    'prodi_id.exists' => 'Program studi tidak ditemukan.',
                    'prodi_id.integer' => 'ID Program studi harus berupa angka.',
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'message' => 'Validasi gagal.',
                        'errors' => $validator->errors(),
                    ], 422);
                }

                $prodiId = (int) $request->query('prodi_id');
            }
        }

        $fileName = "data-magang-" . ($mitraId ?: 'semua') . "-" . $angkatan . "-" . ($periodeId ?: $semesterMagang) . "-" . Carbon::now()->format('ymdHi');
        $fileName .= '.xlsx';

        return Excel::download(new MagangExport($angkatan, $semesterMagang, $mitraId, $prodiId, $mahasiswaId, $periodeId), $fileName);
    }


    /**
     * Menghitung jumlah mahasiswa yang sedang magang (status: berlangsung)
     * pada periode (tahun ajaran) terakhir.
     */
    // public function jumlahMagangAktif()
    // {
    //     // 1. Tentukan tahun ajaran terbaru dari data magang yang ada
    //     $tahunAjaranTerbaru = Magang::latest('tahun_ajaran')->value('tahun_ajaran');

    //     if (!$tahunAjaranTerbaru) {
    //         // Jika tidak ada data magang sama sekali, kembalikan 0
    //         return response()->json(['jumlah_aktif' => 0], 200);
    //     }

    //     // 2. Hitung jumlah magang yang 'berlangsung' pada tahun ajaran tersebut
    //     $jumlahAktif = Magang::where('tahun_ajaran', $tahunAjaranTerbaru)
    //         ->where('status_magang', 'berlangsung')
    //         ->count();

    //     return response()->json(['jumlah_aktif' => $jumlahAktif], 200);
    // }
}
