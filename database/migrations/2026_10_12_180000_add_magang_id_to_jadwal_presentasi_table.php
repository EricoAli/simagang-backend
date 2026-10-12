<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jadwal_presentasi', function (Blueprint $table) {
            $table->unsignedBigInteger('magang_id')->nullable()->after('jadwal_id');
            $table->foreign('magang_id')
                  ->references('magang_id')
                  ->on('magang')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_presentasi', function (Blueprint $table) {
            $table->dropForeign(['magang_id']);
            $table->dropColumn('magang_id');
        });
    }
};
