<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PeriodeMagang extends Model
{
    use HasFactory;

    protected $table = 'periode_magang';
    protected $primaryKey = 'periode_id';

    protected $fillable = [
        'tahun_ajaran_id',
        'semester_magang',
        'jenis_semester',
        'is_active',
    ];

    protected $casts = [
        'semester_magang' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
    }
}
