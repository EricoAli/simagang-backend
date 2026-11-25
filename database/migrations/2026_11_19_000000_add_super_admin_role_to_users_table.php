<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambahkan role eksplisit "super_admin" pada tabel users.
 *
 * Migration ini tidak mengubah role user yang sudah ada dan tidak
 * menghapus data apa pun. Super Admin harus ditetapkan secara eksplisit
 * (mis. UPDATE users SET role = 'super_admin' WHERE username = '...').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'mahasiswa', 'mitra', 'dosbing', 'super_admin'])
                ->nullable(false)
                ->change();
        });
    }

    public function down(): void
    {
        // Role super_admin tidak dikenal oleh skema lama. Turunkan ke admin
        // agar data user tetap ada (bukan dihapus) sebelum enum dipersempit.
        DB::table('users')
            ->where('role', 'super_admin')
            ->update(['role' => 'admin']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'mahasiswa', 'mitra', 'dosbing'])
                ->nullable(false)
                ->change();
        });
    }
};
