<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dosbing extends Model
{
    use HasFactory;

    protected $table = 'dosen_pembimbing';
    protected $primaryKey = 'dosbing_id';

    protected $fillable = [
        'prodi_id',
        'nip',
        'nama',
    ];

    protected $casts = [
        'prodi_id' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    // 🔗 Relasi
    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'prodi_id');
    }

    public function magang()
    {
        return $this->hasMany(Magang::class, 'dosbing_id', 'dosbing_id');
    }
}
