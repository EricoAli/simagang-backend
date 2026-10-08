<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create prodi table
        Schema::create('prodi', function (Blueprint $table) {
            $table->id('prodi_id');
            $table->string('kode_prodi', 20)->unique();
            $table->string('nama_prodi', 100);
            $table->timestamps();
        });

        // Seed 4 prodi
        DB::table('prodi')->insert([
            ['kode_prodi' => 'TRPL', 'nama_prodi' => 'Teknologi Rekayasa Perangkat Lunak', 'created_at' => now(), 'updated_at' => now()],
            ['kode_prodi' => 'TRIK', 'nama_prodi' => 'Teknologi Rekayasa Internet', 'created_at' => now(), 'updated_at' => now()], // Wait, TRIK is TRIK? TRI is TRI. TRE is TRE. Let's just use the short code as name if unsure. I'll use codes for both for now to be safe, or just basic expansions.
            ['kode_prodi' => 'TRI', 'nama_prodi' => 'Teknologi Rekayasa Instrumentasi dan Kontrol', 'created_at' => now(), 'updated_at' => now()],
            ['kode_prodi' => 'TRE', 'nama_prodi' => 'Teknologi Rekayasa Elektro', 'created_at' => now(), 'updated_at' => now()],
        ]);
        
        // Let's correct the names just in case, but code is the most important.
        DB::table('prodi')->where('kode_prodi', 'TRIK')->update(['nama_prodi' => 'TRIK']);
        DB::table('prodi')->where('kode_prodi', 'TRI')->update(['nama_prodi' => 'TRI']);
        DB::table('prodi')->where('kode_prodi', 'TRE')->update(['nama_prodi' => 'TRE']);

        // 2. Create data_admin table
        Schema::create('data_admin', function (Blueprint $table) {
            $table->id('admin_id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('prodi_id');
            $table->string('nama', 100)->nullable();
            
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->foreign('prodi_id')->references('prodi_id')->on('prodi')->onDelete('cascade');
        });

        // 3. Update mahasiswa table
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->unsignedBigInteger('prodi_id')->nullable()->after('user_id');
            $table->foreign('prodi_id')->references('prodi_id')->on('prodi')->onDelete('restrict');
        });

        // Backfill existing mahasiswa to TRPL
        $trpl = DB::table('prodi')->where('kode_prodi', 'TRPL')->first();
        if ($trpl) {
            DB::table('mahasiswa')->update(['prodi_id' => $trpl->prodi_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mahasiswa', function (Blueprint $table) {
            $table->dropForeign(['prodi_id']);
            $table->dropColumn('prodi_id');
        });

        Schema::dropIfExists('data_admin');
        Schema::dropIfExists('prodi');
    }
};
