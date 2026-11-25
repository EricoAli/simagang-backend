<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPresentasi extends Model
{
    protected $table = 'jadwal_presentasi';
    protected $primaryKey = 'jadwal_id';

    protected $fillable = [
        'magang_id',
        'dosen_penguji_id',
        'tanggal_presentasi',
        'waktu_mulai',
        'waktu_selesai',
        'tempat',
        'ruangan',
        'keterangan',
        'status',
        'created_by',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'status',
        'tempat',
    ];

    // Relasi: JadwalPresentasi belongsTo Magang
    public function magang()
    {
        return $this->belongsTo(Magang::class, 'magang_id', 'magang_id');
    }

    public function peserta()
    {
        return $this->hasMany(JadwalPeserta::class, 'jadwal_id', 'jadwal_id');
    }

    public function dosenPenguji()
    {
        return $this->belongsTo(Dosbing::class, 'dosen_penguji_id', 'dosbing_id');
    }
}
