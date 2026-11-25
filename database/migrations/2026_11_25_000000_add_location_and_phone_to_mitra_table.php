<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addAlamat = !Schema::hasColumn('mitra', 'alamat');
        $addTelepon = !Schema::hasColumn('mitra', 'telepon');

        if (!$addAlamat && !$addTelepon) {
            return;
        }

        Schema::table('mitra', function (Blueprint $table) use ($addAlamat, $addTelepon) {
            if ($addAlamat) {
                $table->string('alamat', 255)->nullable();
            }

            if ($addTelepon) {
                $table->string('telepon', 50)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Keep these fields on rollback so saved Mitra contact information is
        // never removed as a side effect of rolling back this migration.
    }
};
