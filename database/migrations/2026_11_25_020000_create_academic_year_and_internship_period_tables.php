<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tahun_ajaran')) {
            Schema::create('tahun_ajaran', function (Blueprint $table) {
                $table->id('tahun_ajaran_id');
                $table->string('tahun_ajaran', 9)->unique();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('periode_magang')) {
            Schema::create('periode_magang', function (Blueprint $table) {
                $table->id('periode_id');
                $table->unsignedBigInteger('tahun_ajaran_id');
                $table->unsignedSmallInteger('semester_magang');
                $table->string('jenis_semester', 5);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tahun_ajaran_id')
                    ->references('tahun_ajaran_id')
                    ->on('tahun_ajaran')
                    ->restrictOnDelete();
                $table->unique(['tahun_ajaran_id', 'semester_magang'], 'periode_year_semester_unique');
            });
        }
    }

    public function down(): void
    {
        // Intentionally keep master rows and their schema on rollback. These
        // are user-managed records, and dropping them would destroy data.
    }
};
