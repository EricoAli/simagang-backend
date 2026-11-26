<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'semester_magang',
        'jenis_semester',
        'tahun_ajaran',
        'tahun_ajaran_id',
        'periode_id',
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

    protected $appends = [
        'status_label',
        'periode_label',
    ];

    /**
     * Normalisasi label/alias status magang ke enum kanonik database:
     * draft | berlangsung | selesai | ditolak.
     */
    public static function normalizeStatus(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $status = strtolower(trim($raw));
        if ($status === '') {
            return null;
        }

        if (in_array($status, [
            'draft',
            'belum_mulai',
            'belum mulai',
            'belum magang',
            'belum',
            'menunggu',
            'menunggu konfirmasi',
        ], true)) {
            return 'draft';
        }

        if (in_array($status, [
            'berlangsung',
            'sedang_magang',
            'sedang magang',
            'sedang',
            'berjalan',
            'revisi',
        ], true)) {
            return 'berlangsung';
        }

        if (in_array($status, ['selesai', 'selesai magang'], true)) {
            return 'selesai';
        }

        if (in_array($status, ['ditolak', 'tolak'], true)) {
            return 'ditolak';
        }

        return $status;
    }

    public function setStatusMagangAttribute($value): void
    {
        $normalized = self::normalizeStatus($value === null ? null : (string) $value);
        $this->attributes['status_magang'] = $normalized ?? 'draft';
    }

    public function setJenisSemesterAttribute($value): void
    {
        $this->attributes['jenis_semester'] = PeriodeMagang::normalizeJenisSemester(
            $value === null ? null : (string) $value
        );
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status_magang) {
            'draft' => 'Belum Mulai',
            'berlangsung' => 'Sedang Magang',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            default => ucfirst((string) $this->status_magang),
        };
    }

    public function getPeriodeLabelAttribute(): ?string
    {
        $periode = $this->relationLoaded('periode') ? $this->getRelation('periode') : null;
        $year = null;

        if ($periode) {
            $year = $periode->relationLoaded('tahunAjaran')
                ? $periode->tahunAjaran?->tahun_ajaran
                : null;
        }

        $year = $year
            ?? ($this->relationLoaded('masterTahunAjaran') ? $this->masterTahunAjaran?->tahun_ajaran : null)
            ?? $this->tahun_ajaran;

        $semester = $periode?->semester_magang ?? $this->semester_magang;
        $jenis = $periode?->jenis_semester ?? $this->jenis_semester;

        if (! $year && ! $semester) {
            return null;
        }

        $parts = [];
        if ($year) {
            $parts[] = (string) $year;
        }
        if ($semester) {
            $parts[] = 'Semester '.$semester.($jenis ? ' '.$jenis : '');
        }

        return implode(' · ', $parts);
    }

    protected static function booted()
    {
        static::creating(function ($magang) {
            self::syncPeriodFields($magang);

            if (! $magang->tahun_ajaran) {
                $mahasiswa = $magang->mahasiswa;
                if (! $mahasiswa && $magang->mahasiswa_id) {
                    $mahasiswa = Mahasiswa::find($magang->mahasiswa_id);
                }

                if ($mahasiswa && $mahasiswa->angkatan) {
                    $magang->tahun_ajaran = (string) ((int) $mahasiswa->angkatan + 3);
                } else {
                    $magang->tahun_ajaran = (string) date('Y');
                }
            }

            if (! $magang->jumlah_magang_ke && $magang->mahasiswa_id) {
                $existingCount = self::where('mahasiswa_id', $magang->mahasiswa_id)->count();
                $magang->jumlah_magang_ke = $existingCount + 1;
            }
        });

        static::saving(function ($magang) {
            self::syncPeriodFields($magang);

            if ((int) $magang->semester_magang < 1) {
                throw new \InvalidArgumentException('Semester magang minimal 1.');
            }
        });

        static::deleting(function ($magang) {
            $magang->load(['dokumenMagang', 'laporan', 'penilaianMitra', 'logbook.fotoKegiatan', 'logbooks.fotoKegiatan']);

            foreach ($magang->dokumenMagang as $dokumen) {
                if (! empty($dokumen->file_path)) {
                    if (Storage::disk('local')->exists($dokumen->file_path)) {
                        Storage::disk('local')->delete($dokumen->file_path);
                    } elseif (Storage::disk('public')->exists($dokumen->file_path)) {
                        Storage::disk('public')->delete($dokumen->file_path);
                    }
                }
            }

            if ($magang->laporan?->file_path) {
                if (Storage::disk('local')->exists($magang->laporan->file_path)) {
                    Storage::disk('local')->delete($magang->laporan->file_path);
                } elseif (Storage::disk('public')->exists($magang->laporan->file_path)) {
                    Storage::disk('public')->delete($magang->laporan->file_path);
                }
            }

            if ($magang->penilaianMitra?->file_path) {
                Storage::disk('public')->delete($magang->penilaianMitra->file_path);
            }

            $logbookCollection = $magang->logbooks->isNotEmpty()
                ? $magang->logbooks
                : ($magang->logbook ? collect([$magang->logbook]) : collect());

            foreach ($logbookCollection as $logbook) {
                if ($logbook->fotoKegiatan) {
                    foreach ($logbook->fotoKegiatan as $foto) {
                        Storage::disk('public')->delete($foto->file_path);
                    }
                }
            }
        });
    }

    private static function syncPeriodFields(Magang $magang): void
    {
        if ($magang->periode_id && ($magang->isDirty('periode_id') || ! $magang->semester_magang || ! $magang->tahun_ajaran_id || ! $magang->jenis_semester)) {
            $periode = PeriodeMagang::with('tahunAjaran')->find($magang->periode_id);
            if ($periode) {
                $magang->tahun_ajaran_id = $periode->tahun_ajaran_id;
                $magang->tahun_ajaran = $periode->tahunAjaran?->tahun_ajaran ?? $magang->tahun_ajaran;
                $magang->semester_magang = $periode->semester_magang;
                $magang->jenis_semester = $periode->jenis_semester;

                return;
            }
        }

        if ($magang->tahun_ajaran_id && ($magang->isDirty('tahun_ajaran_id') || ! $magang->tahun_ajaran)) {
            $tahun = TahunAjaran::find($magang->tahun_ajaran_id);
            if ($tahun) {
                $magang->tahun_ajaran = $tahun->tahun_ajaran;
            }
        }
    }

    // 🔗 Relasi
    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id');
    }

    public function mitra()
    {
        return $this->belongsTo(Mitra::class, 'mitra_id', 'mitra_id');
    }

    public function dosenPembimbing()
    {
        return $this->belongsTo(Dosbing::class, 'dosbing_id', 'dosbing_id');
    }

    public function periode()
    {
        return $this->belongsTo(PeriodeMagang::class, 'periode_id', 'periode_id');
    }

    public function masterTahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
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
        return $this->hasOne(Logbook::class, 'magang_id');
    }

    public function logbooks()
    {
        return $this->hasMany(Logbook::class, 'magang_id', 'magang_id');
    }

    public function jadwalPresentasi()
    {
        return $this->hasMany(JadwalPresentasi::class, 'magang_id', 'magang_id');
    }

    public function jadwalPresentasiPeserta()
    {
        return $this->belongsToMany(JadwalPresentasi::class, 'jadwal_presentasi_peserta', 'magang_id', 'jadwal_id')
            ->withPivot('peserta_id')
            ->withTimestamps();
    }
}
