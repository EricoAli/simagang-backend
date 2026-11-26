<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    use HasFactory;

    protected $table = 'tahun_ajaran';
    protected $primaryKey = 'tahun_ajaran_id';

    protected $fillable = [
        'tahun_ajaran',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    public function periodeMagang()
    {
        return $this->hasMany(PeriodeMagang::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
    }

    public function magang()
    {
        return $this->hasMany(Magang::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
    }

    public function mahasiswa()
    {
        return $this->hasMany(Mahasiswa::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
    }
}
