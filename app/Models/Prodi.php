<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prodi extends Model
{
    use HasFactory;

    protected $table = 'prodi';
    protected $primaryKey = 'prodi_id';

    protected $fillable = [
        'kode_prodi',
        'nama_prodi',
    ];

    public function mahasiswa()
    {
        return $this->hasMany(Mahasiswa::class, 'prodi_id', 'prodi_id');
    }

    public function admins()
    {
        return $this->hasMany(Admin::class, 'prodi_id', 'prodi_id');
    }
}
