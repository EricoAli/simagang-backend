<?php

namespace Tests\Feature;

use App\Exports\MagangExport;
use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagangExportTest extends TestCase
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

    private function createSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    private function createMahasiswa(int $prodiId, string $nama = 'Mhs'): array
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        $mahasiswa = Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nim'      => '24/' . rand(10000, 99999) . '/SV/' . rand(10000, 99999),
            'nama'     => $nama . ' ' . rand(100, 999),
            'angkatan' => 2024,
        ]);
        return [$user, $mahasiswa];
    }

    private function createMitra(string $nama = 'PT Mitra'): array
    {
        $user = User::factory()->create(['role' => 'mitra', 'is_active' => true]);
        $mitra = Mitra::create([
            'user_id'    => $user->user_id,
            'nama_mitra' => $nama . ' ' . rand(100, 999),
            'email'      => 'mitra' . rand(100, 999) . '@corp.id',
            'narahubung' => 'Pak Budi',
        ]);
        return [$user, $mitra];
    }

    private function createMagang(int $mahasiswaId, int $mitraId, string $roleMagang = 'Engineer'): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'mitra_id'        => $mitraId,
            'semester_magang' => 6,
            'role_magang'     => $roleMagang,
            'jobdesk'         => 'Test Jobdesk',
            'periode_bulan'   => 5,
            'status_magang'   => 'berlangsung',
        ]);
    }

    public function test_magang_export_scopes_by_mitra_role_and_only_includes_own_interns()
    {
        [$mhsUser1, $mhs1] = $this->createMahasiswa($this->prodiTRPL->prodi_id, 'Mhs Alpha');
        [$mhsUser2, $mhs2] = $this->createMahasiswa($this->prodiTRPL->prodi_id, 'Mhs Beta');

        [$mitraUserA, $mitraA] = $this->createMitra('PT Solusi Digital');
        [$mitraUserB, $mitraB] = $this->createMitra('PT Inovasi Cipta');

        $magangA = $this->createMagang($mhs1->mahasiswa_id, $mitraA->mitra_id, 'Backend Intern A');
        $magangB = $this->createMagang($mhs2->mahasiswa_id, $mitraB->mitra_id, 'Frontend Intern B');

        // Mitra A mengakses endpoint ekspor
        $response = $this->actingAs($mitraUserA)->get('/api/magang/export/excel');
        $response->assertStatus(200);

        // Verifikasi koleksi data export untuk Mitra A
        $exportMitraA = new MagangExport(null, null, null, $mitraA->mitra_id);
        $collectionA = $exportMitraA->collection();

        $this->assertCount(1, $collectionA);
        $this->assertEquals($magangA->magang_id, $collectionA->first()->magang_id);
        $this->assertNotEquals($magangB->magang_id, $collectionA->first()->magang_id);
    }

    public function test_magang_export_scopes_by_admin_prodi_role_and_only_includes_own_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        [$mhsTRPLUser, $mhsTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id, 'Mhs TRPL');
        [$mhsTRIKUser, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id, 'Mhs TRIK');

        [$mitraUser, $mitra] = $this->createMitra('PT Partner Nasional');

        $magangTRPL = $this->createMagang($mhsTRPL->mahasiswa_id, $mitra->mitra_id, 'Developer TRPL');
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id, $mitra->mitra_id, 'Engineer TRIK');

        // Admin TRPL mengakses endpoint
        $response = $this->actingAs($adminTRPL)->get('/api/magang/export/excel');
        $response->assertStatus(200);

        // Verifikasi koleksi data export untuk Admin TRPL
        $exportAdminTRPL = new MagangExport(null, null, $this->prodiTRPL->prodi_id, null);
        $collectionTRPL = $exportAdminTRPL->collection();

        $this->assertCount(1, $collectionTRPL);
        $this->assertEquals($magangTRPL->magang_id, $collectionTRPL->first()->magang_id);
    }

    public function test_magang_export_super_admin_can_export_all_or_filter_by_mitra()
    {
        $superAdmin = $this->createSuperAdmin();

        [$mhsUser1, $mhs1] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$mhsUser2, $mhs2] = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        [$mitraUserA, $mitraA] = $this->createMitra('PT Alpha');
        [$mitraUserB, $mitraB] = $this->createMitra('PT Beta');

        $magangA = $this->createMagang($mhs1->mahasiswa_id, $mitraA->mitra_id);
        $magangB = $this->createMagang($mhs2->mahasiswa_id, $mitraB->mitra_id);

        // Super Admin tanpa filter -> 200
        $resAll = $this->actingAs($superAdmin)->get('/api/magang/export/excel');
        $resAll->assertStatus(200);

        $exportAll = new MagangExport(null, null, null, null);
        $this->assertCount(2, $exportAll->collection());

        // Super Admin dengan query param mitra_id
        $resFilterMitra = $this->actingAs($superAdmin)->get("/api/magang/export/excel?mitra_id={$mitraA->mitra_id}");
        $resFilterMitra->assertStatus(200);

        $exportFilter = new MagangExport(null, null, null, $mitraA->mitra_id);
        $filteredCollection = $exportFilter->collection();
        $this->assertCount(1, $filteredCollection);
        $this->assertEquals($magangA->magang_id, $filteredCollection->first()->magang_id);
    }

    public function test_magang_export_returns_403_for_unauthorized_student()
    {
        [$mhsUser, $mhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $response = $this->actingAs($mhsUser)->get('/api/magang/export/excel');
        $response->assertStatus(403);
    }
}
