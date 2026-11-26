<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menautkan akun login ber-role `dosbing` ke data master dosen pembimbing.
 *
 * Migration ini additive dan nullable agar data dosen pembimbing lama tetap
 * valid. Akun dosbing yang belum tertaut tidak otomatis diberi akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('dosen_pembimbing', 'user_id')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')
                    ->nullable()
                    ->unique()
                    ->after('dosbing_id');
            });
        }

        if (!Schema::hasIndex('dosen_pembimbing', 'dosen_pembimbing_user_id_foreign')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->foreign('user_id', 'dosen_pembimbing_user_id_foreign')
                    ->references('user_id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('dosen_pembimbing', 'dosen_pembimbing_user_id_foreign')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->dropForeign('dosen_pembimbing_user_id_foreign');
            });
        }

        if (Schema::hasColumn('dosen_pembimbing', 'user_id')) {
            Schema::table('dosen_pembimbing', function (Blueprint $table) {
                $table->dropColumn('user_id');
            });
        }
    }
};
