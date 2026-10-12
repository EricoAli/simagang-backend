<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Dosbing;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SuperAdminController extends Controller
{
    /**
     * Tampilkan daftar Admin Prodi dengan pagination & filter.
     */
    public function indexAdminProdi(Request $request)
    {
        $query = Admin::with(['user:user_id,username,email,role,is_active,created_at', 'prodi:prodi_id,kode_prodi,nama_prodi']);

        // Filter prodi_id
        if ($prodiId = $request->query('prodi_id')) {
            $query->where('prodi_id', $prodiId);
        }

        // Filter status keaktifan akun
        if ($request->has('is_active')) {
            $isActive = filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('user', function ($q) use ($isActive) {
                $q->where('is_active', $isActive);
            });
        }

        // Pencarian nama, username, atau email
        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $admins = $query->paginate($perPage);

        return response()->json($admins, 200);
    }

    /**
     * Simpan akun Admin Prodi baru.
     */
    public function storeAdminProdi(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'nama' => ['required', 'string', 'max:100'],
            'prodi_id' => ['required', 'exists:prodi,prodi_id'],
        ], [
            'username.unique' => 'Username sudah terdaftar',
            'email.unique' => 'Email sudah terdaftar',
            'prodi_id.exists' => 'Program studi tidak valid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            // 1. Buat user
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'admin',
                'is_active' => true,
            ]);

            // 2. Buat data_admin
            $admin = Admin::create([
                'user_id' => $user->user_id,
                'prodi_id' => $data['prodi_id'],
                'nama' => $data['nama'],
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Akun Admin Prodi berhasil dibuat',
                'data' => $admin->load(['user:user_id,username,email,role,is_active', 'prodi:prodi_id,kode_prodi,nama_prodi']),
            ], 201);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('SuperAdminController@storeAdminProdi error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat membuat akun Admin Prodi.',
            ], 500);
        }
    }

    /**
     * Detail akun Admin Prodi berdasarkan admin_id.
     */
    public function showAdminProdi($id)
    {
        $admin = Admin::with(['user:user_id,username,email,role,is_active,created_at', 'prodi:prodi_id,kode_prodi,nama_prodi'])
            ->findOrFail($id);

        return response()->json($admin, 200);
    }

    /**
     * Update akun Admin Prodi.
     */
    public function updateAdminProdi(Request $request, $id)
    {
        $admin = Admin::with('user')->findOrFail($id);
        $user = $admin->user;

        $validator = Validator::make($request->all(), [
            'nama' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->user_id, 'user_id')],
            'prodi_id' => ['sometimes', 'required', 'exists:prodi,prodi_id'],
            'password' => ['nullable', 'string', 'min:6'],
        ], [
            'email.unique' => 'Email sudah digunakan oleh akun lain',
            'prodi_id.exists' => 'Program studi tidak valid',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        DB::beginTransaction();
        try {
            // Update data admin
            if (isset($data['nama'])) {
                $admin->nama = $data['nama'];
            }
            if (isset($data['prodi_id'])) {
                $admin->prodi_id = $data['prodi_id'];
            }
            $admin->save();

            // Update user
            if (isset($data['email'])) {
                $user->email = $data['email'];
            }
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }
            $user->save();

            DB::commit();

            return response()->json([
                'message' => 'Akun Admin Prodi berhasil diperbarui',
                'data' => $admin->fresh()->load(['user:user_id,username,email,role,is_active', 'prodi:prodi_id,kode_prodi,nama_prodi']),
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error('SuperAdminController@updateAdminProdi error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui akun Admin Prodi.',
            ], 500);
        }
    }

    /**
     * Toggle status aktif / nonaktif Admin Prodi.
     */
    public function toggleStatusAdminProdi(Request $request, $id)
    {
        $admin = Admin::with('user')->findOrFail($id);
        $user = $admin->user;

        $validator = Validator::make($request->all(), [
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => 'Parameter is_active (boolean) wajib disertakan',
                'errors' => $validator->errors(),
            ], 422);
        }

        $isActive = $request->boolean('is_active');
        $user->is_active = $isActive;
        $user->save();

        // Jika dinonaktifkan, cabut seluruh token aktif user ini
        if (! $isActive) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $isActive ? 'Akun Admin Prodi berhasil diaktifkan' : 'Akun Admin Prodi berhasil dinonaktifkan',
            'data' => [
                'admin_id' => $admin->admin_id,
                'user_id' => $user->user_id,
                'username' => $user->username,
                'is_active' => $user->is_active,
            ],
        ], 200);
    }

    /**
     * Dashboard Super Admin: Statistik agregat 4 prodi dan filter per prodi.
     */
    public function dashboard(Request $request)
    {
        $prodiId = $request->query('prodi_id');

        // Jika filter prodi spesifik diberikan
        if ($prodiId && $prodiId !== 'all') {
            $prodi = Prodi::find($prodiId);
            if (! $prodi) {
                return response()->json([
                    'message' => 'Program studi dengan ID tersebut tidak ditemukan',
                ], 404);
            }

            $queryMagang = Magang::whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $prodiId));
            $semesterMagangTerbaru = (clone $queryMagang)->latest('semester_magang')->value('semester_magang');
            $jumlahMagangAktif = $semesterMagangTerbaru
                ? (clone $queryMagang)->where('semester_magang', $semesterMagangTerbaru)->count()
                : 0;

            $jumlahMahasiswa = Mahasiswa::where('prodi_id', $prodiId)->count();
            $jumlahAdmin = Admin::where('prodi_id', $prodiId)->count();
            $jumlahMitra = Mitra::count(); // Mitra institusional DTEDI
            $jumlahDosbing = Dosbing::count(); // Dosen pembimbing DTEDI

            return response()->json([
                'mode' => 'filter',
                'prodi' => [
                    'prodi_id' => $prodi->prodi_id,
                    'kode_prodi' => $prodi->kode_prodi,
                    'nama_prodi' => $prodi->nama_prodi,
                ],
                'statistik' => [
                    'jumlah_mahasiswa' => $jumlahMahasiswa,
                    'jumlah_mahasiswa_aktif_magang' => $jumlahMagangAktif,
                    'semester_magang_terbaru' => $semesterMagangTerbaru,
                    'jumlah_admin_prodi' => $jumlahAdmin,
                    'jumlah_mitra' => $jumlahMitra,
                    'jumlah_dosbing' => $jumlahDosbing,
                ],
            ], 200);
        }

        // Agregat seluruh prodi (default)
        $semesterMagangTerbaru = Magang::latest('semester_magang')->value('semester_magang');
        $totalMagangAktif = $semesterMagangTerbaru
            ? Magang::where('semester_magang', $semesterMagangTerbaru)->count()
            : 0;

        $totalMahasiswa = Mahasiswa::count();
        $totalMitra = Mitra::count();
        $totalDosbing = Dosbing::count();
        $totalAdmin = Admin::count();

        // Breakdown per prodi
        $prodis = Prodi::all();
        $breakdownProdi = [];

        foreach ($prodis as $p) {
            $pMagangQuery = Magang::whereHas('mahasiswa', fn ($q) => $q->where('prodi_id', $p->prodi_id));
            $pSemesterTerbaru = (clone $pMagangQuery)->latest('semester_magang')->value('semester_magang');
            $pMagangAktif = $pSemesterTerbaru
                ? (clone $pMagangQuery)->where('semester_magang', $pSemesterTerbaru)->count()
                : 0;

            $breakdownProdi[] = [
                'prodi_id' => $p->prodi_id,
                'kode_prodi' => $p->kode_prodi,
                'nama_prodi' => $p->nama_prodi,
                'jumlah_mahasiswa' => Mahasiswa::where('prodi_id', $p->prodi_id)->count(),
                'jumlah_mahasiswa_aktif_magang' => $pMagangAktif,
                'jumlah_admin_prodi' => Admin::where('prodi_id', $p->prodi_id)->count(),
            ];
        }

        return response()->json([
            'mode' => 'agregat',
            'statistik_keseluruhan' => [
                'total_mahasiswa' => $totalMahasiswa,
                'total_mahasiswa_aktif_magang' => $totalMagangAktif,
                'semester_magang_terbaru' => $semesterMagangTerbaru,
                'total_admin_prodi' => $totalAdmin,
                'total_mitra' => $totalMitra,
                'total_dosbing' => $totalDosbing,
            ],
            'breakdown_per_prodi' => $breakdownProdi,
        ], 200);
    }
}
