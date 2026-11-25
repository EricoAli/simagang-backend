<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Mahasiswa;
use App\Models\PeriodeMagang;
use App\Models\TahunAjaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MagangStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_magang_validation_and_creation(): void
    {
        // 1. Create a mahasiswa user
        $user = User::create([
            'username' => 'testmahasiswa',
            'password' => bcrypt('password123'),
            'role' => 'mahasiswa',
            'email' => 'testmhs@example.com',
        ]);

        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $prodi = \App\Models\Prodi::where('kode_prodi', 'TRPL')->first();

        $mahasiswa = Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => 'TEST12345',
            'nama' => 'Test Mahasiswa',
            'angkatan' => '2023',
        ]);

        // 2. Authenticate the user using Sanctum
        Sanctum::actingAs($user, ['*']);

        $tahunAjaran = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'is_active' => true,
        ]);
        $periode = PeriodeMagang::create([
            'tahun_ajaran_id' => $tahunAjaran->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        // 3. Prepare files and post data
        $filePenerimaan = UploadedFile::fake()->create('penerimaan.pdf', 100, 'application/pdf');
        $filePraKRS = UploadedFile::fake()->create('krs.pdf', 100, 'application/pdf');

        $payload = [
            'periode_id' => $periode->periode_id,
            'semester_magang' => 6,
            'role_magang' => 'Software Engineer',
            'jobdesk' => 'Developing features',
            'periode_bulan' => 6,
            'dokumenPenerimaan' => $filePenerimaan,
            'dokumenPraKRS' => $filePraKRS,
        ];

        // 4. Hit the store endpoint
        $response = $this->postJson('/api/magang', $payload);

        // 5. Assert and dump on failure
        if ($response->status() !== 201) {
            dump($response->json());
        }

        $response->assertStatus(201);
    }
}
