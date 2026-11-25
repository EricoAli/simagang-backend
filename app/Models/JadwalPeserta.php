<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JadwalPeserta extends Model
{
    protected $table = 'jadwal_peserta';
    protected $primaryKey = 'peserta_id';

    protected $fillable = [
        'jadwal_id',
        'magang_id',
        'mahasiswa_id',
    ];

    public function jadwal()
    {
        return $this->belongsTo(JadwalPresentasi::class, 'jadwal_id', 'jadwal_id');
    }

    public function magang()
    {
        return $this->belongsTo(Magang::class, 'magang_id', 'magang_id');
    }

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id', 'mahasiswa_id');
    }
}
