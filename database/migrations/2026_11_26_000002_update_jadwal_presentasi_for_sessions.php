<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_presentasi', function (Blueprint $table) {
            $table->unsignedBigInteger('dosen_penguji_id')->nullable()->after('magang_id');
            $table->unsignedBigInteger('prodi_id')->nullable()->after('dosen_penguji_id');

            $table->foreign('dosen_penguji_id')
                ->references('dosbing_id')
                ->on('dosen_pembimbing')
                ->nullOnDelete();

            $table->foreign('prodi_id')
                ->references('prodi_id')
                ->on('prodi')
                ->nullOnDelete();
        });

        Schema::create('jadwal_presentasi_peserta', function (Blueprint $table) {
            $table->id('peserta_id');
            $table->unsignedBigInteger('jadwal_id');
            $table->unsignedBigInteger('magang_id');
            $table->timestamps();

            $table->foreign('jadwal_id')
                ->references('jadwal_id')
                ->on('jadwal_presentasi')
                ->onDelete('cascade');

            $table->foreign('magang_id')
                ->references('magang_id')
                ->on('magang')
                ->onDelete('cascade');

            $table->unique(['jadwal_id', 'magang_id'], 'jadwal_peserta_unique');
        });

        // Sinkronkan relasi magang_id eksisting ke tabel peserta dan prodi_id jadwal
        $existingJadwals = DB::table('jadwal_presentasi')
            ->whereNotNull('magang_id')
            ->get(['jadwal_id', 'magang_id']);

        foreach ($existingJadwals as $jadwal) {
            $prodiId = DB::table('magang')
                ->join('mahasiswa', 'magang.mahasiswa_id', '=', 'mahasiswa.mahasiswa_id')
                ->where('magang.magang_id', $jadwal->magang_id)
                ->value('mahasiswa.prodi_id');

            if ($prodiId) {
                DB::table('jadwal_presentasi')
                    ->where('jadwal_id', $jadwal->jadwal_id)
                    ->whereNull('prodi_id')
                    ->update(['prodi_id' => $prodiId]);
            }

            DB::table('jadwal_presentasi_peserta')->updateOrInsert(
                ['jadwal_id' => $jadwal->jadwal_id, 'magang_id' => $jadwal->magang_id],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_presentasi_peserta');

        Schema::table('jadwal_presentasi', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropForeign(['dosen_penguji_id']);
            $table->dropColumn(['dosen_penguji_id', 'prodi_id']);
        });
    }
};
