<?php

namespace App\Http\Controllers;
use App\Models\Mahasiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MahasiswaController extends Controller
{
    /**
     * Tampilkan daftar mahasiswa (dengan pagination dan pencarian sederhana).
     */
    public function index(Request $request)
    {
        $query = Mahasiswa::with(['user', 'magang', 'prodi']);

        $user = $request->user();

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->where('mahasiswa_id', $mahasiswaId);
            }
        } elseif ($user->role === 'admin') {
            $prodi_id = optional($user->admin)->prodi_id;
            if ($prodi_id) {
                $query->where('prodi_id', $prodi_id);
            } else {
                // Return empty if admin has no prodi
                $query->where('prodi_id', -1);
            }
        }

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nim', 'like', "%{$search}%");
            });
        }

        if ($angkatan = $request->query('angkatan')) {
            $query->where('angkatan', $angkatan);
        }

        if ($semester_magang = $request->get('semester_magang')) {
            $query->whereHas('magang', function ($q) use ($semester_magang) {
                $q->where('semester_magang', $semester_magang);
            });
        }

        // Hanya kolom tabel ini yang boleh dipakai untuk sorting; selain itu pakai default.
        $sortable = ['mahasiswa_id', 'user_id', 'prodi_id', 'nim', 'nama', 'angkatan', 'created_at', 'updated_at'];
        $sortBy = in_array($request->query('sort_by'), $sortable, true)
            ? $request->query('sort_by')
            : 'mahasiswa_id';
        $sortOrderInput = $request->query('sort_order', 'desc');
        $sortOrder = is_string($sortOrderInput) && strtolower($sortOrderInput) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortOrder);

        $perPage = max(1, (int) $request->query('per_page', 10));
        $mahasiswa = $query->paginate($perPage);

        return response()->json($mahasiswa, 200);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'admin' && $request->has('id')) {
            $mahasiswa = Mahasiswa::with(['user', 'magang', 'prodi'])->findOrFail($request->query('id'));
            
            // Check authorization
            $prodi_id = optional($user->admin)->prodi_id;
            if ($mahasiswa->prodi_id !== $prodi_id) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }

            return response()->json($mahasiswa, 200);
        }

        $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;
        $mahasiswa = Mahasiswa::with(['prodi'])->findOrFail($mahasiswa_id);

        return response()->json($mahasiswa, 200);
    }

    public function showDetail(Request $request, $id)
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::with(['user', 'magang', 'prodi'])->findOrFail($id);

        if ($user->role === 'admin') {
            $prodi_id = optional($user->admin)->prodi_id;
            if ($mahasiswa->prodi_id !== $prodi_id) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role === 'mahasiswa') {
            if (optional($user->mahasiswa)->mahasiswa_id != $id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        }

        return response()->json($mahasiswa, 200);
    }

    /**
     * Simpan mahasiswa baru.
     */
    public function store(Request $request)
    {
        $rules = [
            'nim' => ['required', 'string', 'max:20', 'unique:mahasiswa,nim'],
            'nama' => ['required', 'string', 'max:100'],
        ];

        $messages = [
            'nim.unique' => 'Mahasiswa dengan NIM tersebut telah terdaftar'
        ];

        $validator = Validator::make($request->all(), $rules, $messages);
        if ($validator->fails()) {
            return response()->json([
                'title' => 'Validasi Gagal',
                'message' => collect($validator->errors()->all())->first()
            ], 422);
        }

        try {
            $data = $validator->validated();
            
            $userReq = $request->user();
            if ($userReq->role !== 'admin') {
                return response()->json(['message' => 'Hanya admin yang dapat menambahkan mahasiswa'], 403);
            }

            $adminProdiId = optional($userReq->admin)->prodi_id;
            if (!$adminProdiId) {
                return response()->json(['message' => 'Admin tidak memiliki prodi'], 403);
            }

            $nimParts = explode("/", $data['nim']);
            if (count($nimParts) !== 4) {
                return response()->json([
                    'message' => 'Format NIM tidak valid. Gunakan format: YY/NIU/Fakultas/NIF'
                ], 422);
            }

            $angkatan = "20" . $nimParts[0];
            $niu = $nimParts[1];
            $kodeFakultas = $nimParts[2];
            $nif = $nimParts[3];

            $user = User::firstOrCreate(
                ['username' => $niu],
                [
                    'email' => null,
                    'password' => Hash::make($nif),
                    'role' => 'mahasiswa'
                ]
            );

            $data['user_id'] = $user->user_id;
            $data['angkatan'] = $angkatan;
            $data['prodi_id'] = $adminProdiId; // Override prodi_id by admin's prodi

            $mahasiswa = Mahasiswa::create($data);

            return response()->json([
                'message' => 'Mahasiswa berhasil ditambahkan.',
                'data' => $mahasiswa->load(['user', 'magang', 'prodi'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('MahasiswaController@store error: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menambahkan data mahasiswa.'
            ], 500);
        }
    }


    /**
     * Update data mahasiswa.
     */
    public function update(Request $request)
    {
        $rules = [
            'mahasiswa_id' => ['required', 'integer', 'exists:mahasiswa,mahasiswa_id'],
            'nim' => ['required', 'string', 'max:20', Rule::unique('mahasiswa', 'nim')->ignore((int) $request->input('mahasiswa_id'), 'mahasiswa_id')],
            'nama' => ['required', 'string', 'max:100'],
            'angkatan' => ['nullable', 'integer'],
        ];

        // NIM harus unik, kecuali NIM milik mahasiswa yang sedang diubah ini.
        
        $validator = Validator::make($request->all(), $rules, [
            'nim.unique' => 'Mahasiswa dengan NIM tersebut telah terdaftar',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        try {
            $userReq = $request->user();
            $mahasiswa = Mahasiswa::findOrFail($data['mahasiswa_id']);

            if ($userReq->role === 'admin') {
                $adminProdiId = optional($userReq->admin)->prodi_id;
                if ($mahasiswa->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
                }
            } elseif ($userReq->role === 'mahasiswa') {
                 if (optional($userReq->mahasiswa)->mahasiswa_id != $mahasiswa->mahasiswa_id) {
                     return response()->json(['message' => 'Unauthorized.'], 403);
                 }
            }
            
            unset($data['mahasiswa_id']);
    
            $mahasiswa->update($data);

            // Sinkronisasi status_magang jika dikirim dari form/modal Admin
            if ($request->filled('status_magang') || $request->filled('status')) {
                $rawStatus = strtolower(trim((string) ($request->input('status_magang') ?? $request->input('status'))));
                if (in_array($rawStatus, ['berjalan', 'revisi', 'sedang magang', 'sedang'])) {
                    $statusEnum = 'berlangsung';
                } elseif (in_array($rawStatus, ['selesai magang', 'selesai'])) {
                    $statusEnum = 'selesai';
                } elseif (in_array($rawStatus, ['belum magang', 'belum', 'draft'])) {
                    $statusEnum = 'draft';
                } elseif ($rawStatus === 'ditolak') {
                    $statusEnum = 'ditolak';
                } else {
                    $statusEnum = $rawStatus;
                }

                if (in_array($statusEnum, ['draft', 'berlangsung', 'selesai', 'ditolak'])) {
                    $latestMagang = $mahasiswa->magang()->orderByDesc('magang_id')->first();
                    if ($latestMagang) {
                        $latestMagang->update(['status_magang' => $statusEnum]);
                    } elseif ($statusEnum !== 'draft') {
                        $defaultMitra = \App\Models\Mitra::first();
                        $defaultDosbing = \App\Models\Dosbing::first();
                        $latestMagang = $mahasiswa->magang()->create([
                            'mitra_id' => $defaultMitra?->mitra_id,
                            'dosbing_id' => $defaultDosbing?->dosbing_id,
                            'tahun_ajaran' => $mahasiswa->angkatan ? ((int) $mahasiswa->angkatan + 3) : (int) date('Y'),
                            'semester_magang' => 6,
                            'jumlah_magang_ke' => 1,
                            'status_magang' => $statusEnum,
                            'role_magang' => 'Intern',
                            'periode_bulan' => 6,
                        ]);
                    }
                }
            }

            // Sinkronisasi mitra & dosen jika disertakan
            if ($request->filled('mitra') || $request->filled('dosen')) {
                $latestMagang = $mahasiswa->magang()->orderByDesc('magang_id')->first();
                if ($latestMagang) {
                    if ($request->filled('mitra')) {
                        $mitraVal = $request->input('mitra');
                        $mitraId = is_numeric($mitraVal) ? (int)$mitraVal : \App\Models\Mitra::where('nama_mitra', $mitraVal)->value('mitra_id');
                        if ($mitraId) {
                            $latestMagang->update(['mitra_id' => $mitraId]);
                        }
                    }
                    if ($request->filled('dosen')) {
                        $dosenVal = $request->input('dosen');
                        $dosbingId = is_numeric($dosenVal) ? (int)$dosenVal : \App\Models\Dosbing::where('nama', $dosenVal)->value('dosbing_id');
                        if ($dosbingId) {
                            $latestMagang->update(['dosbing_id' => $dosbingId]);
                        }
                    }
                }
            }
    
            return response()->json([
                'message' => 'Mahasiswa berhasil diperbarui',
                'data' => $mahasiswa->fresh()->load(['user', 'magang', 'prodi'])
            ], 200);
            
        } catch (\Throwable $th) {
            Log::error('MahasiswaController@update error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat memperbarui data mahasiswa.',
            ], 500);
        }
    }

    /**
     * Hapus mahasiswa.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userReq = $request->user();
            $mahasiswa = Mahasiswa::findOrFail($id);

            if ($userReq->role === 'admin') {
                $adminProdiId = optional($userReq->admin)->prodi_id;
                if ($mahasiswa->prodi_id !== $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
                }
            } else {
                 return response()->json(['message' => 'Hanya admin yang dapat menghapus data.'], 403);
            }
    
            $mahasiswa->delete();
    
            return response()->json([
                'message' => 'Mahasiswa berhasil dihapus'
            ], 200);
            
        } catch (\Throwable $th) {
            Log::error('MahasiswaController@destroy error: ' . $th->getMessage(), ['exception' => $th]);
            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat menghapus data mahasiswa.',
            ], 500);
        }
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);
        
        $userReq = $request->user();
        if ($userReq->role !== 'admin') {
            return response()->json(['message' => 'Hanya admin yang dapat import data'], 403);
        }

        $adminProdiId = optional($userReq->admin)->prodi_id;
        if (!$adminProdiId) {
             return response()->json(['message' => 'Admin tidak memiliki prodi'], 403);
        }

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), "r");
        $header = fgetcsv($handle, 1000, ";");

        $errors = [];
        $imported = [];
        $lineNumber = 1;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($handle, 1000, ";")) !== false) {
                $lineNumber++;

                if (count($row) < 3) {
                    $errors[] = "Baris ke-{$lineNumber}: Data tidak lengkap";
                    continue;
                }

                [$no, $nama, $nim] = $row;

                if (empty($nim)) {
                    $errors[] = "Baris ke-{$lineNumber}: NIM tidak boleh kosong";
                    continue;
                }

                $e = Mahasiswa::where(function ($query) use ($nim) {
                    $query->where('nim', '=', $nim);
                });

                if ($e->exists()) {
                    $errors[] = "Baris ke-{$lineNumber}: NIM sudah terdaftar";
                    
                    continue;
                }

                if (empty($nama)) {
                    $errors[] = "Baris ke-{$lineNumber}: Nama tidak boleh kosong";
                    continue;
                }

                $nimParts = explode("/", $nim);

                if (count($nimParts) !== 4) {
                    $errors[] = "Baris ke-{$lineNumber}: Format NIM tidak sesuai (harus XX/XXXXX/XX/XXXXX), ditemukan: {$nim}";
                    continue;
                }

                [$tahun, $niu, $kodeFakultas, $nif] = $nimParts;

                if (!ctype_digit($tahun) || strlen($tahun) !== 2) {
                    $errors[] = "Baris ke-{$lineNumber}: Tahun angkatan harus 2 digit angka, ditemukan: {$tahun}";
                    continue;
                }

                if (!ctype_digit($niu)) {
                    $errors[] = "Baris ke-{$lineNumber}: NIU harus berupa angka, ditemukan: {$niu}";
                    continue;
                }

                if (strlen($kodeFakultas) !== 2 || !ctype_alpha($kodeFakultas)) {
                    $errors[] = "Baris ke-{$lineNumber}: Kode fakultas harus 2 huruf, ditemukan: {$kodeFakultas}";
                    continue;
                }

                if (!ctype_digit($nif)) {
                    $errors[] = "Baris ke-{$lineNumber}: NIF harus berupa angka, ditemukan: {$nif}";
                    continue;
                }

                $angkatan = "20" . $tahun;

                if ($angkatan < 2000 || $angkatan > 2030) {
                    $errors[] = "Baris ke-{$lineNumber}: Tahun angkatan tidak valid: {$angkatan}";
                    continue;
                }

                $user = User::firstOrCreate(
                    ['username' => $niu],
                    [
                        'email' => null,
                        'password' => Hash::make($nif),
                        'role' => "mahasiswa"
                    ]
                );

                $mahasiswa = Mahasiswa::firstOrCreate(
                    ['nim' => $nim],
                    [
                        'user_id' => $user->user_id,
                        'nama' => $nama,
                        'angkatan' => $angkatan,
                        'prodi_id' => $adminProdiId
                    ]
                );

                $imported[] = [
                    'nim' => $nim,
                    'nama' => $nama,
                    'angkatan' => $angkatan,
                    'prodi_id' => $adminProdiId
                ];
            }

            fclose($handle);

            if (!empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Import gagal',
                    'errors' => $errors,
                    'imported_count' => 0,
                    'failed_count' => count($errors)
                ], 422);
            }

            DB::commit();

            return response()->json([
                'message' => 'Import berhasil',
                'data' => $imported,
                'imported_count' => count($imported),
                'failed_count' => 0
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            fclose($handle);
            Log::error('MahasiswaController@import error: ' . $e->getMessage(), ['exception' => $e]);

            return response()->json([
                'message' => 'Terjadi kesalahan pada server saat import data mahasiswa.'
            ], 500);
        }
    }

}
