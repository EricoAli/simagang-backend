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
        'tahun_ajaran_id' => 'integer',
        'semester_magang' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    protected $appends = [
        'label',
    ];

    /**
     * Normalize semester type to capitalized Gasal/Genap matching FE selects.
     */
    public static function normalizeJenisSemester(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'gasal', 'ganjil' => 'Gasal',
            'genap' => 'Genap',
            default => $value !== '' ? ucfirst($normalized) : null,
        };
    }

    public function setJenisSemesterAttribute($value): void
    {
        $this->attributes['jenis_semester'] = self::normalizeJenisSemester($value === null ? null : (string) $value);
    }

    public function getLabelAttribute(): string
    {
        $yearLabel = $this->relationLoaded('tahunAjaran')
            ? ($this->tahunAjaran?->tahun_ajaran ?? '-')
            : ($this->tahunAjaran()->value('tahun_ajaran') ?? '-');

        return "Semester {$this->semester_magang} | {$this->jenis_semester} | {$yearLabel}";
    }

    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class, 'tahun_ajaran_id', 'tahun_ajaran_id');
    }

    public function magang()
    {
        return $this->hasMany(Magang::class, 'periode_id', 'periode_id');
    }
}
