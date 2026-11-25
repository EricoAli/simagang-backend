<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_peserta', function (Blueprint $table) {
            $table->id('peserta_id');
            $table->unsignedBigInteger('jadwal_id');
            $table->unsignedBigInteger('magang_id');
            $table->unsignedBigInteger('mahasiswa_id');
            $table->timestamps();

            $table->foreign('jadwal_id')
                ->references('jadwal_id')
                ->on('jadwal_presentasi')
                ->cascadeOnDelete();
            $table->foreign('magang_id')
                ->references('magang_id')
                ->on('magang')
                ->cascadeOnDelete();
            $table->foreign('mahasiswa_id')
                ->references('mahasiswa_id')
                ->on('mahasiswa')
                ->cascadeOnDelete();

            $table->unique(['jadwal_id', 'mahasiswa_id']);
            $table->unique(['jadwal_id', 'magang_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_peserta');
    }
};
