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
        'prodi_id',
        'tanggal_presentasi',
        'waktu_mulai',
        'waktu_selesai',
        'tempat',
        'ruangan',
        'keterangan',
        'status',
    ];

    protected $casts = [
        'magang_id' => 'integer',
        'dosen_penguji_id' => 'integer',
        'prodi_id' => 'integer',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'tempat',
    ];

    protected $appends = [
        'dosen_penguji',
        'dosen_penguji_data',
        'peserta_count',
        'jumlah_peserta',
    ];

    public function getDosenPengujiAttribute(): ?string
    {
        if ($this->relationLoaded('penguji')) {
            return $this->getRelation('penguji')?->nama;
        }

        if ($this->dosen_penguji_id) {
            return $this->penguji()->value('nama');
        }

        return null;
    }

    public function getDosenPengujiDataAttribute(): ?array
    {
        $penguji = $this->relationLoaded('penguji')
            ? $this->getRelation('penguji')
            : ($this->dosen_penguji_id ? $this->penguji()->first() : null);

        if (! $penguji) {
            return null;
        }

        return [
            'dosbing_id' => $penguji->dosbing_id,
            'nip' => $penguji->nip,
            'nama' => $penguji->nama,
            'prodi_id' => $penguji->prodi_id,
        ];
    }

    public function getPesertaCountAttribute(): int
    {
        if (array_key_exists('peserta_magang_count', $this->attributes)) {
            $count = (int) $this->attributes['peserta_magang_count'];

            return $count > 0 ? $count : ($this->magang_id ? 1 : 0);
        }

        if ($this->relationLoaded('pesertaMagang')) {
            $count = $this->pesertaMagang->count();

            return $count > 0 ? $count : ($this->magang_id ? 1 : 0);
        }

        $count = $this->pesertaMagang()->count();

        return $count > 0 ? $count : ($this->magang_id ? 1 : 0);
    }

    public function getJumlahPesertaAttribute(): int
    {
        return $this->peserta_count;
    }

    // Relasi: JadwalPresentasi belongsTo Magang (legacy primary participant)
    public function magang()
    {
        return $this->belongsTo(Magang::class, 'magang_id', 'magang_id');
    }

    public function penguji()
    {
        return $this->belongsTo(Dosbing::class, 'dosen_penguji_id', 'dosbing_id');
    }

    public function prodi()
    {
        return $this->belongsTo(Prodi::class, 'prodi_id', 'prodi_id');
    }

    public function pesertaMagang()
    {
        return $this->belongsToMany(Magang::class, 'jadwal_presentasi_peserta', 'jadwal_id', 'magang_id')
            ->withPivot('peserta_id')
            ->withTimestamps();
    }
}
