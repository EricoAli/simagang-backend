<?php

namespace App\Http\Controllers;

use App\Models\Dosbing;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class JadwalPresentasiController extends Controller
{
    /**
     * Tampilkan daftar jadwal presentasi (dengan filter & pagination).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (in_array($user->role, ['dosbing', 'mitra'], true)) {
            return response()->json([
                'message' => 'Akses jadwal presentasi untuk peran '.$user->role.' belum didukung.',
            ], 403);
        }

        $query = JadwalPresentasi::with([
            'magang.mahasiswa.prodi',
            'magang.dosenPembimbing',
            'magang.mitra',
            'magang.periode.tahunAjaran',
            'penguji',
            'pesertaMagang.mahasiswa.prodi',
            'pesertaMagang.dosenPembimbing',
            'pesertaMagang.mitra',
            'pesertaMagang.periode.tahunAjaran',
        ])->withCount('pesertaMagang');

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->where(function ($q) use ($mahasiswaId) {
                    $q->whereHas('magang', fn ($mq) => $mq->where('mahasiswa_id', $mahasiswaId))
                        ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('mahasiswa_id', $mahasiswaId));
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($adminProdiId) {
                $query->where(function ($q) use ($adminProdiId) {
                    $q->where('prodi_id', $adminProdiId)
                        ->orWhereHas('magang.mahasiswa', fn ($mq) => $mq->where('prodi_id', $adminProdiId))
                        ->orWhereHas('pesertaMagang.mahasiswa', fn ($pq) => $pq->where('prodi_id', $adminProdiId));
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($tanggal = $request->query('tanggal_presentasi')) {
            $query->whereDate('tanggal_presentasi', $tanggal);
        }

        if ($magangId = $request->query('magang_id')) {
            $query->where(function ($q) use ($magangId) {
                $q->where('magang_id', $magangId)
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('magang.magang_id', $magangId));
            });
        }

        if ($mahasiswaId = $request->query('mahasiswa_id')) {
            $query->where(function ($q) use ($mahasiswaId) {
                $q->whereHas('magang', fn ($mq) => $mq->where('mahasiswa_id', $mahasiswaId))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('mahasiswa_id', $mahasiswaId));
            });
        }

        if ($dosbingId = $request->query('dosbing_id')) {
            $query->where(function ($q) use ($dosbingId) {
                $q->whereHas('magang', fn ($mq) => $mq->where('dosbing_id', $dosbingId))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('dosbing_id', $dosbingId));
            });
        }

        if ($dosenPengujiId = $request->query('dosen_penguji_id')) {
            $query->where('dosen_penguji_id', $dosenPengujiId);
        }

        if ($periodeId = $request->query('periode_id')) {
            $query->where(function ($q) use ($periodeId) {
                $q->whereHas('magang', fn ($mq) => $mq->where('periode_id', $periodeId))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('periode_id', $periodeId));
            });
        }

        if ($tahunAjaranId = $request->query('tahun_ajaran_id')) {
            $query->where(function ($q) use ($tahunAjaranId) {
                $q->whereHas('magang', fn ($mq) => $mq->where('tahun_ajaran_id', $tahunAjaranId))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('tahun_ajaran_id', $tahunAjaranId));
            });
        }

        if ($tahunAjaran = trim((string) $request->query('tahun_ajaran', ''))) {
            $query->where(function ($q) use ($tahunAjaran) {
                $q->whereHas('magang', fn ($mq) => $mq->where('tahun_ajaran', 'like', "{$tahunAjaran}%"))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('tahun_ajaran', 'like', "{$tahunAjaran}%"));
            });
        }

        if ($semesterMagang = $request->query('semester_magang')) {
            $sem = (int) $semesterMagang;
            $query->where(function ($q) use ($sem) {
                $q->whereHas('magang', fn ($mq) => $mq->where('semester_magang', $sem))
                    ->orWhereHas('pesertaMagang', fn ($pq) => $pq->where('semester_magang', $sem));
            });
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->where('ruangan', 'like', "%{$search}%")
                    ->orWhere('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('penguji', fn ($dq) => $dq->where('nama', 'like', "%{$search}%"))
                    ->orWhereHas('magang.mahasiswa', fn ($mq) => $mq->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%"))
                    ->orWhereHas('pesertaMagang.mahasiswa', fn ($pq) => $pq->where('nama', 'like', "%{$search}%")->orWhere('nim', 'like', "%{$search}%"));
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 500);
        $jadwal = $query->orderBy('tanggal_presentasi')->paginate($perPage);

        return response()->json($jadwal, 200);
    }

    /**
     * Tampilkan detail jadwal presentasi.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        if (in_array($user->role, ['dosbing', 'mitra'], true)) {
            return response()->json([
                'message' => 'Akses jadwal presentasi untuk peran '.$user->role.' belum didukung.',
            ], 403);
        }

        $jadwal = JadwalPresentasi::with([
            'magang.mahasiswa.prodi',
            'magang.dosenPembimbing',
            'magang.mitra',
            'magang.periode.tahunAjaran',
            'penguji',
            'pesertaMagang.mahasiswa.prodi',
            'pesertaMagang.dosenPembimbing',
            'pesertaMagang.mitra',
        ])->withCount('pesertaMagang')->findOrFail($id);

        if ($denied = $this->authorizeScheduleView($user, $jadwal)) {
            return $denied;
        }

        return response()->json($jadwal, 200);
    }

    /**
     * Simpan jadwal presentasi baru (mendukung sesi multi-peserta maupun single magang_id).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola jadwal presentasi'], 403);
        }

        $input = $this->normalizeTimeInputs($request->all());

        $rules = [
            'magang_id' => ['nullable', 'integer', 'exists:magang,magang_id'],
            'dosen_penguji_id' => ['nullable', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'prodi_id' => ['nullable', 'integer', 'exists:prodi,prodi_id'],
            'tanggal_presentasi' => ['required', 'date'],
            'waktu_mulai' => ['required', 'date_format:H:i'],
            'waktu_selesai' => ['required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['required', 'string', 'max:100'],
            'tempat' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['nullable', 'in:terjadwal,selesai,dibatalkan'],
        ];

        $messages = [
            'waktu_selesai.after' => 'Waktu mulai tidak dapat melebihi waktu akhir',
            'magang_id.exists' => 'Data magang tidak ditemukan',
            'dosen_penguji_id.exists' => 'Dosen penguji tidak ditemukan',
        ];

        $validator = Validator::make($input, $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $magang = null;
        if (! empty($data['magang_id'])) {
            $magang = Magang::with('mahasiswa')->findOrFail($data['magang_id']);

            if ($user->role === 'admin') {
                $adminProdiId = optional($user->admin)->prodi_id;
                if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa magang bukan dari prodi anda.'], 403);
                }
            }

            if (! empty($data['dosen_penguji_id']) && $magang->dosbing_id && (int) $data['dosen_penguji_id'] === (int) $magang->dosbing_id) {
                return response()->json([
                    'title' => 'Validasi gagal',
                    'message' => 'Dosen penguji tidak boleh sama dengan dosen pembimbing mahasiswa.',
                    'errors' => ['dosen_penguji_id' => ['Dosen penguji tidak boleh sama dengan dosen pembimbing mahasiswa.']],
                ], 422);
            }

            $data['prodi_id'] = $magang->mahasiswa?->prodi_id ?? $data['prodi_id'] ?? null;
        } elseif ($user->role === 'admin') {
            $data['prodi_id'] = optional($user->admin)->prodi_id;
        }

        try {
            $jadwal = DB::transaction(function () use ($data, $magang) {
                $jadwal = JadwalPresentasi::create($data);

                if ($magang) {
                    $jadwal->pesertaMagang()->syncWithoutDetaching([$magang->magang_id]);
                }

                return $jadwal;
            });

            return response()->json([
                'message' => 'Jadwal presentasi berhasil dibuat',
                'data' => $jadwal->fresh()->load(['magang.mahasiswa', 'penguji', 'pesertaMagang.mahasiswa'])->loadCount('pesertaMagang'),
            ], 201);
        } catch (\Throwable $th) {
            Log::error('JadwalPresentasiController@store error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat membuat jadwal presentasi.',
            ], 500);
        }
    }

    /**
     * Update jadwal presentasi.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola jadwal presentasi'], 403);
        }

        $jadwal = JadwalPresentasi::with(['magang.mahasiswa', 'pesertaMagang.mahasiswa'])->findOrFail($id);

        if ($denied = $this->authorizeAdminManage($user, $jadwal)) {
            return $denied;
        }

        $input = $this->normalizeTimeInputs($request->all());

        $rules = [
            'magang_id' => ['sometimes', 'nullable', 'integer', 'exists:magang,magang_id'],
            'dosen_penguji_id' => ['sometimes', 'nullable', 'integer', 'exists:dosen_pembimbing,dosbing_id'],
            'tanggal_presentasi' => ['sometimes', 'required', 'date'],
            'waktu_mulai' => ['sometimes', 'required', 'date_format:H:i'],
            'waktu_selesai' => ['sometimes', 'required', 'date_format:H:i', 'after:waktu_mulai'],
            'ruangan' => ['sometimes', 'required', 'string', 'max:100'],
            'tempat' => ['nullable', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:terjadwal,selesai,dibatalkan'],
        ];

        $messages = [
            'waktu_selesai.after' => 'Waktu mulai tidak dapat melebihi waktu akhir',
            'magang_id.exists' => 'Data magang tidak ditemukan',
            'dosen_penguji_id.exists' => 'Dosen penguji tidak ditemukan',
        ];

        $validator = Validator::make($input, $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $targetMagang = null;
        if (! empty($data['magang_id'])) {
            $targetMagang = Magang::with('mahasiswa')->findOrFail($data['magang_id']);
            if ($user->role === 'admin') {
                $adminProdiId = optional($user->admin)->prodi_id;
                if (optional($targetMagang->mahasiswa)->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa magang target bukan dari prodi anda.'], 403);
                }
            }
            $data['prodi_id'] = $targetMagang->mahasiswa?->prodi_id ?? $jadwal->prodi_id;
        }

        $targetPengujiId = array_key_exists('dosen_penguji_id', $data)
            ? $data['dosen_penguji_id']
            : $jadwal->dosen_penguji_id;

        if ($targetPengujiId) {
            $participants = $jadwal->pesertaMagang->pluck('dosbing_id')->filter();
            if ($targetMagang && $targetMagang->dosbing_id) {
                $participants->push($targetMagang->dosbing_id);
            } elseif ($jadwal->magang?->dosbing_id) {
                $participants->push($jadwal->magang->dosbing_id);
            }

            if ($participants->contains(fn ($idDosen) => (int) $idDosen === (int) $targetPengujiId)) {
                return response()->json([
                    'title' => 'Validasi gagal',
                    'message' => 'Dosen penguji tidak boleh sama dengan dosen pembimbing peserta pada sesi ini.',
                    'errors' => ['dosen_penguji_id' => ['Dosen penguji bentrok dengan dosen pembimbing peserta.']],
                ], 422);
            }
        }

        try {
            DB::transaction(function () use ($jadwal, $data, $targetMagang) {
                $jadwal->update($data);

                if ($targetMagang) {
                    $jadwal->pesertaMagang()->syncWithoutDetaching([$targetMagang->magang_id]);
                }
            });

            return response()->json([
                'message' => 'Jadwal presentasi berhasil diperbarui',
                'data' => $jadwal->fresh()->load(['magang.mahasiswa', 'penguji', 'pesertaMagang.mahasiswa'])->loadCount('pesertaMagang'),
            ], 200);
        } catch (\Throwable $th) {
            Log::error('JadwalPresentasiController@update error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui jadwal presentasi.',
            ], 500);
        }
    }

    /**
     * Hapus jadwal presentasi.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola jadwal presentasi'], 403);
        }

        $jadwal = JadwalPresentasi::with(['magang.mahasiswa', 'pesertaMagang.mahasiswa'])->findOrFail($id);

        if ($denied = $this->authorizeAdminManage($user, $jadwal)) {
            return $denied;
        }

        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal presentasi berhasil dihapus',
        ], 200);
    }

    /**
     * Daftar mahasiswa bimbingan beserta status penjadwalan presentasinya.
     */
    public function advisees(Request $request)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin', 'dosbing'], true)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $query = Magang::with([
            'mahasiswa.prodi',
            'mitra',
            'dosenPembimbing',
            'periode.tahunAjaran',
            'jadwalPresentasi',
            'jadwalPresentasiPeserta',
        ]);

        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            $query->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $adminProdiId));
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

        if ($search = trim((string) $request->query('q', ''))) {
            $query->whereHas('mahasiswa', function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('magang_id')->get()->map(function (Magang $magang) {
            $jadwal = $magang->jadwalPresentasiPeserta->first() ?? $magang->jadwalPresentasi->first();

            $statusPenjadwalan = 'belum_dijadwalkan';
            if ($jadwal) {
                $statusPenjadwalan = $jadwal->status === 'selesai' ? 'selesai' : 'sudah_dijadwalkan';
            }

            return [
                'magang_id' => $magang->magang_id,
                'nama' => $magang->mahasiswa?->nama,
                'nim' => $magang->mahasiswa?->nim,
                'prodi' => $magang->mahasiswa?->prodi?->kode_prodi ?? $magang->mahasiswa?->prodi?->nama_prodi,
                'mitra' => $magang->mitra?->nama_mitra,
                'dosbing_id' => $magang->dosbing_id,
                'dosen_pembimbing' => $magang->dosenPembimbing?->nama,
                'periode_id' => $magang->periode_id,
                'periode_label' => $magang->periode_label,
                'status_magang' => $magang->status_magang,
                'status_penjadwalan' => $statusPenjadwalan,
                'bisa_dijadwalkan' => $magang->status_magang === 'berlangsung' && $jadwal === null,
                'jadwal_id' => $jadwal?->jadwal_id,
            ];
        })->values();

        return response()->json(['data' => $items], 200);
    }

    /**
     * Daftar peserta pada satu sesi presentasi.
     */
    public function peserta(Request $request, $id)
    {
        $user = $request->user();
        if (in_array($user->role, ['dosbing', 'mitra'], true)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $jadwal = JadwalPresentasi::with([
            'magang.mahasiswa.prodi',
            'magang.mitra',
            'magang.dosenPembimbing',
            'pesertaMagang.mahasiswa.prodi',
            'pesertaMagang.mitra',
            'pesertaMagang.dosenPembimbing',
        ])->findOrFail($id);

        if ($denied = $this->authorizeScheduleView($user, $jadwal)) {
            return $denied;
        }

        $merged = $jadwal->pesertaMagang->keyBy('magang_id');
        if ($jadwal->magang && ! $merged->has($jadwal->magang->magang_id)) {
            $merged->put($jadwal->magang->magang_id, $jadwal->magang);
        }

        $data = $merged->values()->map(function (Magang $magang) {
            return [
                'peserta_id' => $magang->pivot?->peserta_id ?? $magang->magang_id,
                'magang_id' => $magang->magang_id,
                'nim' => $magang->mahasiswa?->nim,
                'nama' => $magang->mahasiswa?->nama,
                'prodi' => $magang->mahasiswa?->prodi?->kode_prodi,
                'mitra' => $magang->mitra?->nama_mitra ?? '-',
                'lokasi' => null,
                'dosen' => $magang->dosenPembimbing?->nama ?? '-',
                'dosbing_id' => $magang->dosbing_id,
                'mahasiswa' => $magang->mahasiswa,
                'dosen_pembimbing' => $magang->dosenPembimbing,
            ];
        });

        return response()->json(['data' => $data], 200);
    }

    /**
     * Daftar mahasiswa magang yang memenuhi syarat untuk ditambahkan ke sesi presentasi.
     */
    public function mahasiswaEligible(Request $request, $id)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat melihat kandidat peserta presentasi.'], 403);
        }

        $jadwal = JadwalPresentasi::with(['magang.mahasiswa', 'pesertaMagang.mahasiswa'])->findOrFail($id);

        if ($denied = $this->authorizeAdminManage($user, $jadwal)) {
            return $denied;
        }

        $existingIds = $jadwal->pesertaMagang->pluck('magang_id');
        if ($jadwal->magang_id) {
            $existingIds->push($jadwal->magang_id);
        }
        $existingIds = $existingIds->unique()->values()->all();

        $query = Magang::with(['mahasiswa.prodi', 'mitra', 'dosenPembimbing', 'periode.tahunAjaran'])
            ->where('status_magang', 'berlangsung');

        if (! empty($existingIds)) {
            $query->whereNotIn('magang_id', $existingIds);
        }

        if ($jadwal->dosen_penguji_id) {
            $query->where(function ($q) use ($jadwal) {
                $q->whereNull('dosbing_id')
                    ->orWhere('dosbing_id', '!=', $jadwal->dosen_penguji_id);
            });
        }

        $targetProdiId = $user->role === 'admin'
            ? optional($user->admin)->prodi_id
            : ($jadwal->prodi_id ?: null);

        if ($targetProdiId) {
            $query->whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $targetProdiId));
        }

        if ($dosbingId = $request->query('dosbing_id')) {
            $query->where('dosbing_id', $dosbingId);
        }

        if ($periodeId = $request->query('periode_id')) {
            $query->where('periode_id', $periodeId);
        }

        $eligible = $query->orderByDesc('magang_id')->get()->map(function (Magang $magang) {
            return [
                'magang_id' => $magang->magang_id,
                'nim' => $magang->mahasiswa?->nim,
                'nama' => $magang->mahasiswa?->nama,
                'prodi' => $magang->mahasiswa?->prodi?->kode_prodi,
                'mitra' => $magang->mitra?->nama_mitra ?? '-',
                'dosbing_id' => $magang->dosbing_id,
                'dosen' => $magang->dosenPembimbing?->nama ?? '-',
                'periode_label' => $magang->periode_label,
                'status_magang' => $magang->status_magang,
            ];
        })->values();

        return response()->json(['data' => $eligible], 200);
    }

    /**
     * Tambah mahasiswa magang ke dalam sesi presentasi.
     */
    public function addPeserta(Request $request, $id)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola peserta presentasi.'], 403);
        }

        $jadwal = JadwalPresentasi::with(['magang.mahasiswa', 'pesertaMagang.mahasiswa'])->findOrFail($id);

        if ($denied = $this->authorizeAdminManage($user, $jadwal)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'magang_id' => ['required', 'integer', 'exists:magang,magang_id'],
        ], [
            'magang_id.required' => 'Mahasiswa magang wajib dipilih.',
            'magang_id.exists' => 'Data magang tidak ditemukan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $magang = Magang::with('mahasiswa')->findOrFail($validator->validated()['magang_id']);

        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }
        }

        if ($jadwal->dosen_penguji_id && $magang->dosbing_id && (int) $jadwal->dosen_penguji_id === (int) $magang->dosbing_id) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => 'Dosen penguji tidak boleh sama dengan dosen pembimbing mahasiswa.',
            ], 422);
        }

        $alreadyInPivot = $jadwal->pesertaMagang()->where('jadwal_presentasi_peserta.magang_id', $magang->magang_id)->exists();
        if ($alreadyInPivot || (int) $jadwal->magang_id === (int) $magang->magang_id) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => 'Mahasiswa sudah terdaftar sebagai peserta pada sesi ini.',
            ], 422);
        }

        DB::transaction(function () use ($jadwal, $magang) {
            $jadwal->pesertaMagang()->attach($magang->magang_id);

            if (! $jadwal->prodi_id && $magang->mahasiswa?->prodi_id) {
                $jadwal->prodi_id = $magang->mahasiswa->prodi_id;
                $jadwal->save();
            }
        });

        return response()->json([
            'message' => 'Peserta berhasil ditambahkan ke sesi presentasi.',
            'data' => $jadwal->fresh()->load(['pesertaMagang.mahasiswa', 'penguji'])->loadCount('pesertaMagang'),
        ], 201);
    }

    /**
     * Keluarkan mahasiswa magang dari sesi presentasi.
     */
    public function removePeserta(Request $request, $id, $magangId)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola peserta presentasi.'], 403);
        }

        $jadwal = JadwalPresentasi::with(['magang.mahasiswa', 'pesertaMagang.mahasiswa'])->findOrFail($id);

        if ($denied = $this->authorizeAdminManage($user, $jadwal)) {
            return $denied;
        }

        DB::transaction(function () use ($jadwal, $magangId) {
            $jadwal->pesertaMagang()->detach($magangId);

            if ((int) $jadwal->magang_id === (int) $magangId) {
                $jadwal->magang_id = $jadwal->pesertaMagang()->value('jadwal_presentasi_peserta.magang_id');
                $jadwal->save();
            }
        });

        return response()->json([
            'message' => 'Peserta berhasil dikeluarkan dari sesi presentasi.',
        ], 200);
    }

    private function authorizeScheduleView(User $user, JadwalPresentasi $jadwal)
    {
        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            $matchesLegacy = $jadwal->magang_id && optional($jadwal->magang)->mahasiswa_id === $mahasiswaId;
            $matchesPivot = $jadwal->pesertaMagang->contains(fn ($m) => (int) $m->mahasiswa_id === (int) $mahasiswaId);

            if (! $matchesLegacy && ! $matchesPivot) {
                return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan milik anda.'], 403);
            }

            return null;
        }

        if ($user->role === 'admin') {
            return $this->authorizeAdminManage($user, $jadwal, true);
        }

        if ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return null;
    }

    private function authorizeAdminManage(User $user, JadwalPresentasi $jadwal, bool $isRead = false)
    {
        if ($user->role !== 'admin') {
            return null;
        }

        if (is_null($jadwal->magang_id) && is_null($jadwal->prodi_id) && $jadwal->pesertaMagang->isEmpty()) {
            $msg = $isRead
                ? 'Unauthorized. Jadwal historis belum dikaitkan dengan aktivitas magang mahasiswa.'
                : 'Unauthorized. Hanya Super Admin yang dapat mengelola jadwal historis tanpa relasi magang.';

            return response()->json(['message' => $msg], 403);
        }

        $adminProdiId = optional($user->admin)->prodi_id;
        $matchesProdi = ($jadwal->prodi_id && (int) $jadwal->prodi_id === (int) $adminProdiId)
            || (optional($jadwal->magang?->mahasiswa)->prodi_id === $adminProdiId)
            || $jadwal->pesertaMagang->contains(fn ($m) => optional($m->mahasiswa)->prodi_id === $adminProdiId);

        if (! $matchesProdi) {
            return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan dari prodi anda.'], 403);
        }

        return null;
    }

    private function normalizeTimeInputs(array $input): array
    {
        foreach (['waktu_mulai', 'waktu_selesai'] as $field) {
            if (! empty($input[$field]) && preg_match('/^(\d{2}:\d{2}):\d{2}$/', trim((string) $input[$field]), $m)) {
                $input[$field] = $m[1];
            }
        }

        return $input;
    }
}
