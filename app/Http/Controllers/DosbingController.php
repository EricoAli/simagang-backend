<?php

namespace App\Http\Controllers;

use App\Models\Dosbing;
use App\Models\Magang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DosbingController extends Controller
{
    /**
     * Tampilkan daftar dosen pembimbing.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Dosbing::with('prodi');

        if ($prodiId = $request->query('prodi_id')) {
            $query->where('prodi_id', $prodiId);
        } elseif ($user && $user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($adminProdiId) {
                $query->where(function ($q) use ($adminProdiId) {
                    $q->where('prodi_id', $adminProdiId)
                        ->orWhereNull('prodi_id');
                });
            }
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(nama) LIKE LOWER(?)', ["%{$search}%"])
                    ->orWhere('nip', 'like', "%{$search}%");
            });
        }

        $perPage = min(max((int) $request->query('per_page', 15), 1), 500);
        $dosbing = $query->orderBy('dosbing_id')->paginate($perPage);

        return response()->json($dosbing, 200);
    }

    /**
     * Detail dosen pembimbing.
     */
    public function show($id)
    {
        $dosbing = Dosbing::with('prodi')->findOrFail($id);

        return response()->json($dosbing, 200);
    }

    /**
     * Simpan data dosen pembimbing baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola dosen pembimbing.'], 403);
        }

        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip'],
            'nama' => ['required', 'string', 'max:150'],
            'prodi_id' => ['nullable', 'integer', 'exists:prodi,prodi_id'],
        ];

        $messages = [
            'nip.numeric' => 'NIP harus berupa angka',
            'nip.digits' => 'NIP harus terdiri dari 18 digit',
            'nip.unique' => 'Dosen dengan NIP tersebut sudah ada',
            'prodi_id.exists' => 'Program studi tidak valid',
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
        if ($user->role === 'admin') {
            $data['prodi_id'] = optional($user->admin)->prodi_id;
        }

        try {
            $dosbing = Dosbing::create($data);

            return response()->json([
                'message' => 'Dosen pembimbing berhasil ditambahkan',
                'data' => $dosbing->load('prodi'),
            ], 201);
        } catch (\Throwable $th) {
            Log::error('DosbingController@store error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menambahkan dosen pembimbing.',
            ], 500);
        }
    }

    /**
     * Update data dosen pembimbing.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat mengelola dosen pembimbing.'], 403);
        }

        $dosbing = Dosbing::findOrFail($id);
        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($dosbing->prodi_id && $dosbing->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dosen pembimbing bukan dari prodi anda.'], 403);
            }
        }

        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip,'.$id.',dosbing_id'],
            'nama' => ['required', 'string', 'max:150'],
            'prodi_id' => ['nullable', 'integer', 'exists:prodi,prodi_id'],
        ];

        $messages = [
            'nip.numeric' => 'NIP harus berupa angka',
            'nip.digits' => 'NIP harus terdiri dari 18 digit',
            'nip.unique' => 'Dosen dengan NIP tersebut sudah ada',
            'prodi_id.exists' => 'Program studi tidak valid',
        ];

        try {
            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                return response()->json([
                    'title' => 'Validasi Gagal',
                    'message' => collect($validator->errors()->all())->first(),
                    'errors' => $validator->errors(),
                ], 422);
            }

            $data = $validator->validated();
            if ($user->role === 'admin') {
                $data['prodi_id'] = optional($user->admin)->prodi_id;
            }

            $dosbing->update($data);

            return response()->json([
                'message' => 'Dosen pembimbing berhasil diperbarui',
                'data' => $dosbing->fresh()->load('prodi'),
            ], 200);
        } catch (\Throwable $th) {
            Log::error('DosbingController@update error: '.$th->getMessage(), ['exception' => $th]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui dosen pembimbing.',
            ], 500);
        }
    }

    /**
     * Hapus dosen pembimbing.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Hanya admin yang dapat menghapus dosen pembimbing.'], 403);
        }

        $dosbing = Dosbing::findOrFail($id);
        if ($user->role === 'admin') {
            $adminProdiId = optional($user->admin)->prodi_id;
            if ($dosbing->prodi_id && $dosbing->prodi_id !== $adminProdiId) {
                return response()->json(['message' => 'Unauthorized. Dosen pembimbing bukan dari prodi anda.'], 403);
            }
        }

        $dosbing->delete();

        return response()->json([
            'message' => 'Dosen pembimbing berhasil dihapus',
        ], 200);
    }

    public function getDosbingByMagangId($id)
    {
        $magang = Magang::findOrFail($id);
        $dosbing = $magang->dosbing_id
            ? Dosbing::with('prodi')->where('dosbing_id', $magang->dosbing_id)->first()
            : null;

        return response()->json($dosbing, 200);
    }

    public function jumlahDosbing()
    {
        $jumlahDosbing = Dosbing::count();

        return response()->json(['jumlah_dosbing' => $jumlahDosbing], 200);
    }
}
