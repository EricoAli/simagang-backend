<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Concerns\AuthorizesMagangAccess;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MahasiswaController extends Controller
{
    use AuthorizesMagangAccess;

    /**
     * Tampilkan daftar mahasiswa (dengan pagination dan pencarian sederhana).
     */
    public function index(Request $request)
    {
        $query = Mahasiswa::with([
            'user',
            'prodi',
            'magang.periode.tahunAjaran',
            'magang.mitra',
            'magang.dosenPembimbing',
        ]);

        $user = $request->user();
        $requestedProdiId = null;

        if ($request->filled('prodi_id')) {
            $prodiValidator = Validator::make($request->only('prodi_id'), [
                'prodi_id' => ['integer', 'exists:prodi,prodi_id'],
            ]);

            if ($prodiValidator->fails()) {
                return response()->json([
                    'message' => 'Validasi gagal.',
                    'errors' => $prodiValidator->errors(),
                ], 422);
            }

            $requestedProdiId = (int) $request->query('prodi_id');
        }

        $periodValidator = Validator::make(
            $request->only(['periode_id', 'tahun_ajaran_id', 'semester_magang']),
            [
                'periode_id' => ['nullable', 'integer', 'exists:periode_magang,periode_id'],
                'tahun_ajaran_id' => ['nullable', 'integer', 'exists:tahun_ajaran,tahun_ajaran_id'],
                'semester_magang' => ['nullable', 'integer', 'min:1'],
            ],
            [
                'periode_id.exists' => 'Periode magang tidak ditemukan.',
                'tahun_ajaran_id.exists' => 'Tahun ajaran tidak ditemukan.',
            ]
        );

        if ($periodValidator->fails()) {
            return response()->json([
                'message' => 'Validasi gagal.',
                'errors' => $periodValidator->errors(),
            ], 422);
        }

        $periodeId = $request->filled('periode_id')
            ? (int) $request->query('periode_id')
            : null;
        $tahunAjaranId = $request->filled('tahun_ajaran_id')
            ? (int) $request->query('tahun_ajaran_id')
            : null;
        $semesterFilter = $request->filled('semester_magang')
            ? (int) $request->query('semester_magang')
            : null;
        $hasPeriodFilter = $periodeId !== null
            || $tahunAjaranId !== null
            || $semesterFilter !== null;

        $applyPeriodFilter = function ($q) use (
            $periodeId,
            $tahunAjaranId,
            $semesterFilter
        ) {
            if ($periodeId !== null) {
                $q->where('periode_id', $periodeId);
            }
            if ($tahunAjaranId !== null) {
                $q->whereHas('periode', function ($periodQuery) use ($tahunAjaranId) {
                    $periodQuery->where('tahun_ajaran_id', $tahunAjaranId);
                });
            }
            if ($semesterFilter !== null) {
                $q->where('semester_magang', $semesterFilter);
            }

            return $q;
        };

        if ($user->role === 'mahasiswa') {
            $mahasiswaId = optional($user->mahasiswa)->mahasiswa_id;
            if ($mahasiswaId) {
                $query->where('mahasiswa_id', $mahasiswaId);
            }
        } elseif ($user->role === 'super_admin') {
            // Super Admin: seluruh prodi, tanpa pembatasan.
        } elseif ($user->role === 'admin') {
            $prodi_id = optional($user->admin)->prodi_id;
            if ($requestedProdiId !== null && (int) $prodi_id !== $requestedProdiId) {
                return response()->json([
                    'message' => 'Admin Prodi hanya dapat melihat data program studinya.',
                ], 403);
            }

            if ($prodi_id) {
                $query->where('prodi_id', $prodi_id);
            } else {
                // Return empty if admin has no prodi
                $query->where('prodi_id', -1);
            }
        } elseif ($user->role === 'mitra') {
            // Mitra hanya melihat mahasiswa yang magang di mitranya.
            $mitraId = optional($user->mitra)->mitra_id;
            if ($mitraId) {
                // Terapkan mitra dan filter periode pada baris magang yang
                // sama agar data antarperiode tidak tercampur.
                $query->whereHas('magang', function ($q) use ($mitraId, $applyPeriodFilter) {
                    $q->where('mitra_id', $mitraId);
                    $applyPeriodFilter($q);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            // Dosbing belum terhubung ke akun user; jangan bocorkan data.
            $query->whereRaw('1 = 0');
        }

        // Query filter only narrows the role-scoped query. Admin scope above
        // is always taken from the authenticated account.
        if ($requestedProdiId !== null && $user->role !== 'admin') {
            $query->where('prodi_id', $requestedProdiId);
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

        if ($hasPeriodFilter && $user->role !== 'mitra') {
            $query->whereHas('magang', $applyPeriodFilter);
        }

        $sortBy = $request->query('sort_by', 'mahasiswa_id');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = (int) $request->query('per_page', 10);
        $mahasiswa = $query->paginate($perPage);

        return response()->json($mahasiswa, 200);
    }

    public function show(Request $request)
    {
        $user = $request->user();

        if ($this->isAdminRole($user) && $request->has('id')) {
            $mahasiswa = Mahasiswa::with([
                'user',
                'prodi',
                'magang.periode.tahunAjaran',
                'magang.mitra',
                'magang.dosenPembimbing',
            ])->findOrFail($request->query('id'));
            
            // Admin Prodi tetap dibatasi; Super Admin melewati pemeriksaan ini.
            if ($user->role === 'admin') {
                $prodi_id = optional($user->admin)->prodi_id;
                if (!$prodi_id || (int) $mahasiswa->prodi_id !== (int) $prodi_id) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
                }
            }

            return response()->json($mahasiswa, 200);
        }

        $mahasiswa_id = optional($user->mahasiswa)->mahasiswa_id;
        $mahasiswa = Mahasiswa::with([
            'prodi',
            'magang.periode.tahunAjaran',
            'magang.mitra',
            'magang.dosenPembimbing',
        ])->findOrFail($mahasiswa_id);

        return response()->json($mahasiswa, 200);
    }

    public function showDetail(Request $request, $id)
    {
        $user = $request->user();
        $mahasiswa = Mahasiswa::with([
            'user',
            'prodi',
            'magang.periode.tahunAjaran',
            'magang.mitra',
            'magang.dosenPembimbing',
        ])->findOrFail($id);

        if ($user->role === 'super_admin') {
            // Super Admin: seluruh prodi.
        } elseif ($user->role === 'admin') {
            $prodi_id = optional($user->admin)->prodi_id;
            if (!$prodi_id || (int) $mahasiswa->prodi_id !== (int) $prodi_id) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
            }
        } elseif ($user->role === 'mahasiswa') {
            if (optional($user->mahasiswa)->mahasiswa_id != $id) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }
        } elseif ($user->role === 'mitra') {
            $mitraId = optional($user->mitra)->mitra_id;
            $ownsStudent = $mitraId && $mahasiswa->magang()
                ->where('mitra_id', $mitraId)
                ->exists();

            if (!$ownsStudent) {
                return response()->json(['message' => 'Unauthorized. Mahasiswa bukan peserta magang mitra anda.'], 403);
            }
        } else {
            // Dosbing dan role lain tidak berhak atas master mahasiswa.
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json($mahasiswa, 200);
    }

    /**
     * Simpan mahasiswa baru.
     */
    public function store(Request $request)
    {
        $rules = [
            'nim' => ['required', 'string', 'max:50', 'unique:mahasiswa,nim'],
            'nama' => ['required', 'string', 'max:255'],
            'prodi_id' => ['nullable', 'integer', 'exists:prodi,prodi_id'],
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
            if (!$this->isAdminRole($userReq)) {
                return response()->json(['message' => 'Hanya admin yang dapat menambahkan mahasiswa'], 403);
            }

            // Super Admin wajib memilih prodi tujuan secara eksplisit.
            // Admin Prodi selalu memakai prodi miliknya dan tidak boleh
            // memanipulasi prodi_id dari request.
            if ($this->isSuperAdmin($userReq)) {
                if (!$request->filled('prodi_id')) {
                    return response()->json([
                        'message' => 'Super Admin wajib memilih program studi mahasiswa.'
                    ], 422);
                }

                $targetProdiId = (int) $request->input('prodi_id');
            } else {
                $adminProdiId = optional($userReq->admin)->prodi_id;
                if (!$adminProdiId) {
                    return response()->json(['message' => 'Admin tidak memiliki prodi'], 403);
                }

                // Jangan percaya prodi_id dari Frontend: tolak jika berbeda,
                // lalu paksa memakai prodi Admin yang login.
                if ($request->filled('prodi_id')
                    && (int) $request->input('prodi_id') !== (int) $adminProdiId) {
                    return response()->json([
                        'message' => 'Anda hanya dapat menambahkan mahasiswa pada program studi Anda.'
                    ], 403);
                }

                $targetProdiId = (int) $adminProdiId;
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
            $data['prodi_id'] = $targetProdiId;

            $mahasiswa = Mahasiswa::create($data);

            return response()->json([
                'message' => 'Mahasiswa berhasil ditambahkan.',
                'data' => $mahasiswa->load([
                    'user',
                    'prodi',
                    'magang.periode.tahunAjaran',
                ])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
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
            'nim' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:255'],
            'angkatan' => ['nullable', 'integer'],
            'prodi_id' => ['nullable', 'integer', 'exists:prodi,prodi_id'],
        ];

        // Validasi NIM uniqueness tapi ignore untuk id ini
        
        $validator = Validator::make($request->all(), $rules);

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

            if ($this->isSuperAdmin($userReq)) {
                // Super Admin boleh mengedit lintas prodi dan memindahkan
                // mahasiswa ke prodi lain (prodi_id tervalidasi exists).
            } elseif ($userReq->role === 'admin') {
                $adminProdiId = optional($userReq->admin)->prodi_id;
                if (!$adminProdiId || (int) $mahasiswa->prodi_id !== (int) $adminProdiId) {
                    return response()->json(['message' => 'Unauthorized. Mahasiswa bukan dari prodi anda.'], 403);
                }

                // Admin tidak boleh memindahkan mahasiswa ke prodi lain.
                if ($request->filled('prodi_id')
                    && (int) $request->input('prodi_id') !== (int) $mahasiswa->prodi_id) {
                    return response()->json([
                        'message' => 'Program studi mahasiswa tidak dapat diubah.'
                    ], 403);
                }
                unset($data['prodi_id']);
            } elseif ($userReq->role === 'mahasiswa') {
                 if (optional($userReq->mahasiswa)->mahasiswa_id != $mahasiswa->mahasiswa_id) {
                     return response()->json(['message' => 'Unauthorized.'], 403);
                 }
                 unset($data['prodi_id']);
            }
            
            unset($data['mahasiswa_id']);

            $mahasiswa->update($data);
    
            return response()->json([
                'message' => 'Mahasiswa berhasil diperbarui',
                'data' => $mahasiswa->fresh()->load([
                    'user',
                    'prodi',
                    'magang.periode.tahunAjaran',
                ])
            ], 200);
            
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
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

            if ($this->isSuperAdmin($userReq)) {
                // Super Admin boleh menghapus lintas prodi.
            } elseif ($userReq->role === 'admin') {
                $adminProdiId = optional($userReq->admin)->prodi_id;
                if (!$adminProdiId || (int) $mahasiswa->prodi_id !== (int) $adminProdiId) {
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
            return response()->json([
                'message' => $th->getMessage(),
            ], 500);
        }
    }


    public function import(Request $request)
    {
        $userReq = $request->user();
        if (!$this->isAdminRole($userReq)) {
            return response()->json(['message' => 'Hanya admin yang dapat import data'], 403);
        }

        $request->validate(['file' => 'required|file|mimes:csv,txt|max:10240']);

        $adminProdiId = null;
        if (!$this->isSuperAdmin($userReq)) {
            $adminProdiId = optional($userReq->admin)->prodi_id;
            if (!$adminProdiId) {
                return response()->json(['message' => 'Admin tidak memiliki prodi'], 403);
            }
            $adminProdiId = (int) $adminProdiId;
        }

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            return response()->json(['message' => 'File CSV tidak dapat dibaca.'], 422);
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                return response()->json(['message' => 'File CSV kosong.'], 422);
            }
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $header = fgetcsv($handle, null, $delimiter, '"', '');
            $header = array_map(static function ($value) {
                $value = preg_replace('/^\xEF\xBB\xBF/', '', (string) $value);
                return strtolower(trim($value));
            }, $header ?: []);

            $requiredHeaders = ['nama_lengkap', 'nim', 'prodi', 'angkatan'];
            if (count($header) !== count($requiredHeaders)
                || count(array_unique($header)) !== count($requiredHeaders)
                || array_diff($requiredHeaders, $header)
                || array_diff($header, $requiredHeaders)) {
                return response()->json([
                    'message' => 'Header CSV tidak sesuai. Gunakan tepat: nama_lengkap,nim,prodi,angkatan.',
                    'errors' => ['Header wajib: nama_lengkap,nim,prodi,angkatan (tanpa kolom tambahan).'],
                ], 422);
            }

            $column = array_flip($header);
            $prodiList = Prodi::all(['prodi_id', 'kode_prodi', 'nama_prodi']);
            $imported = [];
            $errors = [];
            $lineNumber = 1;
            $processedRows = 0;

            while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $lineNumber++;
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue;
                }
                $processedRows++;
                if (count($row) !== count($requiredHeaders)) {
                    $errors[] = "Baris {$lineNumber}: jumlah kolom tidak sesuai; wajib 4 kolom.";
                    continue;
                }

                $nama = trim((string) ($row[$column['nama_lengkap']] ?? ''));
                $nim = trim((string) ($row[$column['nim']] ?? ''));
                $prodiValue = trim((string) ($row[$column['prodi']] ?? ''));
                $angkatan = trim((string) ($row[$column['angkatan']] ?? ''));
                $rowErrors = [];

                if ($nama === '' || mb_strlen($nama) > 100) {
                    $rowErrors[] = 'nama_lengkap wajib diisi (maksimal 100 karakter).';
                }
                if ($nim === '') {
                    $rowErrors[] = 'NIM wajib diisi.';
                } elseif (Mahasiswa::where('nim', $nim)->exists()) {
                    $rowErrors[] = 'NIM sudah terdaftar; data lama tidak diubah.';
                }
                if ($angkatan === '' || !preg_match('/^\d{4}$/', $angkatan) || (int) $angkatan < 2000 || (int) $angkatan > 2030) {
                    $rowErrors[] = 'angkatan harus berupa tahun 4 digit antara 2000 dan 2030.';
                }

                $nimParts = explode('/', $nim);
                if (count($nimParts) !== 4
                    || !preg_match('/^\d{2}$/', $nimParts[0] ?? '')
                    || !preg_match('/^\d+$/', $nimParts[1] ?? '')
                    || !preg_match('/^[A-Za-z]{2}$/', $nimParts[2] ?? '')
                    || !preg_match('/^\d+$/', $nimParts[3] ?? '')) {
                    $rowErrors[] = 'format NIM harus XX/NIU/XX/NIF dengan dua digit tahun, NIU dan NIF angka, serta kode fakultas dua huruf.';
                } elseif (preg_match('/^\d{4}$/', $angkatan) && (int) $angkatan !== (int) ('20' . $nimParts[0])) {
                    $rowErrors[] = 'angkatan harus sesuai dengan dua digit awal NIM.';
                }

                $normalizedProdi = mb_strtolower($prodiValue);
                $prodi = $prodiList->first(static function ($item) use ($normalizedProdi) {
                    return mb_strtolower(trim($item->kode_prodi)) === $normalizedProdi
                        || mb_strtolower(trim($item->nama_prodi)) === $normalizedProdi;
                });
                if (!$prodiValue || !$prodi) {
                    $rowErrors[] = 'prodi tidak cocok dengan kode atau nama Program Studi pada master.';
                } elseif ($adminProdiId && (int) $prodi->prodi_id !== $adminProdiId) {
                    $rowErrors[] = 'Admin Prodi hanya dapat mengimpor mahasiswa ke program studinya sendiri.';
                }

                if (count($nimParts) === 4 && preg_match('/^\d+$/', $nimParts[1] ?? '')) {
                    $existingAccount = User::where('username', $nimParts[1])->first();
                    if ($existingAccount) {
                        $rowErrors[] = 'username/NIU dari NIM sudah digunakan akun lain; data lama tidak diubah.';
                    }
                }

                if ($rowErrors) {
                    $errors[] = "Baris {$lineNumber} (NIM {$nim}): " . implode(' ', $rowErrors);
                    continue;
                }

                try {
                    $student = DB::transaction(function () use ($nim, $nimParts, $nama, $angkatan, $prodi) {
                        $user = User::create([
                            'username' => $nimParts[1],
                            'email' => null,
                            'password' => Hash::make($nimParts[3]),
                            'role' => 'mahasiswa',
                        ]);

                        return Mahasiswa::create([
                            'user_id' => $user->user_id,
                            'nim' => $nim,
                            'nama' => $nama,
                            'angkatan' => $angkatan,
                            'prodi_id' => $prodi->prodi_id,
                        ]);
                    });

                    $imported[] = [
                        'mahasiswa_id' => $student->mahasiswa_id,
                        'nim' => $student->nim,
                        'nama_lengkap' => $student->nama,
                        'prodi_id' => $student->prodi_id,
                        'angkatan' => $student->angkatan,
                    ];
                } catch (\Throwable $exception) {
                    report($exception);
                    $errors[] = "Baris {$lineNumber} (NIM {$nim}): gagal disimpan karena konflik data atau kendala database.";
                }
            }

            if ($processedRows === 0) {
                return response()->json([
                    'message' => 'CSV belum memiliki baris data mahasiswa.',
                    'errors' => ['Tambahkan minimal satu baris data setelah header.'],
                ], 422);
            }

            fclose($handle);

            return response()->json([
                'message' => $errors ? 'Import selesai dengan baris yang gagal.' : 'Import berhasil.',
                'data' => $imported,
                'imported_count' => count($imported),
                'failed_count' => count($errors),
                'errors' => $errors,
            ]);
        } catch (\Throwable $e) {
            if (is_resource($handle)) fclose($handle);
            report($e);
            return response()->json([
                'message' => 'Terjadi kesalahan saat membaca file import.',
            ], 500);
        }
    }

}
