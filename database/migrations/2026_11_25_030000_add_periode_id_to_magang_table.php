<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan relasi periode master ke data magang tanpa mengubah kolom
     * lama. Kolom semester_magang dan tahun_ajaran tetap dipertahankan agar
     * riwayat/data lama tetap dapat dibaca oleh klien yang belum diperbarui.
     */
    public function up(): void
    {
        if (!Schema::hasTable('magang') || !Schema::hasTable('periode_magang')) {
            return;
        }

        if (!Schema::hasColumn('magang', 'periode_id')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->unsignedBigInteger('periode_id')
                    ->nullable()
                    ->after('semester_magang');
            });
        }

        $this->backfillExistingMagang();

        if (!Schema::hasIndex('magang', 'magang_periode_id_index')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->index('periode_id', 'magang_periode_id_index');
            });
        }

        // Satu mahasiswa hanya boleh memiliki satu data magang per periode.
        // Index hanya dibuat bila data lama tidak mengandung duplikat agar
        // migrasi tidak merusak data yang sudah ada.
        if (!$this->hasDuplicateStudentPeriod()
            && !Schema::hasIndex('magang', 'magang_mahasiswa_periode_unique')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->unique(
                    ['mahasiswa_id', 'periode_id'],
                    'magang_mahasiswa_periode_unique'
                );
            });
        } elseif ($this->hasDuplicateStudentPeriod()) {
            Log::warning(
                'Index unik magang_mahasiswa_periode_unique tidak dibuat karena '
                . 'ditemukan data magang duplikat pada periode yang sama.'
            );
        }

        // SQLite tidak mendukung penambahan foreign key melalui ALTER TABLE.
        // Database produksi (MySQL) tetap mendapatkan constraint foreign key.
        if (DB::getDriverName() !== 'sqlite'
            && !Schema::hasForeignKey('magang', 'magang_periode_id_foreign')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->foreign('periode_id', 'magang_periode_id_foreign')
                    ->references('periode_id')
                    ->on('periode_magang')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Cocokkan data lama (tahun_ajaran YEAR + semester_magang) dengan master
     * periode berdasarkan empat digit tahun awal pada label tahun ajaran.
     */
    private function backfillExistingMagang(): void
    {
        $periods = DB::table('periode_magang as p')
            ->join(
                'tahun_ajaran as t',
                't.tahun_ajaran_id',
                '=',
                'p.tahun_ajaran_id'
            )
            ->select([
                'p.periode_id',
                'p.semester_magang',
                't.tahun_ajaran',
            ])
            ->get();

        if ($periods->isEmpty()) {
            return;
        }

        $periodMap = [];
        foreach ($periods as $period) {
            $startYear = (int) substr((string) $period->tahun_ajaran, 0, 4);
            if ($startYear > 0) {
                $periodMap[$startYear . ':' . (int) $period->semester_magang]
                    = (int) $period->periode_id;
            }
        }

        DB::table('magang')
            ->whereNull('periode_id')
            ->select(['magang_id', 'semester_magang', 'tahun_ajaran'])
            ->orderBy('magang_id')
            ->chunkById(200, function ($rows) use ($periodMap) {
                foreach ($rows as $row) {
                    $key = (int) $row->tahun_ajaran
                        . ':' . (int) $row->semester_magang;

                    if (isset($periodMap[$key])) {
                        DB::table('magang')
                            ->where('magang_id', $row->magang_id)
                            ->whereNull('periode_id')
                            ->update(['periode_id' => $periodMap[$key]]);
                    }
                }
            }, 'magang_id');
    }

    private function hasDuplicateStudentPeriod(): bool
    {
        return DB::table('magang')
            ->select(['mahasiswa_id', 'periode_id'])
            ->whereNotNull('periode_id')
            ->groupBy('mahasiswa_id', 'periode_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
    }

    public function down(): void
    {
        if (!Schema::hasTable('magang')
            || !Schema::hasColumn('magang', 'periode_id')) {
            return;
        }

        if (DB::getDriverName() !== 'sqlite'
            && Schema::hasForeignKey('magang', 'magang_periode_id_foreign')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->dropForeign('magang_periode_id_foreign');
            });
        }

        if (Schema::hasIndex('magang', 'magang_mahasiswa_periode_unique')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->dropUnique('magang_mahasiswa_periode_unique');
            });
        }

        if (Schema::hasIndex('magang', 'magang_periode_id_index')) {
            Schema::table('magang', function (Blueprint $table) {
                $table->dropIndex('magang_periode_id_index');
            });
        }

        Schema::table('magang', function (Blueprint $table) {
            $table->dropColumn('periode_id');
        });
    }
};
