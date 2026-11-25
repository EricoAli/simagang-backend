<?php

namespace App\Http\Controllers;

use App\Models\Prodi;

class ProdiController extends Controller
{
    /**
     * Daftar program studi (read-only) untuk kebutuhan dropdown/relasi.
     */
    public function index()
    {
        $prodi = Prodi::orderBy('prodi_id')->get();

        return response()->json([
            'data' => $prodi,
        ], 200);
    }
}
