<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagangIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;
    protected Prodi $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Admin::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nama'     => 'Admin ' . rand(100, 999),
        ]);
        return $user;
    }

    private function createMahasiswa(int $prodiId): array
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        $mahasiswa = Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nim'      => '24/' . rand(10000, 99999) . '/SV/' . rand(10000, 99999),
            'nama'     => 'Mahasiswa ' . rand(100, 999),
            'angkatan' => 2024,
        ]);
        return [$user, $mahasiswa];
    }

    private function createMitra(): array
    {
        $user = User::factory()->create(['role' => 'mitra', 'is_active' => true]);
        $mitra = Mitra::create([
            'user_id'    => $user->user_id,
            'nama_mitra' => 'PT Mitra Integrasi ' . rand(100, 999),
            'email'      => 'mitra' . rand(1000, 9999) . '@corp.id',
            'narahubung' => 'Pak Joko',
        ]);
        return [$user, $mitra];
    }

    private function createMagang(int $mahasiswaId, int $mitraId, string $status = 'draft'): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'mitra_id'        => $mitraId,
            'semester_magang' => 6,
            'role_magang'     => 'Backend Engineer',
            'jobdesk'         => 'API Development',
            'periode_bulan'   => 5,
            'status_magang'   => $status,
        ]);
    }

    public function test_magang_update_status_persists_to_database()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mitraUser, $mitra] = $this->createMitra();

        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id, 'draft');

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'selesai',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('magang', [
            'magang_id'     => $magang->magang_id,
            'status_magang' => 'selesai',
        ]);
    }

    public function test_magang_update_normalizes_aliases_berjalan_and_revisi_to_berlangsung()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mitraUser, $mitra] = $this->createMitra();

        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id, 'draft');

        // Alias 'revisi' -> 'berlangsung'
        $resRevisi = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'revisi',
        ]);
        $resRevisi->assertStatus(200);
        $this->assertDatabaseHas('magang', [
            'magang_id'     => $magang->magang_id,
            'status_magang' => 'berlangsung',
        ]);

        // Alias 'berjalan' -> 'berlangsung'
        $resBerjalan = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'berjalan',
        ]);
        $resBerjalan->assertStatus(200);
        $this->assertDatabaseHas('magang', [
            'magang_id'     => $magang->magang_id,
            'status_magang' => 'berlangsung',
        ]);
    }

    public function test_magang_update_rejects_invalid_status_with_422()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mitraUser, $mitra] = $this->createMitra();

        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id, 'draft');

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'invalid_status_xyz',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status_magang');
    }

    public function test_magang_show_detail_includes_latest_jadwal_presentasi()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mitraUser, $mitra] = $this->createMitra();

        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id, 'berlangsung');

        $jadwal = JadwalPresentasi::create([
            'magang_id'          => $magang->magang_id,
            'tanggal_presentasi' => '2026-11-25',
            'waktu_mulai'        => '10:00:00',
            'waktu_selesai'      => '11:30:00',
            'ruangan'            => 'Lab RPL 1',
            'keterangan'         => 'Sidang Hasil Magang',
        ]);

        $response = $this->actingAs($admin)->getJson("/api/magang/{$magang->magang_id}/detail");
        $response->assertStatus(200);
        $this->assertNotNull($response->json('jadwal_presentasi'));
        $this->assertEquals($jadwal->jadwal_id, $response->json('jadwal_presentasi.jadwal_id'));
        $this->assertEquals('Lab RPL 1', $response->json('jadwal_presentasi.ruangan'));
    }

    public function test_unauthorized_user_from_different_prodi_cannot_update_magang()
    {
        $adminTRIK = $this->createAdmin($this->prodiTRIK->prodi_id);
        [$mhsUserTRPL, $mahasiswaTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mitraUser, $mitra] = $this->createMitra();

        $magangTRPL = $this->createMagang($mahasiswaTRPL->mahasiswa_id, $mitra->mitra_id, 'draft');

        // Admin TRIK mencoba update magang mahasiswa TRPL
        $response = $this->actingAs($adminTRIK)->putJson("/api/magang/{$magangTRPL->magang_id}", [
            'status_magang' => 'selesai',
        ]);

        $response->assertStatus(403);
    }
}
