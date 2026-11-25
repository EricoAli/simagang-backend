<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\Dosbing;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Magang;
use App\Models\Mitra;

use Illuminate\Http\Request;

class DosbingController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Tampilkan daftar dosen pembimbing
     */
    public function index(Request $request)
    {
        $query = Dosbing::with('prodi');

        if ($request->user()?->role === 'admin') {
            $prodiId = (int) optional($request->user()->admin)->prodi_id;
            if (!$prodiId) {
                abort(403, 'Admin tidak memiliki program studi.');
            }
            $query->where('prodi_id', $prodiId);
        }

        // Filter berdasarkan nama
        if ($search = $request->query('q')) {
            // $query->where('nama', 'ilike', "%{$search}%");
            $query->whereRaw('LOWER(nama) LIKE LOWER(?)', ["%{$search}%"]);
        }

        $perPage = (int) $request->query('per_page', 15);
        $dosbing = $query->orderBy('dosbing_id')->paginate($perPage);

        return response()->json($dosbing, 200);
    }

    /**
     * Detail dosen pembimbing
     */
    public function show($id)
    {
        $dosbing = Dosbing::findOrFail($id);
        return response()->json($dosbing, 200);
    }

    /**
     * Simpan data dosen pembimbing baru
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user?->role === 'admin') {
            $prodiId = (int) optional($user->admin)->prodi_id;
            if (!$prodiId) {
                abort(403, 'Admin tidak memiliki program studi.');
            }
            // Server owns the Admin's prodi; ignore a submitted cross-prodi ID.
            $request->merge(['prodi_id' => $prodiId]);
        }

        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip'],
            'nama' => ['required', 'string', 'max:150'],
            'prodi_id' => ['required', 'integer', 'exists:prodi,prodi_id'],
        ];

        $messages = [
            'nip.numeric' => 'NIP harus berupa angka',
            'nip.digits' => 'NIP harus terdiri dari 18 digit',
            'nip.unique' => 'Dosen dengan NIP tersebut sudah ada',
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => "Validasi Gagal",
                'message' => collect($validator->errors()->all())->first(),
            ], 422);
        }

        try {
            $dosbing = Dosbing::create($validator->validated());

            return response()->json([
                'message' => 'Dosen pembimbing berhasil ditambahkan',
                'data' => $dosbing,
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Update data dosen pembimbing
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $dosbing = Dosbing::findOrFail($id);
        if ($user?->role === 'admin') {
            $prodiId = (int) optional($user->admin)->prodi_id;
            if (!$prodiId || (int) $dosbing->prodi_id !== $prodiId) {
                abort(403, 'Admin Prodi hanya dapat mengelola dosen dari program studinya.');
            }
            $request->merge(['prodi_id' => $prodiId]);
        }

        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip,' . $id . ',dosbing_id'],
            'nama' => ['required', 'string', 'max:150'],
            'prodi_id' => ['required', 'integer', 'exists:prodi,prodi_id'],
        ];

        $messages = [
            'nip.numeric' => 'NIP harus berupa angka',
            'nip.digits' => 'NIP harus terdiri dari 18 digit',
            'nip.unique' => 'Dosen dengan NIP tersebut sudah ada',
        ];

        try {
            $validator = Validator::make($request->all(), $rules, $messages);
            
            if ($validator->fails()) {
                return response()->json([
                    'title' => "Validasi Gagal",
                    'message' => collect($validator->errors()->all())->first(),
                ], 422);
            }
    
            $dosbing->update($validator->validated());
    
            return response()->json([
                'message' => 'Dosen pembimbing berhasil diperbarui',
                'data' => $dosbing->fresh(),
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Hapus dosen pembimbing
     */
    public function destroy(Request $request, $id)
    {
        $dosbing = Dosbing::findOrFail($id);
        if ($request->user()?->role === 'admin') {
            $prodiId = (int) optional($request->user()->admin)->prodi_id;
            if (!$prodiId || (int) $dosbing->prodi_id !== $prodiId) {
                abort(403, 'Admin Prodi hanya dapat mengelola dosen dari program studinya.');
            }
        }
        $dosbing->delete();

        return response()->json([
            'message' => 'Dosen pembimbing berhasil dihapus',
        ], 200);
    }

    public function getDosbingByMagangId(Request $request, $id)
    {
        $magang = Magang::with('mahasiswa')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $magang);

        $dosbingId = $magang->dosbing_id;
        $dosbing = Dosbing::where('dosbing_id', $dosbingId)->first();
        return response()->json($dosbing, 200);

    }

    public function jumlahDosbing()
    {
        $jumlahDosbing = Dosbing::count();
        return response()->json(['jumlah_dosbing' => $jumlahDosbing], 200);
    }
}
