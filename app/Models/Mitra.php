<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Mitra extends Model
{
    use HasFactory;

    protected $table = 'mitra';
    protected $primaryKey = 'mitra_id';

    protected $fillable = [
        'nama_mitra',
        'user_id',
        'email',
        'narahubung',
        'alamat',
        'telepon',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];


    public function magang()
    {
        return $this->hasMany(Magang::class, 'mitra_id', 'mitra_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public static function booted()
    {
        static::deleting(function ($mitra) {
            $mitra->user()->delete();
        });
    }
}
