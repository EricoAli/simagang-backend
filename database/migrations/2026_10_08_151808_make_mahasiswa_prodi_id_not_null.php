<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make mahasiswa.prodi_id NOT NULL.
 *
 * Safe precondition: database dev is currently empty.
 * Business rule: every mahasiswa added by Admin is automatically assigned
 * to that Admin's prodi. Therefore prodi_id can never be null in normal flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('prodi_id')
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('prodi_id')
                ->nullable()
                ->change();
        });
    }
};
