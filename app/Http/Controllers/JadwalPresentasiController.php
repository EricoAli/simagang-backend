<?php

namespace App\Http\Controllers;

use App\Models\JadwalPresentasi;
use App\Models\Magang;
use Illuminate\Http\Request;
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

        // Eksplisit tolak role yang tidak diizinkan mengakses jadwal
        if (in_array($user->role, ['dosbing', 'mitra'])) {
            return response()->json([
                'message' => 'Akses jadwal presentasi untuk peran '.$user->role.' belum didukung.',
            ], 403);
        }

        $query = JadwalPresentasi::with('magang.mahasiswa.prodi');

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->whereHas('magang', function ($q) use ($mahasiswaId) {
                    $q->where('mahasiswa_id', $mahasiswaId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($adminProdiId) {
                // Admin Prodi hanya melihat jadwal yang terkait mahasiswa dari prodinya
                $query->whereHas('magang.mahasiswa', function ($q) use ($adminProdiId) {
                    $q->where('prodi_id', $adminProdiId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Filter berdasarkan status
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Filter berdasarkan tanggal
        if ($tanggal = $request->query('tanggal_presentasi')) {
            $query->whereDate('tanggal_presentasi', $tanggal);
        }

        // Filter berdasarkan magang_id
        if ($magangId = $request->query('magang_id')) {
            $query->where('magang_id', $magangId);
        }

        // Pagination dan urutan
        $perPage = (int) $request->query('per_page', 15);
        $jadwal = $query->orderBy('tanggal_presentasi')->paginate($perPage);

        return response()->json($jadwal, 200);
    }

    /**
     * Tampilkan detail jadwal presentasi.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();

        // Eksplisit tolak dosbing dan mitra sebelum query
        if (in_array($user->role, ['dosbing', 'mitra'])) {
            return response()->json([
                'message' => 'Akses jadwal presentasi untuk peran '.$user->role.' belum didukung.',
            ], 403);
        }

        $jadwal = JadwalPresentasi::with('magang.mahasiswa.prodi')->findOrFail($id);

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if (! $jadwal->magang_id || optional($jadwal->magang)->mahasiswa_id !== $mahasiswaId) {
                return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan milik anda.'], 403);
            }
        } elseif ($user->role === 'admin') {
            if (is_null($jadwal->magang_id)) {
                return response()->json([
                    'message' => 'Unauthorized. Jadwal historis belum dikaitkan dengan aktivitas magang mahasiswa.',
                ], 403);
            }

            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($jadwal->magang?->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($jadwal, 200);
    }

    /**
     * Simpan jadwal presentasi baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola jadwal presentasi'], 403);
        }

        $rules = [
            'magang_id' => ['required', 'integer', 'exists:magang,magang_id'],
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
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $magang = Magang::with('mahasiswa')->findOrFail($data['magang_id']);

        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($magang->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa magang bukan dari prodi anda.'], 403);
            }
        }

        try {
            $jadwal = JadwalPresentasi::create($data);

            return response()->json([
                'message' => 'Jadwal presentasi berhasil dibuat',
                'data' => $jadwal->load('magang.mahasiswa'),
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

        $jadwal = JadwalPresentasi::with('magang.mahasiswa')->findOrFail($id);

        // Otorisasi terhadap jadwal eksisting
        if ($user->role === 'admin') {
            if (is_null($jadwal->magang_id)) {
                return response()->json([
                    'message' => 'Unauthorized. Hanya Super Admin yang dapat mengelola jadwal historis tanpa relasi magang.',
                ], 403);
            }

            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($jadwal->magang?->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan dari prodi anda.'], 403);
            }
        }

        $rules = [
            'magang_id' => ['sometimes', 'required', 'integer', 'exists:magang,magang_id'],
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
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Validasi kepemilikan prodi jika magang_id diperbarui
        if (isset($data['magang_id'])) {
            $targetMagang = Magang::with('mahasiswa')->findOrFail($data['magang_id']);
            if ($user->role === 'admin') {
                $adminProdiId = optional($user->admin)->prodi_id;
                if (optional($targetMagang->mahasiswa)->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa magang target bukan dari prodi anda.'], 403);
                }
            }
        }

        try {
            $jadwal->update($data);

            return response()->json([
                'message' => 'Jadwal presentasi berhasil diperbarui',
                'data' => $jadwal->fresh()->load('magang.mahasiswa'),
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

        $jadwal = JadwalPresentasi::with('magang.mahasiswa')->findOrFail($id);

        if ($user->role === 'admin') {
            if (is_null($jadwal->magang_id)) {
                return response()->json([
                    'message' => 'Unauthorized. Hanya Super Admin yang dapat menghapus jadwal historis tanpa relasi magang.',
                ], 403);
            }

            $adminProdiId = optional($user->admin)->prodi_id;
            if (optional($jadwal->magang?->mahasiswa)->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Jadwal presentasi bukan dari prodi anda.'], 403);
            }
        }

        $jadwal->delete();

        return response()->json([
            'message' => 'Jadwal presentasi berhasil dihapus',
        ], 200);
    }
}
