<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenMagang extends Model
{
    public const JENIS_DOKUMEN = [
        'doc_surat_penerimaan',
        'doc_pra_krs',
        'doc_laporan_magang',
        'doc_penilaian_mitra',
    ];

    public const LABEL_JENIS_DOKUMEN = [
        'doc_surat_penerimaan' => 'Surat Penerimaan Magang',
        'doc_pra_krs' => 'Dokumen Pra-KRS',
        'doc_laporan_magang' => 'Laporan Magang',
        'doc_penilaian_mitra' => 'Penilaian Mitra',
    ];

    protected $table = 'dokumen_magang';
    protected $primaryKey = 'dokumen_id';
    public $timestamps = false; // pakai uploaded_at & updated_at

    protected $fillable = [
        'magang_id',
        'jenis_dokumen', //['doc_surat_penerimaan', 'doc_pra_krs', 'doc_laporan_magang', 'doc_penilaian_mitra']
        'nama_file',
        'file_path',
        'ukuran_file',
        'uploaded_at',
        'updated_at',
    ];
    protected $hidden = [
        'updated_at',
        'uploaded_at',
        'ukuran_file'
    ];

    protected $appends = ['jenis_dokumen_label'];

    public function getJenisDokumenLabelAttribute(): string
    {
        return self::LABEL_JENIS_DOKUMEN[$this->jenis_dokumen]
            ?? ucwords(str_replace(['_', '-'], ' ', (string) $this->jenis_dokumen));
    }


    public function magang()
    {
        return $this->belongsTo(Magang::class, 'magang_id', 'magang_id');
    }
}
