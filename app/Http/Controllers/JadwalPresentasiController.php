<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use Illuminate\Http\Request;
use App\Models\JadwalPresentasi;
use App\Models\JadwalPeserta;
use App\Models\Magang;
use App\Models\Dosbing;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;


class JadwalPresentasiController extends Controller
{
    use AuthorizesMagangAccess;

        /**
     * Tampilkan daftar jadwal presentasi (dengan filter & pagination).
     */
    public function index(Request $request)
    {
        // Gunakan query builder, bukan get()
        $query = JadwalPresentasi::with(['dosenPenguji.prodi'])->withCount('peserta');

        $user = $request->user();
        if ($user?->role === 'mahasiswa') {
            // Mahasiswa hanya melihat sesi tempat dirinya terdaftar sebagai peserta.
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
            $prodiId = (int) optional($user->admin)->prodi_id;
            if (!$prodiId) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('dosenPenguji', fn ($q) => $q->where('prodi_id', $prodiId));
            }
        }

        // Filter berdasarkan status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter berdasarkan tanggal
        if ($tanggal = $request->query('tanggal_presentasi')) {
            $query->whereDate('tanggal_presentasi', $tanggal);
        }

        // Pagination dan urutan
        $perPage = (int) $request->query('per_page', 15);
        $jadwal = $query->orderBy('tanggal_presentasi')->paginate($perPage);

        $jadwal->getCollection()->transform(fn (JadwalPresentasi $item) => $this->formatJadwal($item));

        return response()->json($jadwal, 200);
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
     * Peserta satu sesi. Admin hanya menerima peserta dari prodi akunnya.
     */
    public function peserta(Request $request, $id)
    {
        $user = $request->user();
        $this->authorizeAdminWithProdi($user);

        $jadwal = JadwalPresentasi::findOrFail($id);
        $this->authorizeScheduleAccess($user, $jadwal);
        $query = $jadwal->peserta()->with([
            'mahasiswa.prodi',
            'magang.mitra',
            'magang.dosenPembimbing',
        ]);

        if ($user->role === 'admin') {
            $prodiId = (int) optional($user->admin)->prodi_id;
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', $prodiId);
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
        $this->authorizeAdminWithProdi($user);
        $jadwal = JadwalPresentasi::findOrFail($id);
        $this->authorizeScheduleAccess($user, $jadwal);

        $query = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
            ->where('status_magang', 'berlangsung')
            ->whereIn('semester_magang', [6, 7])
            ->whereDoesntHave('mahasiswa.pesertaJadwal', function ($q) use ($id) {
                $q->where('jadwal_id', $id);
            });

        if ($user->role === 'admin') {
            $prodiId = (int) optional($user->admin)->prodi_id;
            $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', $prodiId);
            });
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
        $this->authorizeAdminWithProdi($user);

        $validator = Validator::make($request->all(), [
            'magang_id' => ['required', 'integer', 'exists:magang,magang_id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $jadwal = JadwalPresentasi::findOrFail($id);
        $this->authorizeScheduleAccess($user, $jadwal);
        $magang = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing'])
            ->findOrFail((int) $validator->validated()['magang_id']);

        if ($user->role === 'admin'
            && (int) optional($magang->mahasiswa)->prodi_id !== (int) optional($user->admin)->prodi_id) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat mengelola peserta dari program studinya.',
            ], 403);
        }

        if (!$magang->mahasiswa
            || $magang->status_magang !== 'berlangsung'
            || !in_array((int) $magang->semester_magang, [6, 7], true)) {
            return response()->json([
                'message' => 'Mahasiswa belum memenuhi syarat sebagai peserta presentasi.',
            ], 422);
        }

        if ($jadwal->peserta()->where('mahasiswa_id', $magang->mahasiswa_id)->exists()) {
            return response()->json([
                'message' => 'Mahasiswa sudah terdaftar pada jadwal presentasi ini.',
            ], 409);
        }

        try {
            $participant = JadwalPeserta::create([
                'jadwal_id' => $jadwal->jadwal_id,
                'magang_id' => $magang->magang_id,
                'mahasiswa_id' => $magang->mahasiswa_id,
            ]);
        } catch (QueryException $exception) {
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'unique') || str_contains($message, 'duplicate')) {
                return response()->json([
                    'message' => 'Mahasiswa sudah terdaftar pada jadwal presentasi ini.',
                ], 409);
            }

            throw $exception;
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
        $this->authorizeAdminWithProdi($user);

        $jadwal = JadwalPresentasi::findOrFail($id);
        $this->authorizeScheduleAccess($user, $jadwal);
        $participant = JadwalPeserta::with('mahasiswa')
            ->where('jadwal_id', $id)
            ->where('magang_id', $magangId)
            ->firstOrFail();

        if ($user->role === 'admin'
            && (int) optional($participant->mahasiswa)->prodi_id !== (int) optional($user->admin)->prodi_id) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat mengelola peserta dari program studinya.',
            ], 403);
        }

        $participant->delete();

        return response()->json([
            'message' => 'Peserta berhasil dikeluarkan dari jadwal presentasi.',
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

    /**
     * Simpan jadwal presentasi baru.
     */
    public function store(Request $request)
    {
        $this->authorizeAdminWithProdi($request->user());

        $rules = [
            'dosen_penguji_id' => ['required', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'tanggal_presentasi' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['required', 'string', 'max:100'],
            'keterangan' => ['string']
        ];

        $messages = [
            'waktu_selesai.after' => 'Waktu mulai tidak dapat melebihi waktu akhir'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
            ], 422);
        }

        $examiner = Dosbing::findOrFail((int) $validator->validated()['dosen_penguji_id']);
        if ($request->user()->role === 'admin'
            && (int) $examiner->prodi_id !== (int) optional($request->user()->admin)->prodi_id) {
            return response()->json([
                'message' => 'Admin Prodi hanya dapat memilih dosen penguji dari program studinya.',
            ], 403);
        }

        try {
            $jadwal = JadwalPresentasi::create($validator->validated());
            $jadwal->load('dosenPenguji.prodi');
    
            return response()->json([
                'message' => 'Jadwal presentasi berhasil dibuat',
                'data' => $this->formatJadwal($jadwal),
            ], 201);
            
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update jadwal presentasi.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeAdminWithProdi($request->user());

        $rules = [
            'dosen_penguji_id' => ['sometimes', 'required', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'tanggal_presentasi' => ['sometimes', 'required', 'date'],
            'waktu_mulai' => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['required', 'string', 'max:100'],
            'keterangan' => ['string'],
        ];

        $messages = [
            'waktu_selesai.after' => 'Waktu mulai tidak dapat melebihi waktu akhir'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
            ], 422);
        }

        $jadwal = JadwalPresentasi::with('dosenPenguji')->findOrFail($id);
        $this->authorizeScheduleAccess($request->user(), $jadwal);
        if (array_key_exists('dosen_penguji_id', $validator->validated())) {
            $examiner = Dosbing::findOrFail((int) $validator->validated()['dosen_penguji_id']);
            if ($request->user()->role === 'admin'
                && (int) $examiner->prodi_id !== (int) optional($request->user()->admin)->prodi_id) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat memilih dosen penguji dari program studinya.',
                ], 403);
            }
        }

        try {
            $jadwal->update($validator->validated());
            $jadwal->load('dosenPenguji.prodi');
    
            return response()->json([
                'message' => 'Jadwal presentasi berhasil diperbarui',
                'data' => $this->formatJadwal($jadwal->fresh('dosenPenguji.prodi')),
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus jadwal presentasi.
     */
    public function destroy(Request $request, $id)
    {
        $this->authorizeAdminWithProdi($request->user());

        $jadwal = JadwalPresentasi::findOrFail($id);
        $this->authorizeScheduleAccess($request->user(), $jadwal);
        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal presentasi berhasil dihapus',
        ], 200);
    }

    private function authorizeScheduleAccess($user, JadwalPresentasi $jadwal): void
    {
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

        if ($user?->role !== 'admin') {
            return;
        }

        $adminProdiId = (int) optional($user->admin)->prodi_id;
        $examinerProdiId = (int) optional($jadwal->dosenPenguji)->prodi_id;
        if (!$adminProdiId || $examinerProdiId !== $adminProdiId) {
            abort(403, 'Admin Prodi hanya dapat mengelola jadwal dari program studinya.');
        }
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

        return $data;
    }
}

