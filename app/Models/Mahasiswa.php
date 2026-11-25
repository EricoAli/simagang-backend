<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mahasiswa extends Model
{
    use HasFactory;

    protected $table = 'mahasiswa';
    protected $primaryKey = 'mahasiswa_id';

    protected $fillable = [
        'user_id',
        'prodi_id',
        'nim',
        'nama',
        'angkatan',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'prodi_id');
    }

    public function magang()
    {
        // Riwayat magang selalu terurut dari periode terbaru agar halaman
        // detail mahasiswa konsisten saat menampilkan riwayat per periode.
        return $this->hasMany(Magang::class, 'mahasiswa_id', 'mahasiswa_id')
            ->orderByDesc('tahun_ajaran')
            ->orderByDesc('semester_magang')
            ->orderByDesc('magang_id');
    }

    public function pesertaJadwal()
    {
        return $this->hasMany(JadwalPeserta::class, 'mahasiswa_id', 'mahasiswa_id');
    }

    public static function booted()
    {
        static::deleting(function ($mahasiswa) {
            if ($mahasiswa->user) {
                $mahasiswa->user->delete();
            }
            foreach ($mahasiswa->magang as $magang) { 
                $magang->delete(); 
            }
        });
    }
}
