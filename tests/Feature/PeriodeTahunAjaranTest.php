<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\PeriodeMagangSeeder;
use Database\Seeders\ProdiSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodeTahunAjaranTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;
    protected Prodi $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ProdiSeeder::class, SuperAdminSeeder::class]);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function superAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nama' => 'Admin Test',
        ]);

        return $user;
    }

    private function createMahasiswa(int $prodiId, string $nim = '24/11111/SV/11111'): array
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nim' => $nim,
            'nama' => 'Mhs Test',
            'angkatan' => 2024,
        ]);

        return [$user, $mahasiswa];
    }

    public function test_prodi_endpoint_returns_dtedi_prodi_catalog_for_authenticated_users()
    {
        $this->getJson('/api/prodi')->assertStatus(401);

        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $res = $this->actingAs($admin)->getJson('/api/prodi');

        $res->assertStatus(200);
        $codes = collect($res->json('data'))->pluck('kode_prodi')->all();
        $this->assertEquals(['TRPL', 'TRIK', 'TRI', 'TRE'], $codes);
    }

    public function test_super_admin_can_crud_and_partial_toggle_tahun_ajaran_and_prevent_referenced_delete()
    {
        $superAdmin = $this->superAdmin();
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);

        // Non-super-admin cannot create
        $this->actingAs($admin)
            ->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2026/2027'])
            ->assertStatus(403);

        // Reject invalid non-consecutive or bad format with specific 422 message
        $badRes = $this->actingAs($superAdmin)
            ->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2026/2028']);
        $badRes->assertStatus(422);
        $this->assertStringContainsString('berurutan', $badRes->json('message'));

        // Create valid academic year
        $createRes = $this->actingAs($superAdmin)
            ->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2026/2027', 'is_active' => true]);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.tahun_ajaran', '2026/2027')
            ->assertJsonPath('data.is_active', true);

        $tahunId = $createRes->json('data.tahun_ajaran_id');

        // Partial PUT toggle as used by FE masterPeriode/Index.vue
        $toggleRes = $this->actingAs($superAdmin)
            ->putJson("/api/tahun-ajaran/{$tahunId}", ['is_active' => false]);
        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.tahun_ajaran_id', $tahunId)
            ->assertJsonPath('data.is_active', false);

        // Re-activate via PATCH
        $this->actingAs($superAdmin)
            ->patchJson("/api/tahun-ajaran/{$tahunId}/status", ['is_active' => true])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        // Referenced academic year cannot be deleted (409)
        PeriodeMagang::create([
            'tahun_ajaran_id' => $tahunId,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson("/api/tahun-ajaran/{$tahunId}")
            ->assertStatus(409);

        // Unused academic year can be deleted
        $unused = TahunAjaran::create(['tahun_ajaran' => '2029/2030', 'is_active' => true]);
        $this->actingAs($superAdmin)
            ->deleteJson("/api/tahun-ajaran/{$unused->tahun_ajaran_id}")
            ->assertStatus(200);
    }

    public function test_super_admin_can_manage_periode_magang_and_read_endpoints_are_shared()
    {
        $this->seed(PeriodeMagangSeeder::class);
        $superAdmin = $this->superAdmin();
        [$mhsUser] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $tahun = TahunAjaran::where('tahun_ajaran', '2026/2027')->firstOrFail();

        // Mahasiswa can read periode list with nested tahun_ajaran relation
        $listRes = $this->actingAs($mhsUser)->getJson('/api/periode-magang?is_active=1');
        $listRes->assertStatus(200);
        $first = collect($listRes->json('data'))->first();
        $this->assertNotNull($first['tahun_ajaran']['tahun_ajaran'] ?? null);
        $this->assertContains($first['jenis_semester'], ['Gasal', 'Genap']);

        // Reject duplicate combination & parity mismatch
        $dupRes = $this->actingAs($superAdmin)->postJson('/api/periode-magang', [
            'tahun_ajaran_id' => $tahun->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'genap',
        ]);
        $dupRes->assertStatus(422);

        $parityRes = $this->actingAs($superAdmin)->postJson('/api/periode-magang', [
            'tahun_ajaran_id' => $tahun->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'Gasal',
        ]);
        $parityRes->assertStatus(422);

        // Create dynamic semester 8 Genap
        $createRes = $this->actingAs($superAdmin)->postJson('/api/periode-magang', [
            'tahun_ajaran_id' => $tahun->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'genap',
            'is_active' => true,
        ]);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.jenis_semester', 'Genap')
            ->assertJsonPath('data.semester_magang', 8);

        $periodeId = $createRes->json('data.periode_id');

        // Partial PUT toggle { is_active: false } works as called by FE
        $this->actingAs($superAdmin)
            ->putJson("/api/periode-magang/{$periodeId}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // Mahasiswa cannot mutate master periode
        $this->actingAs($mhsUser)
            ->deleteJson("/api/periode-magang/{$periodeId}")
            ->assertStatus(403);
    }

    public function test_admin_and_super_admin_dashboards_filter_by_periode_and_include_aktivitas_terbaru()
    {
        $this->seed(PeriodeMagangSeeder::class);
        $superAdmin = $this->superAdmin();
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        [$u1, $mhs1] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/11111/SV/11111');
        [$u2, $mhs2] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/22222/SV/22222');
        [$u3, $mhs3] = $this->createMahasiswa($this->prodiTRIK->prodi_id, '24/33333/SV/33333');

        $mitraUser = User::factory()->create(['role' => 'mitra', 'is_active' => true]);
        $mitra = Mitra::create([
            'user_id' => $mitraUser->user_id,
            'nama_mitra' => 'PT Uji Dashboard',
        ]);

        $periode6 = PeriodeMagang::whereHas('tahunAjaran', fn ($q) => $q->where('tahun_ajaran', '2026/2027'))
            ->where('semester_magang', 6)
            ->firstOrFail();
        $periode7 = PeriodeMagang::whereHas('tahunAjaran', fn ($q) => $q->where('tahun_ajaran', '2025/2026'))
            ->where('semester_magang', 7)
            ->firstOrFail();

        Magang::create([
            'mahasiswa_id' => $mhs1->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'tahun_ajaran' => '2026/2027',
            'tahun_ajaran_id' => $periode6->tahun_ajaran_id,
            'periode_id' => $periode6->periode_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'status_magang' => 'berlangsung',
        ]);

        Magang::create([
            'mahasiswa_id' => $mhs2->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'tahun_ajaran' => '2026/2027',
            'tahun_ajaran_id' => $periode6->tahun_ajaran_id,
            'periode_id' => $periode6->periode_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'status_magang' => 'draft',
        ]);

        Magang::create([
            'mahasiswa_id' => $mhs3->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'tahun_ajaran' => '2025/2026',
            'tahun_ajaran_id' => $periode7->tahun_ajaran_id,
            'periode_id' => $periode7->periode_id,
            'semester_magang' => 7,
            'jenis_semester' => 'Gasal',
            'status_magang' => 'berlangsung',
        ]);

        // Admin TRPL only sees 1 ongoing TRPL student (not draft, not TRIK) + 1 aktivitas_terbaru
        $resAdmin = $this->actingAs($adminTRPL)->getJson('/api/dashboard/admin');
        $resAdmin->assertStatus(200)
            ->assertJsonPath('jumlah_mahasiswa_aktif_magang', 1)
            ->assertJsonPath('jumlah_mahasiswa', 2);
        $this->assertCount(1, $resAdmin->json('aktivitas_terbaru'));
        $this->assertEquals('TRPL', $resAdmin->json('aktivitas_terbaru.0.kode_prodi'));

        // Super Admin can access /dashboard/admin (with filter) & /super-admin/dashboard (with periode_id)
        $resSuperDashboardAdmin = $this->actingAs($superAdmin)->getJson("/api/dashboard/admin?periode_id={$periode6->periode_id}");
        $resSuperDashboardAdmin->assertStatus(200)
            ->assertJsonPath('jumlah_mahasiswa_aktif_magang', 1);

        $resSuperAgg = $this->actingAs($superAdmin)->getJson("/api/super-admin/dashboard?periode_id={$periode7->periode_id}");
        $resSuperAgg->assertStatus(200)
            ->assertJsonPath('statistik_keseluruhan.total_mahasiswa_aktif_magang', 1);
    }
}
