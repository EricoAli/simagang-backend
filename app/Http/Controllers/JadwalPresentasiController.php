<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\Dosbing;
use App\Models\JadwalPeserta;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class JadwalPresentasiController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Tampilkan daftar jadwal presentasi (dengan filter & pagination).
     *
     * - Super Admin: seluruh prodi.
     * - Admin Prodi: jadwal dengan dosen penguji dari prodinya.
     * - Dosbing: jadwal yang memuat mahasiswa bimbingannya.
     * - Mahasiswa: jadwal tempat dirinya menjadi peserta.
     * - Mitra: tidak memiliki akses jadwal.
     */
    public function index(Request $request)
    {
        $query = JadwalPresentasi::with(['dosenPenguji.prodi'])->withCount('peserta');

        $user = $request->user();

        if ($user?->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if (!$mahasiswaId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('peserta', function ($participantQuery) use ($mahasiswaId) {
                    $participantQuery->where('mahasiswa_id', $mahasiswaId)
                        ->whereHas('magang', function ($magangQuery) use ($mahasiswaId) {
                            $magangQuery->where('mahasiswa_id', $mahasiswaId);
                        });
                });
            }
        } elseif ($user?->role === 'admin') {
            $prodiId = $this->adminProdiId($user);
            if (!$prodiId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('dosenPenguji', fn ($q) => $q->where('prodi_id', $prodiId));
            }
        } elseif ($user?->role === 'dosbing') {
            $dosbingId = optional($this->dosbingForUser($user))->dosbing_id;

            if (!$dosbingId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('peserta.magang', function ($q) use ($dosbingId) {
                    $q->where('dosbing_id', $dosbingId);
                });
            }
        } elseif ($user?->role === 'mitra') {
            $query->whereRaw('1 = 0');
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tanggal = $request->query('tanggal_presentasi')) {
            $query->whereDate('tanggal_presentasi', $tanggal);
        }

        $perPage = (int) $request->query('per_page', 15);
        $jadwal = $query->orderBy('tanggal_presentasi')->paginate($perPage);

        $jadwal->getCollection()->transform(fn (JadwalPresentasi $item) => $this->formatJadwal($item));

        return response()->json($jadwal, 200);
    }

    /**
     * Daftar mahasiswa bimbingan dosbing beserta status penjadwalannya.
     */
    public function advisees(Request $request)
    {
        $user = $request->user();
        $dosbing = $this->requireDosbing($user);

        $query = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'periode.tahunAjaran',
            'dosenPembimbing',
            'pesertaJadwal.jadwal',
        ])->where('dosbing_id', $dosbing->dosbing_id);

        if ($request->filled('status_magang')) {
            $query->where('status_magang', $request->query('status_magang'));
        }

        if ($search = trim((string) $request->query('q'))) {
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $data = $query->orderBy('magang_id')->get()->map(function (Magang $magang) {
            $jadwalList = $magang->pesertaJadwal
                ->map(fn (JadwalPeserta $peserta) => $peserta->jadwal)
                ->filter();

            $active = $jadwalList->first(fn (JadwalPresentasi $jadwal) => $jadwal->status === 'terjadwal');
            $completed = $jadwalList->first(fn (JadwalPresentasi $jadwal) => $jadwal->status === 'selesai');

            $statusPenjadwalan = $active
                ? 'sudah_dijadwalkan'
                : ($completed ? 'selesai' : 'belum_dijadwalkan');

            $memenuhiSyarat = $magang->status_magang === 'berlangsung'
                && in_array((int) $magang->semester_magang, [6, 7], true);

            $mahasiswa = $magang->mahasiswa;

            return [
                'magang_id' => $magang->magang_id,
                'mahasiswa_id' => $mahasiswa?->mahasiswa_id,
                'nim' => $mahasiswa?->nim,
                'nama' => $mahasiswa?->nama,
                'prodi' => $mahasiswa?->prodi?->kode_prodi,
                'prodi_id' => $mahasiswa?->prodi_id,
                'status_magang' => $magang->status_magang,
                'semester_magang' => $magang->semester_magang,
                'periode_label' => $this->periodeLabel($magang),
                'mitra' => $magang->mitra?->nama_mitra,
                'status_penjadwalan' => $statusPenjadwalan,
                'bisa_dijadwalkan' => $memenuhiSyarat && $statusPenjadwalan !== 'sudah_dijadwalkan',
                'jadwal_id' => $active?->jadwal_id ?? $completed?->jadwal_id,
                'jadwal' => ($active ?? $completed)
                    ? $this->formatJadwal($active ?? $completed)
                    : null,
                'mahasiswa' => $mahasiswa,
                'magang' => [
                    'magang_id' => $magang->magang_id,
                    'status_magang' => $magang->status_magang,
                    'semester_magang' => $magang->semester_magang,
                    'periode_id' => $magang->periode_id,
                    'mitra_id' => $magang->mitra_id,
                    'dosbing_id' => $magang->dosbing_id,
                ],
            ];
        })->values();

        return response()->json(['data' => $data], 200);
    }

    /**
     * Tampilkan detail jadwal presentasi.
     */
    public function show(Request $request, $id)
    {
        $jadwal = JadwalPresentasi::with(['dosenPenguji.prodi'])->withCount('peserta')->findOrFail($id);
        $this->authorizeScheduleAccess($request->user(), $jadwal);

        return response()->json($this->formatJadwal($jadwal), 200);
    }

    /**
     * Peserta satu sesi.
     */
    public function peserta(Request $request, $id)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with('dosenPenguji')->findOrFail($id);
        $this->authorizeParticipantManagement($user, $jadwal);

        $query = $jadwal->peserta()->with([
            'mahasiswa.prodi',
            'magang.mitra',
            'magang.dosenPembimbing',
        ]);

        if ($user->role === 'admin') {
            $prodiId = $this->adminProdiId($user);
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', $prodiId);
            });
        } elseif ($user->role === 'dosbing') {
            $dosbingId = optional($this->dosbingForUser($user))->dosbing_id;
            $query->whereHas('magang', function ($q) use ($dosbingId) {
                $q->where('dosbing_id', $dosbingId);
            });
        }

        $data = $query->orderBy('peserta_id')->get()
            ->map(fn (JadwalPeserta $participant) => $this->formatParticipant($participant))
            ->values();

        return response()->json(['data' => $data], 200);
    }

    /**
     * Magang aktif yang belum terdaftar pada sesi ini.
     */
    public function eligiblePeserta(Request $request, $id)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with('dosenPenguji')->findOrFail($id);
        $this->authorizeParticipantManagement($user, $jadwal);

        $query = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
            ->where('status_magang', 'berlangsung')
            ->whereIn('semester_magang', [6, 7])
            ->whereDoesntHave('mahasiswa.pesertaJadwal', function ($q) use ($id) {
                $q->where('jadwal_id', $id);
            })
            ->whereDoesntHave('pesertaJadwal.jadwal', function ($q) use ($id) {
                $q->where('status', 'terjadwal')
                    ->where('jadwal_id', '!=', $id);
            });

        if ($user->role === 'admin') {
            $prodiId = $this->adminProdiId($user);
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', $prodiId);
            });
        } elseif ($user->role === 'dosbing') {
            $dosbingId = optional($this->dosbingForUser($user))->dosbing_id;
            $query->where('dosbing_id', $dosbingId);
        }

        $data = $query->orderBy('magang_id')->get()->map(function (Magang $magang) {
            $mahasiswa = $magang->mahasiswa;

            return [
                'magang_id' => $magang->magang_id,
                'mahasiswa_id' => $mahasiswa?->mahasiswa_id,
                'nim' => $mahasiswa?->nim,
                'nama' => $mahasiswa?->nama,
                'status_magang' => $magang->status_magang,
                'semester_magang' => $magang->semester_magang,
                'mahasiswa' => $mahasiswa,
                'mitra' => $magang->mitra,
                'dosen' => $magang->dosenPembimbing?->nama,
            ];
        })->values();

        return response()->json(['data' => $data], 200);
    }

    /** Tambahkan satu magang/mahasiswa sebagai peserta sesi. */
    public function tambahPeserta(Request $request, $id)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with('dosenPenguji')->findOrFail($id);
        $this->authorizeScheduleWrite($user, $jadwal);

        $validator = Validator::make($request->all(), [
            'magang_id' => ['required', 'integer', 'exists:magang,magang_id'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $magang = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
            ->findOrFail((int) $validator->validated()['magang_id']);

        if ($user->role === 'admin'
            && (int) optional($magang->mahasiswa)->prodi_id !== (int) $this->adminProdiId($user)) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat mengelola peserta dari program studinya.',
            ], 403);
        }

        if ($user->role === 'dosbing') {
            $dosbing = $this->requireDosbing($user);
            $this->assertMagangBelongsToDosbing($dosbing, $magang);
        }

        if ($error = $this->magangPresentationError($magang)) {
            return response()->json(['message' => $error], 422);
        }

        if ($jadwal->peserta()->where('mahasiswa_id', $magang->mahasiswa_id)->exists()) {
            return response()->json([
                'message' => 'Mahasiswa sudah terdaftar pada jadwal presentasi ini.',
            ], 409);
        }

        if ($active = $this->activeScheduleForMagang($magang->magang_id, $jadwal->jadwal_id)) {
            return response()->json([
                'message' => 'Mahasiswa sudah memiliki jadwal presentasi aktif pada sesi lain.',
                'jadwal_id' => $active->jadwal_id,
            ], 422);
        }

        try {
            $participant = DB::transaction(function () use ($jadwal, $magang) {
                return JadwalPeserta::create([
                    'jadwal_id' => $jadwal->jadwal_id,
                    'magang_id' => $magang->magang_id,
                    'mahasiswa_id' => $magang->mahasiswa_id,
                ]);
            });
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
                return response()->json([
                    'message' => 'Mahasiswa sudah terdaftar pada jadwal presentasi ini.',
                ], 409);
            }

            report($exception);

            return response()->json([
                'message' => 'Terjadi kesalahan saat menambahkan peserta.',
            ], 500);
        }

        $participant->load(['mahasiswa.prodi', 'magang.mitra', 'magang.dosenPembimbing']);

        return response()->json([
            'message' => 'Peserta berhasil ditambahkan ke jadwal presentasi.',
            'data' => $this->formatParticipant($participant),
        ], 201);
    }

    /** Hapus keanggotaan peserta saja; data mahasiswa dan magang tetap ada. */
    public function hapusPeserta(Request $request, $id, $magangId)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with('dosenPenguji')->findOrFail($id);
        $this->authorizeScheduleWrite($user, $jadwal);

        $participant = JadwalPeserta::with(['mahasiswa', 'magang'])
            ->where('jadwal_id', $id)
            ->where('magang_id', $magangId)
            ->firstOrFail();

        if ($user->role === 'admin'
            && (int) optional($participant->mahasiswa)->prodi_id !== (int) $this->adminProdiId($user)) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat mengelola peserta dari program studinya.',
            ], 403);
        }

        if ($user->role === 'dosbing') {
            $dosbing = $this->requireDosbing($user);
            $this->assertMagangBelongsToDosbing($dosbing, $participant->magang);
        }

        DB::transaction(fn () => $participant->delete());

        return response()->json([
            'message' => 'Peserta berhasil dikeluarkan dari jadwal presentasi.',
        ], 200);
    }

    /**
     * Simpan jadwal presentasi baru.
     *
     * Dosbing wajib menyertakan magang_id milik mahasiswa bimbingannya agar
     * sesi yang dibuat langsung memiliki peserta yang sah.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        $this->authorizeScheduleCreate($user);

        $rules = [
            'magang_id' => ['nullable', 'integer', 'exists:magang,magang_id'],
            'dosen_penguji_id' => ['required', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'tanggal_presentasi' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['required', 'string', 'max:100'],
            'tempat' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'waktu_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $magangId = $data['magang_id'] ?? null;
        unset($data['magang_id']);

        $examiner = Dosbing::findOrFail((int) $data['dosen_penguji_id']);
        if ($user->role === 'admin'
            && (int) $examiner->prodi_id !== (int) $this->adminProdiId($user)) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat memilih dosen penguji dari program studinya.',
            ], 403);
        }

        $magang = null;
        if ($magangId) {
            $magang = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
                ->findOrFail((int) $magangId);

            if ($user->role === 'dosbing') {
                $dosbing = $this->requireDosbing($user);
                $this->assertMagangBelongsToDosbing($dosbing, $magang);
            } elseif ($user->role === 'admin'
                && (int) optional($magang->mahasiswa)->prodi_id !== (int) $this->adminProdiId($user)) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat menjadwalkan mahasiswa dari program studinya.',
                ], 403);
            }
        } elseif ($user->role === 'dosbing') {
            return response()->json([
                'message' => 'Pilih mahasiswa bimbingan yang akan dijadwalkan.',
                'errors' => ['magang_id' => ['Mahasiswa bimbingan wajib dipilih.']],
            ], 422);
        }

        if ($magang) {
            if ($error = $this->magangPresentationError($magang)) {
                return response()->json(['message' => $error], 422);
            }

            if ($active = $this->activeScheduleForMagang($magang->magang_id)) {
                return response()->json([
                    'message' => 'Mahasiswa sudah memiliki jadwal presentasi aktif pada sesi lain.',
                    'jadwal_id' => $active->jadwal_id,
                ], 422);
            }
        }

        if ($conflict = $this->findScheduleConflict($data)) {
            return $this->conflictResponse($conflict);
        }

        try {
            $jadwal = DB::transaction(function () use ($data, $magang) {
                $jadwal = JadwalPresentasi::create($data);

                if ($magang) {
                    JadwalPeserta::create([
                        'jadwal_id' => $jadwal->jadwal_id,
                        'magang_id' => $magang->magang_id,
                        'mahasiswa_id' => $magang->mahasiswa_id,
                    ]);
                }

                return $jadwal;
            });
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Terjadi kesalahan saat membuat jadwal presentasi.',
            ], 500);
        }

        $jadwal->load(['dosenPenguji.prodi'])->loadCount('peserta');

        return response()->json([
            'message' => 'Jadwal presentasi berhasil dibuat',
            'data' => $this->formatJadwal($jadwal),
        ], 201);
    }

    /**
     * Update jadwal presentasi.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with(['dosenPenguji', 'peserta.magang'])->findOrFail($id);
        $this->authorizeScheduleWrite($user, $jadwal);

        $rules = [
            'magang_id' => ['nullable', 'integer', 'exists:magang,magang_id'],
            'dosen_penguji_id' => ['sometimes', 'required', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'tanggal_presentasi' => ['sometimes', 'required', 'date'],
            'waktu_mulai' => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['required', 'string', 'max:100'],
            'tempat' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
        ];

        $validator = Validator::make($request->all(), $rules, [
            'waktu_selesai.after' => 'Waktu selesai harus setelah waktu mulai.',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $magangId = $data['magang_id'] ?? null;
        unset($data['magang_id']);

        if (array_key_exists('dosen_penguji_id', $data)) {
            $examiner = Dosbing::findOrFail((int) $data['dosen_penguji_id']);
            if ($user->role === 'admin'
                && (int) $examiner->prodi_id !== (int) $this->adminProdiId($user)) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat memilih dosen penguji dari program studinya.',
                ], 403);
            }
        }

        $magang = null;
        if ($magangId) {
            $magang = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
                ->findOrFail((int) $magangId);

            if ($user->role === 'dosbing') {
                $dosbing = $this->requireDosbing($user);
                $this->assertMagangBelongsToDosbing($dosbing, $magang);
            } elseif ($user->role === 'admin'
                && (int) optional($magang->mahasiswa)->prodi_id !== (int) $this->adminProdiId($user)) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat menjadwalkan mahasiswa dari program studinya.',
                ], 403);
            }

            if ($error = $this->magangPresentationError($magang)) {
                return response()->json(['message' => $error], 422);
            }

            $alreadyInSession = $jadwal->peserta()
                ->where('magang_id', $magang->magang_id)
                ->exists();

            if (!$alreadyInSession
                && ($active = $this->activeScheduleForMagang($magang->magang_id, $jadwal->jadwal_id))) {
                return response()->json([
                    'message' => 'Mahasiswa sudah memiliki jadwal presentasi aktif pada sesi lain.',
                    'jadwal_id' => $active->jadwal_id,
                ], 422);
            }
        }

        $conflictPayload = [
            'dosen_penguji_id' => $data['dosen_penguji_id'] ?? $jadwal->dosen_penguji_id,
            'tanggal_presentasi' => $data['tanggal_presentasi'] ?? $jadwal->tanggal_presentasi,
            'waktu_mulai' => $data['waktu_mulai'] ?? $jadwal->waktu_mulai,
            'waktu_selesai' => $data['waktu_selesai'] ?? $jadwal->waktu_selesai,
            'ruangan' => $data['ruangan'] ?? $jadwal->ruangan,
        ];

        if ($conflict = $this->findScheduleConflict($conflictPayload, $jadwal->jadwal_id)) {
            return $this->conflictResponse($conflict);
        }

        try {
            DB::transaction(function () use ($jadwal, $data, $magang) {
                $jadwal->update($data);

                if ($magang && !$jadwal->peserta()->where('magang_id', $magang->magang_id)->exists()) {
                    JadwalPeserta::create([
                        'jadwal_id' => $jadwal->jadwal_id,
                        'magang_id' => $magang->magang_id,
                        'mahasiswa_id' => $magang->mahasiswa_id,
                    ]);
                }
            });
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Terjadi kesalahan saat memperbarui jadwal presentasi.',
            ], 500);
        }

        $jadwal = $jadwal->fresh(['dosenPenguji.prodi']);
        $jadwal->loadCount('peserta');

        return response()->json([
            'message' => 'Jadwal presentasi berhasil diperbarui',
            'data' => $this->formatJadwal($jadwal),
        ], 200);
    }

    /**
     * Hapus jadwal presentasi.
     *
     * Sesi yang masih memiliki peserta tidak boleh dihapus agar riwayat dan
     * peserta tidak hilang. Keluarkan peserta terlebih dahulu.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $jadwal = JadwalPresentasi::with(['dosenPenguji', 'peserta.magang'])->findOrFail($id);
        $this->authorizeScheduleWrite($user, $jadwal);

        if ($jadwal->peserta()->exists()) {
            return response()->json([
                'message' => 'Sesi jadwal presentasi tidak dapat dihapus karena masih memiliki peserta. Keluarkan peserta terlebih dahulu.',
                'errors' => [
                    'peserta' => ['Sesi masih memiliki peserta.'],
                ],
            ], 422);
        }

        try {
            DB::transaction(fn () => $jadwal->delete());
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Terjadi kesalahan saat menghapus jadwal presentasi.',
            ], 500);
        }

        return response()->json([
            'message' => 'Jadwal presentasi berhasil dihapus',
        ], 200);
    }

    private function formatParticipant(JadwalPeserta $participant): array
    {
        $magang = $participant->magang;
        $mahasiswa = $participant->mahasiswa;

        return [
            'peserta_id' => $participant->peserta_id,
            'magang_id' => $participant->magang_id,
            'mahasiswa_id' => $participant->mahasiswa_id,
            'nim' => $mahasiswa?->nim,
            'nama' => $mahasiswa?->nama,
            'mahasiswa' => $mahasiswa,
            'mitra' => $magang?->mitra,
            'dosen' => $magang?->dosenPembimbing,
            'status_magang' => $magang?->status_magang,
            'semester_magang' => $magang?->semester_magang,
        ];
    }

    private function authorizeScheduleCreate($user): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        if ($user?->role === 'admin' && $this->adminProdiId($user)) {
            return;
        }

        if ($user?->role === 'dosbing' && $this->dosbingForUser($user)) {
            return;
        }

        abort(403, 'Anda tidak berhak membuat jadwal presentasi.');
    }

    /**
     * Otorisasi baca satu sesi.
     */
    private function authorizeScheduleAccess($user, JadwalPresentasi $jadwal): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        if ($user?->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            $isParticipant = $mahasiswaId && $jadwal->peserta()
                ->where('mahasiswa_id', $mahasiswaId)
                ->whereHas('magang', function ($query) use ($mahasiswaId) {
                    $query->where('mahasiswa_id', $mahasiswaId);
                })
                ->exists();

            if (!$isParticipant) {
                abort(403, 'Jadwal presentasi ini bukan milik Anda.');
            }
            return;
        }

        if ($user?->role === 'admin') {
            $this->authorizeAdminProdiForSchedule($user, $jadwal);
            return;
        }

        if ($user?->role === 'dosbing') {
            $dosbing = $this->requireDosbing($user);

            if (!$this->dosbingOwnsSchedule($dosbing, $jadwal)) {
                abort(403, 'Jadwal presentasi ini bukan untuk mahasiswa bimbingan Anda.');
            }
            return;
        }

        abort(403, 'Anda tidak berhak mengakses jadwal presentasi ini.');
    }

    /**
     * Otorisasi ubah/hapus sesi.
     */
    private function authorizeScheduleWrite($user, JadwalPresentasi $jadwal): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        if ($user?->role === 'admin') {
            $this->authorizeAdminProdiForSchedule($user, $jadwal);
            return;
        }

        if ($user?->role === 'dosbing') {
            $dosbing = $this->requireDosbing($user);

            if (!$this->dosbingOwnsSchedule($dosbing, $jadwal)) {
                abort(403, 'Anda hanya dapat mengelola jadwal mahasiswa bimbingan Anda.');
            }
            return;
        }

        abort(403, 'Anda tidak berhak mengelola jadwal presentasi ini.');
    }

    /**
     * Otorisasi kelola peserta (lihat kandidat/tambah/keluarkan).
     */
    private function authorizeParticipantManagement($user, JadwalPresentasi $jadwal): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        if ($user?->role === 'admin') {
            $this->authorizeAdminProdiForSchedule($user, $jadwal);
            return;
        }

        if ($user?->role === 'dosbing') {
            $dosbing = $this->requireDosbing($user);

            if (!$this->dosbingOwnsSchedule($dosbing, $jadwal)) {
                abort(403, 'Anda hanya dapat mengelola peserta mahasiswa bimbingan Anda.');
            }
            return;
        }

        abort(403, 'Anda tidak berhak mengelola peserta jadwal presentasi.');
    }

    private function authorizeAdminProdiForSchedule($user, JadwalPresentasi $jadwal): void
    {
        $adminProdiId = $this->adminProdiId($user);
        $examinerProdiId = (int) optional($jadwal->dosenPenguji)->prodi_id;

        if (!$adminProdiId || $examinerProdiId !== $adminProdiId) {
            abort(403, 'Admin Prodi hanya dapat mengelola jadwal dari program studinya.');
        }
    }

    private function requireDosbing($user): Dosbing
    {
        $dosbing = $this->dosbingForUser($user);

        if (!$dosbing) {
            abort(403, 'Akun dosen pembimbing belum terhubung ke data dosen pembimbing.');
        }

        return $dosbing;
    }

    /**
     * Dosbing hanya boleh mengelola sesi yang seluruh pesertanya adalah
     * mahasiswa bimbingannya. Sesi campuran antar-dosbing tidak boleh diubah
     * agar jadwal mahasiswa lain tidak ikut berubah.
     */
    private function dosbingOwnsSchedule(Dosbing $dosbing, JadwalPresentasi $jadwal): bool
    {
        $total = $jadwal->peserta()->count();

        if ($total === 0) {
            // Sesi kosong tidak mengandung data mahasiswa lain, sehingga aman
            // dikelola/dihapus oleh dosbing mana pun.
            return true;
        }

        $owned = $jadwal->peserta()
            ->whereHas('magang', function ($q) use ($dosbing) {
                $q->where('dosbing_id', $dosbing->dosbing_id);
            })
            ->count();

        return $owned === $total;
    }

    private function assertMagangBelongsToDosbing(Dosbing $dosbing, ?Magang $magang): void
    {
        if (!$magang || (int) $magang->dosbing_id !== (int) $dosbing->dosbing_id) {
            abort(403, 'Mahasiswa tersebut bukan mahasiswa bimbingan Anda.');
        }
    }

    /**
     * Syarat mahasiswa dapat dijadwalkan presentasi.
     */
    private function magangPresentationError(Magang $magang): ?string
    {
        if (!$magang->mahasiswa) {
            return 'Data mahasiswa pada magang tidak ditemukan.';
        }

        if ($magang->status_magang !== 'berlangsung') {
            return 'Mahasiswa belum memenuhi syarat sebagai peserta presentasi.';
        }

        if (!in_array((int) $magang->semester_magang, [6, 7], true)) {
            return 'Mahasiswa belum memenuhi syarat sebagai peserta presentasi.';
        }

        return null;
    }

    /**
     * Cari jadwal aktif lain untuk satu magang.
     */
    private function activeScheduleForMagang(int $magangId, ?int $ignoreJadwalId = null): ?JadwalPresentasi
    {
        return JadwalPresentasi::where('status', 'terjadwal')
            ->when($ignoreJadwalId, fn ($q) => $q->where('jadwal_id', '!=', $ignoreJadwalId))
            ->whereHas('peserta', fn ($q) => $q->where('magang_id', $magangId))
            ->first();
    }

    /**
     * Deteksi bentrok waktu pada tanggal yang sama untuk ruangan atau dosen
     * penguji yang sama. Sesi berstatus dibatalkan diabaikan.
     */
    private function findScheduleConflict(array $data, ?int $ignoreJadwalId = null): ?JadwalPresentasi
    {
        $tanggal = substr((string) ($data['tanggal_presentasi'] ?? ''), 0, 10);

        if ($tanggal === '') {
            return null;
        }

        $mulai = $this->timeToMinutes($data['waktu_mulai'] ?? null);
        $selesai = $this->timeToMinutes($data['waktu_selesai'] ?? null);

        if ($mulai === null || $selesai === null || $selesai <= $mulai) {
            return null;
        }

        $ruangan = $this->normalizeRoom($data['ruangan'] ?? '');
        $pengujiId = !empty($data['dosen_penguji_id']) ? (int) $data['dosen_penguji_id'] : null;

        $candidates = JadwalPresentasi::whereDate('tanggal_presentasi', $tanggal)
            ->when($ignoreJadwalId, fn ($q) => $q->where('jadwal_id', '!=', $ignoreJadwalId))
            ->where('status', '!=', 'dibatalkan')
            ->get();

        foreach ($candidates as $candidate) {
            $candidateMulai = $this->timeToMinutes($candidate->waktu_mulai);
            $candidateSelesai = $this->timeToMinutes($candidate->waktu_selesai);

            if ($candidateMulai === null || $candidateSelesai === null) {
                continue;
            }

            $overlap = $candidateMulai < $selesai && $candidateSelesai > $mulai;

            if (!$overlap) {
                continue;
            }

            $sameRoom = $ruangan !== ''
                && $this->normalizeRoom($candidate->ruangan) === $ruangan;
            $sameExaminer = $pengujiId
                && (int) $candidate->dosen_penguji_id === $pengujiId;

            if ($sameRoom || $sameExaminer) {
                return $candidate;
            }
        }

        return null;
    }

    private function conflictResponse(JadwalPresentasi $conflict)
    {
        return response()->json([
            'message' => 'Jadwal bertabrakan dengan sesi lain pada tanggal, ruangan, atau dosen penguji yang sama.',
            'errors' => [
                'jadwal' => [
                    'Bentrok dengan jadwal #' . $conflict->jadwal_id
                    . ' (' . $conflict->waktu_mulai . '-' . $conflict->waktu_selesai
                    . ', ' . ($conflict->ruangan ?: 'tanpa ruangan') . ').',
                ],
            ],
            'jadwal_id' => $conflict->jadwal_id,
        ], 422);
    }

    private function validationError($validator)
    {
        return response()->json([
            'title' => 'Validasi gagal',
            'message' => collect($validator->errors()->all())->first(),
            'errors' => $validator->errors(),
        ], 422);
    }

    private function timeToMinutes($value): ?int
    {
        if (!preg_match('/(\d{1,2})[.:](\d{2})/', (string) $value, $match)) {
            return null;
        }

        $hours = (int) $match[1];
        $minutes = (int) $match[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function normalizeRoom($value): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $value)));
    }

    private function periodeLabel(Magang $magang): ?string
    {
        $periode = $magang->periode;
        $tahun = $periode?->tahunAjaran?->tahun_ajaran;

        if (!$tahun) {
            return null;
        }

        return trim($tahun . ' · Semester ' . $periode->semester_magang
            . ($periode->jenis_semester ? ' ' . $periode->jenis_semester : ''));
    }

    private function formatJadwal(JadwalPresentasi $jadwal): array
    {
        $data = $jadwal->toArray();
        $examiner = $jadwal->dosenPenguji;

        $data['dosen_penguji_id'] = $examiner?->dosbing_id;
        $data['dosen_penguji'] = $examiner?->nama;
        $data['dosen_penguji_data'] = $examiner
            ? [
                'dosbing_id' => $examiner->dosbing_id,
                'nama' => $examiner->nama,
                'prodi_id' => $examiner->prodi_id,
            ]
            : null;

        // Field yang sengaja disembunyikan model tetap dibutuhkan frontend
        // untuk menampilkan status sesi dan membedakan tempat/link.
        $data['status'] = $jadwal->status;
        $data['tempat'] = $jadwal->tempat;
        $data['peserta_count'] = $jadwal->peserta_count ?? $jadwal->peserta()->count();
        $data['punya_peserta'] = $data['peserta_count'] > 0;

        return $data;
    }
}
