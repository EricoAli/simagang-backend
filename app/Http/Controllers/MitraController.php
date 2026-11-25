<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\Mitra;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\Magang;
use App\Models\User;
use Illuminate\Support\Str;

use Illuminate\Http\Request;

class MitraController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Tampilkan daftar mitra (dengan pagination dan pencarian sederhana).
     */
    public function index(Request $request)
    {
        $query = Mitra::with(['magang']);


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
        $rules = [
            'nama_mitra' => ['required', 'string', 'max:150', 'unique:mitra,nama_mitra'],
            'email' => ['required', 'email', 'max:100', 'unique:mitra,email'],
            'narahubung' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            $mitra = DB::transaction(function () use ($data) {
                $username = Str::before($data['email'], '@');

                $user = User::firstOrCreate(
                    ['email' => $data['email']],
                    [
                        'email' => $data['email'],
                        'username' => $username,
                        'password' => $username,
                        'role' => 'mitra',
                    ]
                );

                $data['user_id'] = $user->user_id;

                return Mitra::create($data)->fresh();
            });

            return response()->json([
                'message' => 'Mitra berhasil dibuat',
                'data' => $mitra,
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal menyimpan data Mitra.',
            ], 500);
        }
    }

    /**
     * Update data mitra.
     */
    public function update(Request $request, $id)
    {
        $mitra = Mitra::findOrFail($id);
        $rules = [
            'nama_mitra' => ['required', 'string', 'max:150', Rule::unique('mitra', 'nama_mitra')->ignore($mitra->mitra_id, 'mitra_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('mitra', 'email')->ignore($mitra->mitra_id, 'mitra_id')],
            'narahubung' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:50'],
        ];

        $messages = [
            'nama_mitra.unique' => 'Mitra dengan nama tersebut sudah ada',
            'email.email' => 'Format email tidak valid',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        try {
            DB::transaction(function () use ($mitra, $data) {
                $mitra->update($data);
            });

            return response()->json([
                'message' => 'Mitra berhasil diperbarui',
                'data' => $mitra->fresh()->load(['magang']),
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal menyimpan perubahan data mitra.',
            ], 500);
        }

    }

    /**
     * Hapus mitra.
     */
    public function destroy($id)
    {
        $mitra = Mitra::findOrFail($id);

        Magang::where('mitra_id', $id)->update(['mitra_id' => null]);

        $mitra->delete();

        return response()->json([
            'message' => 'Mitra berhasil dihapus'
        ], 200);


    }

    public function getMitraByMagang(Request $request, $id)
    {
        $magang = Magang::with('mahasiswa')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $magang);

        $mitraId = $magang->mitra_id;
        $mitra = Mitra::where('mitra_id', $mitraId)->first();
        return response()->json($mitra, 200);


    }

    public function jumlahMitra()
    {
        $jumlah = Mitra::count();
        return response()->json(['jumlah_mitra' => $jumlah], 200);
    }
}
