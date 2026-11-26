<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;

class Magang extends Model
{
    use HasFactory;

    protected $table = 'magang';
    protected $primaryKey = 'magang_id';

    protected $fillable = [
        'mahasiswa_id',
        'mitra_id',
        'supervisor_id',
        'dosbing_id',
        'periode_id',
        'semester_magang',
        'tahun_ajaran',
        'role_magang',
        'jobdesk',
        'periode_bulan',
        'jumlah_magang_ke',
        'tanggal_mulai',
        'tanggal_selesai',
        'status_magang',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected static function booted()
    {
        static::saving(function ($magang) {
            // Sinkronisasi periode hanya diperlukan saat data dibuat atau
            // ketika salah satu field periode memang diubah. Update status
            // (atau field lain) tidak boleh menulis ulang periode, melakukan
            // backfill periode_id, atau menggagalkan data legacy.
            $periodeAttributes = ['periode_id', 'semester_magang', 'tahun_ajaran'];
            $shouldSyncPeriode = !$magang->exists
                || $magang->isDirty($periodeAttributes);

            if (!$shouldSyncPeriode) {
                return;
            }

            // Relasi master periode adalah sumber utama. Kolom lama tetap
            // disinkronkan agar data historis dan klien lama tetap kompatibel.
            if ($magang->periode_id) {
                $periode = PeriodeMagang::with('tahunAjaran')
                    ->find($magang->periode_id);

                if (!$periode) {
                    throw new \InvalidArgumentException(
                        'Periode magang yang dipilih tidak ditemukan.'
                    );
                }

                $magang->semester_magang = $periode->semester_magang;
                $magang->tahun_ajaran = self::academicStartYear(
                    $periode->tahunAjaran?->tahun_ajaran
                );
            } elseif ($magang->tahun_ajaran && $magang->semester_magang) {
                $periodeId = self::resolvePeriodeId(
                    $magang->tahun_ajaran,
                    $magang->semester_magang
                );

                if ($periodeId) {
                    $magang->periode_id = $periodeId;
                }
            }

            if (!$magang->tahun_ajaran) {
                $mahasiswa = $magang->mahasiswa;
                if (!$mahasiswa && $magang->mahasiswa_id) {
                    $mahasiswa = Mahasiswa::find($magang->mahasiswa_id);
                }

                if ($mahasiswa && $mahasiswa->angkatan) {
                    $magang->tahun_ajaran = (int) $mahasiswa->angkatan + 3;
                } else {
                    $magang->tahun_ajaran = (int) date('Y');
                }

                // Setelah tahun ajaran diturunkan dari angkatan, coba petakan
                // kembali ke master periode bila datanya tersedia.
                if (!$magang->periode_id && $magang->semester_magang) {
                    $magang->periode_id = self::resolvePeriodeId(
                        $magang->tahun_ajaran,
                        $magang->semester_magang
                    );
                }
            }
        });

        static::deleting(function ($magang) {
            $magang->load(['dokumenMagang', 'laporan', 'penilaianMitra', 'logbook.fotoKegiatan']);

            foreach ($magang->dokumenMagang as $dokumen) {
                if (Storage::disk('public')->exists($dokumen->file_path)) {
                    Storage::disk('public')->delete($dokumen->file_path);
                }
            }

            if ($magang->laporan?->file_path) {
                Storage::disk('public')->delete($magang->laporan->file_path);
            }

            if ($magang->penilaianMitra?->file_path) {
                Storage::disk('public')->delete($magang->penilaianMitra->file_path);
            }

            foreach ($magang->logbook as $logbook) {
                foreach ($logbook->fotoKegiatan as $foto) {
                    Storage::disk('public')->delete($foto->file_path);
                }
            }
        });
    }

    // 🔗 Relasi
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id');
    }

    public function periode()
    {
        return $this->belongsTo(PeriodeMagang::class, 'periode_id', 'periode_id');
    }

    /**
     * Ambil empat digit tahun awal dari label tahun ajaran (2026/2027 -> 2026).
     */
    public static function academicStartYear(?string $tahunAjaran): ?int
    {
        if (!$tahunAjaran || !preg_match('/^\s*(\d{4})/', $tahunAjaran, $match)) {
            return null;
        }

        return (int) $match[1];
    }

    /**
     * Petakan pasangan tahun ajaran + semester ke master periode.
     */
    public static function resolvePeriodeId($tahunAjaran, $semester): ?int
    {
        if (!is_numeric($tahunAjaran) || !is_numeric($semester)) {
            return null;
        }

        $yearStart = (int) $tahunAjaran;

        return PeriodeMagang::query()
            ->where('semester_magang', (int) $semester)
            ->whereHas('tahunAjaran', function ($query) use ($yearStart) {
                $query->where('tahun_ajaran', 'like', $yearStart . '/%');
            })
            ->orderByDesc('tahun_ajaran_id')
            ->value('periode_id');
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id', 'mitra_id');
    }

    public function dosenPembimbing()
    {
        return $this->belongsTo(Dosbing::class, 'dosbing_id', 'dosbing_id');
    }

    public function dokumenMagang()
    {
        return $this->hasMany(DokumenMagang::class, 'magang_id', 'magang_id');
    }

    public function penilaianMitra()
    {
        return $this->hasOne(NilaiMitra::class, 'magang_id', 'magang_id');
    }

    public function laporan()
    {
        return $this->hasOne(Laporan::class, 'magang_id', 'magang_id');
    }

    public function logbook()
    {
        return $this->hasMany(Logbook::class, 'magang_id', 'magang_id');
    }

    public function pesertaJadwal()
    {
        return $this->hasMany(JadwalPeserta::class, 'magang_id', 'magang_id');
    }


}
