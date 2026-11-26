<?php

namespace App\Http\Controllers;

use App\Models\Prodi;

class ProdiController extends Controller
{
    /**
     * Tampilkan daftar seluruh program studi DTEDI.
     */
    public function index()
    {
        $prodi = Prodi::orderBy('prodi_id')->get(['prodi_id', 'kode_prodi', 'nama_prodi']);

        return response()->json([
            'data' => $prodi,
        ], 200);
    }
}
