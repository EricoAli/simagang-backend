<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logbook extends Model
{
    protected $table = 'logbook';
    protected $primaryKey = 'logbook_id';

    protected $fillable = [
        'magang_id',
        'kegiatan',
        'tanggal_kegiatan',
    ];

    protected $casts = [
        'tanggal_kegiatan' => 'date:Y-m-d',
    ];

    public function magang()
    {
        return $this->belongsTo(Magang::class, 'magang_id', 'magang_id');
    }

    public function fotoKegiatan()
    {
        return $this->hasMany(FotoMagang::class, 'logbook_id', 'logbook_id');
    }
}
