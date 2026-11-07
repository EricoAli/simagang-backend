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
        static::creating(function ($magang) {
            if (!$magang->tahun_ajaran) {
                $mahasiswa = $magang->mahasiswa;
                if (!$mahasiswa && $magang->mahasiswa_id) {
                    $mahasiswa = \App\Models\Mahasiswa::find($magang->mahasiswa_id);
                }

                if ($mahasiswa && $mahasiswa->angkatan) {
                    $magang->tahun_ajaran = (int) $mahasiswa->angkatan + 3;
                } else {
                    $magang->tahun_ajaran = (int) date('Y');
                }
            }
        });

        static::saving(function ($magang) {
            if (!in_array($magang->semester_magang, [6, 7])) {
                throw new \InvalidArgumentException('Semester magang hanya boleh 6 atau 7.');
            }
        });

        static::deleting(function ($magang) {
            $magang->load(['dokumenMagang', 'laporan', 'penilaianMitra', 'logbook.fotoKegiatan']);

            foreach ($magang->dokumenMagang as $dokumen) {
                if (!empty($dokumen->file_path)) {
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

            if ($magang->logbook?->fotoKegiatan) {
                foreach ($magang->logbook->fotoKegiatan as $foto) {
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
        return $this->hasOne(Logbook::class, 'magang_id');
    }

    public function jadwalPresentasi()
    {
        return $this->hasMany(JadwalPresentasi::class, 'magang_id', 'magang_id');
    }
}
