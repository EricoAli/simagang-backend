<?php

namespace App\Http\Controllers;

use App\Models\Mitra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Magang;
use App\Models\User;
use Illuminate\Support\Str;

use Illuminate\Http\Request;

class MitraController extends Controller
{
    /**
     * Tampilkan daftar mitra (dengan pagination dan pencarian sederhana).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Mitra bersifat global, tetapi data magang yang ikut dimuat dibatasi sesuai role.
        $query = Mitra::with(['magang' => function ($q) use ($user) {
            if ($user->role === 'super_admin') {
                return;
            }

            if ($user->role === 'admin') {
                $prodiId = optional($user->admin)->prodi_id;
                $q->whereHas('mahasiswa', function ($m) use ($prodiId) {
                    $m->where('prodi_id', $prodiId);
                });
            } elseif ($user->role === 'mahasiswa') {
                $q->where('mahasiswa_id', optional($user->mahasiswa)->mahasiswa_id);
            } elseif ($user->role === 'mitra') {
                $q->where('mitra_id', optional($user->mitra)->mitra_id);
            } else {
                $q->whereRaw('1 = 0');
            }
        }]);


        // Pencarian: nama_mitra
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                // $q->where('nama_mitra', 'ilike', "%{$search}%");
                $q->whereRaw('LOWER(nama_mitra) LIKE LOWER(?)', ["%{$search}%"]);
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $mitra = $query->orderBy('mitra_id')->paginate($perPage);

        return response()->json($mitra, 200);
    }

    /**
     * Tampilkan detail mitra berdasarkan id (mitra_id).
     */
    public function show(Request $request)
    {
        $user = $request->user();
        $mitra_id = optional($user->mitra)->mitra_id;
        $mitra = Mitra::findOrFail($mitra_id);

        return response()->json($mitra, 200);
    }

    /**
     * Simpan mitra baru.
     */
    public function store(Request $request)
    {
        // Batas panjang mengikuti kolom: mitra.nama_mitra(150), mitra.email(100), mitra.narahubung(100)
        $rules = [
            'nama_mitra' => ['required', 'string', 'max:150', 'unique:mitra,nama_mitra'],
            'email' => ['required', 'email', 'max:100', 'unique:mitra,email', 'unique:users,email'],
            'narahubung' => ['nullable', 'string', 'max:100'],
        ];

        $messages = [
            'nama_mitra.unique' => 'Mitra dengan nama tersebut sudah ada',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah pernah dipakai'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
            ], 422);
        }

        try {
            $data = $validator->validated();

            $mitra = DB::transaction(function () use ($data) {
                $username = $this->generateUniqueUsername($data['email']);

                // Email sudah divalidasi unik di tabel users, jadi selalu akun baru.
                $user = User::create([
                    'email' => $data['email'],
                    'username' => $username,
                    'password' => $username,
                    'role' => 'mitra',
                ]);

                $data['user_id'] = $user->user_id;

                return Mitra::create($data);
            });

            return response()->json([
                'message' => 'Mitra berhasil dibuat',
                'data' => $mitra,
            ], 201);

        } catch (\Throwable $th) {
            Log::error('MitraController@store error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menambahkan data mitra.',
            ], 500);
        }
    }

    /**
     * Update data mitra.
     */
    public function update(Request $request, $id)
    {
        $mitra = Mitra::findOrFail($id);

        if ($this->isUsedByOtherProdi($request->user(), $mitra)) {
            return response()->json([
                'message' => 'Unauthorized. Mitra sedang digunakan mahasiswa prodi lain.',
            ], 403);
        }

        $rules = [
            'nama_mitra' => ['required', 'string', 'max:150', Rule::unique('mitra', 'nama_mitra')->ignore($mitra->mitra_id, 'mitra_id')],
            'email' => [
                'required',
                'email',
                'max:100',
                Rule::unique('mitra', 'email')->ignore($mitra->mitra_id, 'mitra_id'),
                Rule::unique('users', 'email')->ignore($mitra->user_id, 'user_id'),
            ],
            'narahubung' => ['nullable', 'string', 'max:100'],
        ];

        $messages = [
            'nama_mitra.unique' => 'Mitra dengan nama tersebut sudah ada',
            'email.email' => 'Format email tidak valid',
            'email.unique' => 'Email sudah pernah dipakai',
        ];

        try {
            $validator = Validator::make($request->all(), $rules, $messages);
    
            if ($validator->fails()) {
                return response()->json([
                    'title' => "Validasi Gagal",
                    'message' => collect($validator->errors()->all())->first(),
                ], 422);
            }
    
            $data = $validator->validated();
    
            $mitra->update($data);
    
            return response()->json([
                'message' => 'Mitra berhasil diperbarui',
                'data' => $mitra->fresh()->load(['magang'])
            ], 200);

        } catch (\Throwable $th) {
            Log::error('MitraController@update error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui data mitra.',
            ], 500);
        }

    }

    /**
     * Hapus mitra.
     */
    public function destroy(Request $request, $id)
    {
        $mitra = Mitra::findOrFail($id);

        if ($this->isUsedByOtherProdi($request->user(), $mitra)) {
            return response()->json([
                'message' => 'Unauthorized. Mitra sedang digunakan mahasiswa prodi lain.',
            ], 403);
        }

        // Sementara: mitra yang masih punya relasi magang (termasuk yang selesai/sudah dinilai) tidak boleh dihapus.
        // Relasi magang tidak dikosongkan otomatis.
        $result = DB::transaction(function () use ($id) {
            // Kunci baris mitra agar tidak ada magang baru yang ditautkan selama proses hapus.
            $mitra = Mitra::lockForUpdate()->findOrFail($id);

            $jumlahMagang = Magang::where('mitra_id', $mitra->mitra_id)->count();
            if ($jumlahMagang > 0) {
                return $jumlahMagang;
            }

            // Mitra::booted() ikut menghapus akun user terkait.
            $mitra->delete();

            return 0;
        });

        if ($result > 0) {
            return response()->json([
                'message' => 'Mitra tidak dapat dihapus karena masih memiliki data magang terkait.',
                'jumlah_magang' => $result,
            ], 409);
        }

        return response()->json([
            'message' => 'Mitra berhasil dihapus'
        ], 200);
    }

    public function getMitraByMagang(Request $request, $id)
    {
        $magang = Magang::with('mahasiswa')->findOrFail($id);

        // Pola otorisasi mengikuti MagangController@show.
        $user = $request->user();
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
        }

        $mitra = Mitra::where('mitra_id', $magang->mitra_id)->first();
        return response()->json($mitra, 200);
    }

    public function jumlahMitra()
    {
        $jumlah = Mitra::count();
        return response()->json(['jumlah_mitra' => $jumlah], 200);
    }

    /**
     * Admin Prodi tidak boleh mengubah/menghapus mitra yang dipakai magang mahasiswa prodi lain.
     * Super Admin tidak dibatasi.
     */
    private function isUsedByOtherProdi(User $user, Mitra $mitra): bool
    {
        if ($user->role !== 'admin') {
            return false;
        }

        $prodiId = optional($user->admin)->prodi_id;
        if (!$prodiId) {
            return true;
        }

        return Magang::where('mitra_id', $mitra->mitra_id)
            ->whereHas('mahasiswa', function ($q) use ($prodiId) {
                $q->where('prodi_id', '!=', $prodiId);
            })
            ->exists();
    }

    /**
     * Username diturunkan dari bagian lokal email; diberi sufiks jika sudah dipakai.
     * Kolom users.username varchar(50).
     */
    private function generateUniqueUsername(string $email): string
    {
        $base = Str::limit(Str::before($email, '@'), 50, '');
        $username = $base;
        $i = 1;

        while (User::where('username', $username)->exists()) {
            $suffix = '_' . $i++;
            $username = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
        }

        return $username;
    }
}
