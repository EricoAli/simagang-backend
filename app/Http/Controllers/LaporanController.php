<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\Laporan;
use App\Models\Magang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LaporanController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Laporan::with('magang');

        $this->scopeByMagangAccess($query, $request->user(), 'magang');

        return response()->json($query->get(), 200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $rules = [
            'magang_id' => 'required|exists:magang,magang_id',
            'file' => 'required|file|mimes:pdf|max:5048',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $this->authorizeMagangAccess(
            $request->user(),
            Magang::findOrFail($request->magang_id)
        );

        // $magang = Magang::where('magang_id', $request->magang_id)
        //     ->where('mahasiswa_id', auth()->id())
        //     ->firstOrFail();

        // // 🔒 Guard bisnis
        // if ($magang->laporan) {
        //     return response()->json([
        //         'message' => 'Laporan magang sudah ada'
        //     ], 409);
        // }

        $magang_id = $request->magang_id;
        $file = $request->file('file');
        $namaFile = $file->getClientOriginalName();
        $extFile = $file->getClientOriginalExtension();
        $base = pathinfo($namaFile, PATHINFO_FILENAME);
        $fileName = $base . "_" . time() . "." . $extFile;
        $path = $file->storeAs("laporan-magang/{$magang_id}", $fileName, 'public');

        $laporan = Laporan::create([
            'magang_id' => $magang_id,
            'file_path' => $path,
            'nama_file' => $namaFile,
        ]);

        return response()->json([
            'message' => 'Laporan magang berhasil diunggah',
            'data' => $laporan
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $laporan = Laporan::with('magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $laporan->magang);

        return response()->json($laporan);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5048',
        ]);

        $laporan = Laporan::with('magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $laporan->magang);

        // hapus file lama
        Storage::disk('public')->delete($laporan->file_path);

        $file = $request->file('file');
        $namaFile = $file->getClientOriginalName();
        $extFile = $file->getClientOriginalExtension();
        $base = pathinfo($namaFile, PATHINFO_FILENAME);
        $fileName = $base . "_" . time() . "." . $extFile;
        $path = $file->storeAs("laporan-magang/{$laporan->magang_id}", $fileName, 'public');

        $laporan->update([
            'file_path' => $path,
            'nama_file' => $fileName
        ]);

        return response()->json([
            'message' => 'Laporan magang berhasil diperbarui',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $laporan = Laporan::with('magang')->findOrFail($id);

        $this->authorizeMagangAccess($request->user(), $laporan->magang);

        Storage::disk('public')->delete($laporan->file_path);
        $laporan->delete();

        return response()->json([
            'message' => 'Laporan magang berhasil dihapus'
        ]);
    }
}
