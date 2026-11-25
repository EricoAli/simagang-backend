<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('dosen_pembimbing', 'prodi_id')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->unsignedBigInteger('prodi_id')->nullable()->after('nama');
            });
        }

        if (!Schema::hasIndex('dosen_pembimbing', 'dosen_pembimbing_prodi_id_foreign')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->foreign('prodi_id', 'dosen_pembimbing_prodi_id_foreign')
                    ->references('prodi_id')->on('prodi')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('jadwal_presentasi', 'dosen_penguji_id')) {
            Schema::table('jadwal_presentasi', function (Blueprint $table) {
                $table->unsignedBigInteger('dosen_penguji_id')->nullable()->after('jadwal_id');
            });
        }

        if (!Schema::hasIndex('jadwal_presentasi', 'jadwal_presentasi_dosen_penguji_id_foreign')) {
            Schema::table('jadwal_presentasi', function (Blueprint $table) {
                $table->foreign('dosen_penguji_id', 'jadwal_presentasi_dosen_penguji_id_foreign')
                    ->references('dosbing_id')->on('dosen_pembimbing')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('jadwal_presentasi', 'jadwal_presentasi_dosen_penguji_id_foreign')) {
            Schema::table('jadwal_presentasi', function (Blueprint $table) {
                $table->dropForeign('jadwal_presentasi_dosen_penguji_id_foreign');
            });
        }
        if (Schema::hasColumn('jadwal_presentasi', 'dosen_penguji_id')) {
            Schema::table('jadwal_presentasi', function (Blueprint $table) {
                $table->dropColumn('dosen_penguji_id');
            });
        }

        if (Schema::hasIndex('dosen_pembimbing', 'dosen_pembimbing_prodi_id_foreign')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->dropForeign('dosen_pembimbing_prodi_id_foreign');
            });
        }
        if (Schema::hasColumn('dosen_pembimbing', 'prodi_id')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->dropColumn('prodi_id');
            });
        }
    }
};
