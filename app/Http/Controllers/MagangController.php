<?php

namespace App\Http\Controllers;
use App\Exports\MagangExport;
use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;


use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class MagangController extends Controller
{
    /**
     * Tampilkan daftar magang (dengan pagination, filter, dan pencarian).
     */
    public function index(Request $request)
    {
        $user = $request->user(); // ambil user dari token
        $mahasiswa = $user->mahasiswa; // jika ada relasi di model User

        $query = Magang::with(['mahasiswa', 'mitra', 'dosenPembimbing']);

        // Filter otomatis jika role adalah mahasiswa
        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->where('mahasiswa_id', $mahasiswaId)->orderBy("created_at");
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'admin') {
            $prodiId = optional($user->admin)->prodi_id;
            if ($prodiId) {
                $query->whereHas('mahasiswa', function ($q) use ($prodiId) {
                    $q->where('prodi_id', $prodiId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            if ($mitraId) {
                $query->where('mitra_id', $mitraId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // 🔎 Filter manual berdasarkan query param (admin bisa gunakan ini)
        if ($mahasiswaId = $request->query('mahasiswa_id')) {
            $query->where('mahasiswa_id', $mahasiswaId);
        }

        if ($mitraId = $request->query('mitra_id')) {
            $query->where('mitra_id', $mitraId);
        }

        if ($angkatan = $request->query('angkatan')) {
            $query->whereHas('mahasiswa', function ($q) use ($angkatan) {
                $q->where('angkatan', $angkatan);
            });
        }

        if ($semester_magang = $request->query('semester_magang')) {
            $query->where('semester_magang', $semester_magang);
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('jobdesk', 'like', "%{$search}%")
                    ->orWhere('role_magang', 'like', "%{$search}%");
            });
        }

        // 🔎 Filter status penilaian (nilai mitra)
        if ($penilaian = $request->query('penilaian')) {
            $user = $request->user();
            $mitra_id = optional($user->mitra)->mitra_id;
            if ($penilaian === 'none') {
                $query->whereDoesntHave('penilaianMitra')
                    ->where('mitra_id', $mitra_id);
            } elseif ($penilaian === 'exist') {
                $query->whereHas('penilaianMitra')
                    ->where('mitra_id', $mitra_id);
            }
        }

        $perPage = (int) $request->query('per_page', 10);
        $magang = $query->paginate($perPage);

        return response()->json($magang, 200);
    }

    /**
     * Tampilkan detail magang berdasarkan id (magang_id).
     */
    public function show(Request $request, $id)
    {
        $magang = Magang::with(['mahasiswa', 'mitra', 'dosenPembimbing'])->findOrFail($id);

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

        return response()->json($magang, 200);
    }

    /**
     * Simpan magang baru.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if ($user->role !== 'mahasiswa' || !$user->mahasiswa) {
            return response()->json(['message' => 'Hanya mahasiswa yang dapat mendaftarkan magang'], 403);
        }

        $mahasiswa_id = $user->mahasiswa->mahasiswa_id;

        $rules = [
            'mitra_id' => ['nullable', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable'],
            'semester_magang' => [
                'sometimes',
                'integer',
                Rule::in([6, 7]),
                function ($attribute, $value, $fail) use ($mahasiswa_id) {
                    $hasMagang = Magang::where('mahasiswa_id', $mahasiswa_id)
                        ->where('semester_magang', $value)
                        ->exists();

                    if ($hasMagang) {
                        $fail("Magang untuk semester {$value} sudah pernah didaftarkan.");
                    }
                },
            ],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'dokumenPenerimaan' => 'required|file|mimes:pdf|max:5120',
            'dokumenPraKRS' => 'required|file|mimes:pdf|max:5120'
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $data['mahasiswa_id'] = $mahasiswa_id;

        // upload penerimaan ke private disk
        $filePenerimaan = $request->file('dokumenPenerimaan');
        $namePenerimaan = $filePenerimaan->getClientOriginalName();
        $extPenerimaan = $filePenerimaan->getClientOriginalExtension();
        $basePenerimaan = pathinfo($namePenerimaan, PATHINFO_FILENAME);
        $fileNamePenerimaan = $basePenerimaan . "_" . time() . "." . $extPenerimaan;
        $pathPenerimaan = $filePenerimaan->storeAs("dokumen-penerimaan/{$mahasiswa_id}", $fileNamePenerimaan, 'local');

        // upload pra KRS ke private disk
        $filePraKrs = $request->file('dokumenPraKRS');
        $namaPraKRS = $filePraKrs->getClientOriginalName();
        $extPraKRS = $filePraKrs->getClientOriginalExtension();
        $basePraKRS = pathinfo($namaPraKRS, PATHINFO_FILENAME);
        $fileNamePraKRS = $basePraKRS . "_" . time() . "." . $extPraKRS;
        $pathPraKrs = $filePraKrs->storeAs("dokumen-pra-krs/{$mahasiswa_id}", $fileNamePraKRS, 'local');

        // create magang dulu
        unset($data['dokumenPenerimaan'], $data['dokumenPraKRS']);
        $magang = Magang::create($data);

        // insert ke dokumen_magang (2 record)

        DokumenMagang::create([
            'magang_id' => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'file_path' => $pathPenerimaan,
            'nama_file' => $namePenerimaan,
            'ukuran_file' => $filePenerimaan->getSize()
        ]);

        DokumenMagang::create([
            'magang_id' => $magang->magang_id,
            'jenis_dokumen' => 'doc_pra_krs',
            'file_path' => $pathPraKrs,
            'nama_file' => $namaPraKRS,
            'ukuran_file' => $filePraKrs->getSize()
        ]);

        return response()->json([
            'message' => 'Data magang berhasil dibuat',
            'data' => $magang->load(['mahasiswa', 'mitra', 'dosenPembimbing'])
        ], 201);
    }

    /**
     * Update data magang.
     */
    public function update(Request $request, $id)
    {
        $magang = Magang::with(['dokumenMagang', 'mahasiswa'])->findOrFail($id);

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
        } else {
            return response()->json(['message' => 'Unauthorized. Role tidak diizinkan mengubah data magang.'], 403);
        }

        $rules = [
            'mahasiswa_id' => ['sometimes', 'exists:mahasiswa,mahasiswa_id'],
            'mitra_id' => ['sometimes', 'exists:mitra,mitra_id'],
            'dosbing_id' => ['nullable', 'exists:dosen_pembimbing,dosbing_id'],
            'semester_magang' => ['sometimes', 'integer', Rule::in([6, 7])],
            'role_magang' => ['nullable', 'string', 'max:100'],
            'jobdesk' => ['nullable', 'string'],
            'periode_bulan' => ['nullable', 'integer', 'min:1'],
            'dokumenPenerimaan' => 'sometimes|file|mimes:pdf|max:5120',
            'dokumenPraKRS' => 'sometimes|file|mimes:pdf|max:5120',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $mahasiswa_id = $magang->mahasiswa_id;

        $magang->update(collect($data)->except(['dokumenPenerimaan', 'dokumenPraKRS'])->toArray());

        // update dokumen penerimaan
        if ($request->hasFile('dokumenPenerimaan')) {
            $filePenerimaan = $request->file('dokumenPenerimaan');
            $namePenerimaan = $filePenerimaan->getClientOriginalName();
            $extPenerimaan = $filePenerimaan->getClientOriginalExtension();
            $basePenerimaan = pathinfo($namePenerimaan, PATHINFO_FILENAME);
            $fileNamePenerimaan = $basePenerimaan . "_" . time() . "." . $extPenerimaan;
            $pathPenerimaan = $filePenerimaan->storeAs("dokumen-penerimaan/{$mahasiswa_id}", $fileNamePenerimaan, 'local');

            $dokumen = $magang->dokumenMagang()->where('jenis_dokumen', 'doc_surat_penerimaan')->first();

            if ($dokumen && !empty($dokumen->file_path)) {
                if (Storage::disk('local')->exists($dokumen->file_path)) {
                    Storage::disk('local')->delete($dokumen->file_path);
                } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                    Storage::disk('public')->delete($dokumen->file_path);
                }
            }

            $magang->dokumenMagang()->updateOrCreate(
                ['jenis_dokumen' => 'doc_surat_penerimaan'],
                [
                    'file_path' => $pathPenerimaan,
                    'nama_file' => $namePenerimaan,
                    'ukuran_file' => $filePenerimaan->getSize()
                ]
            );
        }

        // update dokumen pra KRS
        if ($request->hasFile('dokumenPraKRS')) {
            $filePraKrs = $request->file('dokumenPraKRS');
            $namaPraKRS = $filePraKrs->getClientOriginalName();
            $extPraKRS = $filePraKrs->getClientOriginalExtension();
            $basePraKRS = pathinfo($namaPraKRS, PATHINFO_FILENAME);
            $fileNamePraKRS = $basePraKRS . "_" . time() . "." . $extPraKRS;
            $pathPraKrs = $filePraKrs->storeAs("dokumen-pra-krs/{$mahasiswa_id}", $fileNamePraKRS, 'local');

            $dokumen = $magang->dokumenMagang()->where('jenis_dokumen', 'doc_pra_krs')->first();

            if ($dokumen && !empty($dokumen->file_path)) {
                if (Storage::disk('local')->exists($dokumen->file_path)) {
                    Storage::disk('local')->delete($dokumen->file_path);
                } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                    Storage::disk('public')->delete($dokumen->file_path);
                }
            }

            $magang->dokumenMagang()->updateOrCreate(
                ['jenis_dokumen' => 'doc_pra_krs'],
                [
                    'file_path' => $pathPraKrs,
                    'nama_file' => $namaPraKRS,
                    'ukuran_file' => $filePraKrs->getSize()
                ]
            );
        }

        return response()->json([
            'message' => 'Data magang berhasil diperbarui',
            'data' => $magang->fresh()->load([
                'mahasiswa',
                'mitra',
                'dosenPembimbing',
                'dokumenMagang'
            ])
        ], 200);
    }

    /**
     * Hapus data magang.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $magang = Magang::with('mahasiswa')->findOrFail($id);

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
            } else {
                return response()->json(['message' => 'Unauthorized. Role tidak diizinkan menghapus data magang.'], 403);
            }

            DB::transaction(function () use ($magang) {
                $magang->delete();
            });

            return response()->json([
                'message' => 'Data magang berhasil dihapus'
            ], 200);

        } catch (\Throwable $th) {
            Log::error('MagangController@destroy error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menghapus data magang.',
            ], 500);
        }
    }

    public function showDetail(Request $request, $id)
    {
        $magang = Magang::with(['mahasiswa', 'mitra', 'dosenPembimbing'])
            ->findOrFail($id);

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

        // Logbook
        $logbook = Logbook::with('fotoKegiatan')
            ->where('magang_id', $id)
            ->orderByDesc('logbook_id')
            ->get();

        // Penilaian mitra (ambil first karena per magang biasanya satu)
        $penilaian = NilaiMitra::where('magang_id', $id)->first();

        // Dokumen magang (hanya penerimaan & pra-krs)
        $dokumen = DokumenMagang::where('magang_id', $id)
            ->whereIn('jenis_dokumen', ['doc_surat_penerimaan', 'doc_pra_krs'])
            ->orderByDesc('dokumen_id')
            ->get();

        // Laporan magang
        $laporan = Laporan::where('magang_id', $id)
            ->get();

        return response()->json([
            'detail' => $magang,
            'logbook' => $logbook,
            'penilaian' => $penilaian,
            'dokumen' => $dokumen,
            'laporan' => $laporan,
        ], 200);
    }

    public function exportExcel(Request $request)
    {
        $angkatan = $request->query('angkatan');
        $semesterMagang = $request->query('semester_magang');

        $fileName = "data-magang-" . $angkatan . "-" . $semesterMagang . "-" . Carbon::now()->format('ymdHi');
        $fileName .= '.xlsx';

        return Excel::download(new MagangExport($angkatan, $semesterMagang), $fileName);
    }


    /**
     * Menghitung jumlah mahasiswa yang sedang magang (status: berlangsung)
     * pada periode (tahun ajaran) terakhir.
     */
    // public function jumlahMagangAktif()
    // {
    //     // 1. Tentukan tahun ajaran terbaru dari data magang yang ada
    //     $tahunAjaranTerbaru = Magang::latest('tahun_ajaran')->value('tahun_ajaran');

    //     if (!$tahunAjaranTerbaru) {
    //         // Jika tidak ada data magang sama sekali, kembalikan 0
    //         return response()->json(['jumlah_aktif' => 0], 200);
    //     }

    //     // 2. Hitung jumlah magang yang 'berlangsung' pada tahun ajaran tersebut
    //     $jumlahAktif = Magang::where('tahun_ajaran', $tahunAjaranTerbaru)
    //         ->where('status_magang', 'berlangsung')
    //         ->count();

    //     return response()->json(['jumlah_aktif' => $jumlahAktif], 200);
    // }
}
