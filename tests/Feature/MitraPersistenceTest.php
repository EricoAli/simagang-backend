<?php

namespace Tests\Feature;

use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MitraPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
    }

    public function test_super_admin_can_create_and_update_persistent_mitra_contact_data(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $payload = [
            'nama_mitra' => 'Mitra Persistensi Test',
            'email' => 'mitra-persistensi@example.test',
            'alamat' => 'Bandung',
            'narahubung' => 'Narahubung Awal',
            'telepon' => '081234567890',
        ];

        $created = $this->actingAs($admin)
            ->postJson('/api/mitra', $payload)
            ->assertCreated()
            ->assertJsonPath('data.alamat', 'Bandung')
            ->assertJsonPath('data.telepon', '081234567890');

        $mitraId = $created->json('data.mitra_id');
        $this->assertDatabaseHas('mitra', [
            'mitra_id' => $mitraId,
            'alamat' => 'Bandung',
            'telepon' => '081234567890',
        ]);

        $updated = [
            'nama_mitra' => 'Mitra Persistensi Test',
            'email' => 'mitra-persistensi@example.test',
            'alamat' => 'Yogyakarta',
            'narahubung' => 'Narahubung Baru',
            'telepon' => '082233445566',
        ];

        $this->putJson("/api/mitra/{$mitraId}", $updated)
            ->assertOk()
            ->assertJsonPath('data.alamat', 'Yogyakarta')
            ->assertJsonPath('data.telepon', '082233445566')
            ->assertJsonPath('data.narahubung', 'Narahubung Baru');

        $this->assertDatabaseHas('mitra', [
            'mitra_id' => $mitraId,
            'alamat' => 'Yogyakarta',
            'telepon' => '082233445566',
            'narahubung' => 'Narahubung Baru',
        ]);

        $this->getJson('/api/mitra?q=Mitra%20Persistensi%20Test')
            ->assertOk()
            ->assertJsonPath('data.0.alamat', 'Yogyakarta')
            ->assertJsonPath('data.0.telepon', '082233445566');
    }

    public function test_student_magang_detail_returns_only_its_related_mitra_information(): void
    {
        $studentUser = User::factory()->create(['role' => 'mahasiswa']);
        $student = Mahasiswa::create([
            'user_id' => $studentUser->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nim' => '24/12345/SV/90876',
            'nama' => 'Mahasiswa Mitra Test',
            'angkatan' => '2024',
        ]);

        $mitraUser = User::factory()->create([
            'role' => 'mitra',
            'email' => 'mitra-detail@example.test',
        ]);
        $relatedMitra = Mitra::create([
            'user_id' => $mitraUser->user_id,
            'nama_mitra' => 'Mitra Terkait Mahasiswa',
            'alamat' => 'Surabaya',
            'narahubung' => 'PIC Terkait',
            'telepon' => '081111222333',
            'email' => 'kontak-terkait@example.test',
        ]);

        $magang = Magang::create([
            'mahasiswa_id' => $student->mahasiswa_id,
            'mitra_id' => $relatedMitra->mitra_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 6,
            'status_magang' => 'berlangsung',
        ]);

        $unrelatedMitra = Mitra::create([
            'user_id' => User::factory()->create([
                'role' => 'mitra',
                'email' => 'mitra-other-detail@example.test',
            ])->user_id,
            'nama_mitra' => 'Mitra Tidak Terkait',
            'alamat' => 'Jakarta',
            'email' => 'kontak-lain@example.test',
        ]);

        $response = $this->actingAs($studentUser)
            ->getJson("/api/magang/{$magang->magang_id}/detail")
            ->assertOk()
            ->assertJsonPath('detail.mitra.mitra_id', $relatedMitra->mitra_id)
            ->assertJsonPath('detail.mitra.alamat', 'Surabaya')
            ->assertJsonPath('detail.mitra.narahubung', 'PIC Terkait')
            ->assertJsonPath('detail.mitra.telepon', '081111222333')
            ->assertJsonPath('detail.mitra.email', 'kontak-terkait@example.test');

        $this->assertStringNotContainsString($unrelatedMitra->nama_mitra, $response->getContent());
    }

    public function test_invalid_mitra_update_returns_not_found_and_invalid_email_returns_validation_error(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)->putJson('/api/mitra/999999', [
            'nama_mitra' => 'Tidak Ada',
            'email' => 'tidak-ada@example.test',
        ])->assertNotFound();

        $this->postJson('/api/mitra', [
            'nama_mitra' => 'Email Tidak Valid',
            'email' => 'bukan-email',
            'alamat' => 'Semarang',
            'telepon' => '081234567890',
        ])->assertStatus(422);
    }
}
