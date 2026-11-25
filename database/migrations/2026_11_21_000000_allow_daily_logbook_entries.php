<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('logbook', 'tanggal_kegiatan')) {
            Schema::table('logbook', function (Blueprint $table) {
                // Keep existing rows valid; new API-created entries require this field.
                $table->date('tanggal_kegiatan')->nullable()->after('magang_id');
            });
        }

        // MySQL needs a replacement left-prefix index before dropping the
        // unique index that currently also backs the foreign key.
        if (!Schema::hasIndex('logbook', 'logbook_magang_tanggal_index')) {
            Schema::table('logbook', function (Blueprint $table) {
                $table->index(['magang_id', 'tanggal_kegiatan'], 'logbook_magang_tanggal_index');
            });
        }

        if (Schema::hasIndex('logbook', 'logbook_magang_id_unique', 'unique')) {
            Schema::table('logbook', function (Blueprint $table) {
                $table->dropUnique(['magang_id']);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasIndex('logbook', 'logbook_magang_id_index')) {
            Schema::table('logbook', function (Blueprint $table) {
                $table->index('magang_id', 'logbook_magang_id_index');
            });
        }

        if (Schema::hasIndex('logbook', 'logbook_magang_tanggal_index')) {
            Schema::table('logbook', function (Blueprint $table) {
                $table->dropIndex('logbook_magang_tanggal_index');
            });
        }

        if (Schema::hasColumn('logbook', 'tanggal_kegiatan')) {
            Schema::table('logbook', function (Blueprint $table) {
                $table->dropColumn('tanggal_kegiatan');
            });
        }

        // Keep a non-unique index for the foreign key. Do not recreate the
        // historical unique constraint: preserved daily rows may now coexist.
    }
};
