<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id('tahun_ajaran_id');
            $table->string('tahun_ajaran', 20)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('periode_magang', function (Blueprint $table) {
            $table->id('periode_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->unsignedTinyInteger('semester_magang');
            $table->enum('jenis_semester', ['Gasal', 'Genap']);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tahun_ajaran_id')
                ->references('tahun_ajaran_id')
                ->on('tahun_ajaran')
                ->onDelete('cascade');

            $table->unique(
                ['tahun_ajaran_id', 'semester_magang', 'jenis_semester'],
                'periode_magang_unique_combo'
            );
        });

        Schema::table('magang', function (Blueprint $table) {
            $table->string('tahun_ajaran', 20)->nullable()->change();
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('tahun_ajaran');
            $table->unsignedBigInteger('periode_id')->nullable()->after('tahun_ajaran_id');
            $table->enum('jenis_semester', ['Gasal', 'Genap'])->nullable()->after('semester_magang');

            $table->foreign('tahun_ajaran_id')
                ->references('tahun_ajaran_id')
                ->on('tahun_ajaran')
                ->nullOnDelete();

            $table->foreign('periode_id')
                ->references('periode_id')
                ->on('periode_magang')
                ->nullOnDelete();
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('angkatan');

            $table->foreign('tahun_ajaran_id')
                ->references('tahun_ajaran_id')
                ->on('tahun_ajaran')
                ->nullOnDelete();
        });

        Schema::table('dosen_pembimbing', function (Blueprint $table) {
            $table->unsignedBigInteger('prodi_id')->nullable()->after('dosbing_id');

            $table->foreign('prodi_id')
                ->references('prodi_id')
                ->on('prodi')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dosen_pembimbing', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropColumn('prodi_id');
        });

        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropColumn('tahun_ajaran_id');
        });

        Schema::table('magang', function (Blueprint $table) {
            $table->dropForeign(['periode_id']);
            $table->dropForeign(['tahun_ajaran_id']);
            $table->dropColumn(['tahun_ajaran_id', 'periode_id', 'jenis_semester']);
        });

        if (Schema::hasTable('magang')) {
            DB::table('magang')->get(['magang_id', 'tahun_ajaran'])->each(function ($row) {
                $year = preg_match('/^(\d{4})/', (string) $row->tahun_ajaran, $m) ? (int) $m[1] : (int) date('Y');
                $year = max(1901, min(2155, $year));
                DB::table('magang')->where('magang_id', $row->magang_id)->update(['tahun_ajaran' => $year]);
            });

            Schema::table('magang', function (Blueprint $table) {
                $table->year('tahun_ajaran')->nullable(false)->change();
            });
        }

        Schema::dropIfExists('periode_magang');
        Schema::dropIfExists('tahun_ajaran');
    }
};
