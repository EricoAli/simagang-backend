<?php

namespace App\Http\Controllers;

use App\Models\Dosbing;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\Magang;
use App\Models\Mitra;

use Illuminate\Http\Request;

class DosbingController extends Controller
{
    /**
     * Tampilkan daftar dosen pembimbing
     */
    public function index(Request $request)
    {
        $query = Dosbing::query();

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
        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip'],
            'nama' => ['required', 'string', 'max:150'],
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
            Log::error('DosbingController@store error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menambahkan dosen pembimbing.',
            ], 500);
        }
    }

    /**
     * Update data dosen pembimbing
     */
    public function update(Request $request, $id)
    {
        $rules = [
            'nip' => ['required', 'string', 'numeric', 'digits:18', 'unique:dosen_pembimbing,nip,' . $id . ',dosbing_id'],
            'nama' => ['required', 'string', 'max:150'],
        ];

        $messages = [
            'nip.numeric' => 'NIP harus berupa angka',
            'nip.digits' => 'NIP harus terdiri dari 18 digit',
            'nip.unique' => 'Dosen dengan NIP tersebut sudah ada',
        ];

        try {
            $dosbing = Dosbing::findOrFail($id);
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
            Log::error('DosbingController@update error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui dosen pembimbing.',
            ], 500);
        }
    }

    /**
     * Hapus dosen pembimbing
     */
    public function destroy($id)
    {
        $dosbing = Dosbing::findOrFail($id);
        $dosbing->delete();

        return response()->json([
            'message' => 'Dosen pembimbing berhasil dihapus',
        ], 200);
    }

    public function getDosbingByMagangId($id)
    {
        $magang = Magang::where('magang_id', $id)->first();
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
