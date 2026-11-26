<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Dosbing;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MagangStatusAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodiTrpl;
    private Prodi $prodiTrik;
    private TahunAjaran $tahunAjaran;
    private PeriodeMagang $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->prodiTrpl = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $this->prodiTrik = Prodi::where('kode_prodi', 'TRIK')->firstOrFail();

        $this->tahunAjaran = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'is_active' => true,
        ]);

        $this->periode = PeriodeMagang::create([
            'tahun_ajaran_id' => $this->tahunAjaran->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);
    }

    private function createAdmin(Prodi $prodi): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nama' => 'Admin ' . $prodi->kode_prodi,
        ]);

        return $user;
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    private function createMahasiswa(Prodi $prodi, string $nim): Mahasiswa
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);

        return Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => $nim,
            'nama' => 'Mahasiswa ' . $nim,
            'angkatan' => '2024',
        ]);
    }

    private function createMitra(): Mitra
    {
        $user = User::factory()->create(['role' => 'mitra']);

        return Mitra::create([
            'user_id' => $user->user_id,
            'nama_mitra' => 'PT Mitra Uji',
        ]);
    }

    private function createDosbing(): Dosbing
    {
        return Dosbing::create([
            'nip' => '1987654321',
            'nama' => 'Dosen Uji',
        ]);
    }

    private function createMagang(
        Mahasiswa $mahasiswa,
        ?Mitra $mitra = null,
        ?Dosbing $dosbing = null
    ): Magang {
        return Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'mitra_id' => $mitra?->mitra_id,
            'dosbing_id' => $dosbing?->dosbing_id,
            'periode_id' => $this->periode->periode_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_admin_prodi_can_update_status_for_own_prodi(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0001/SV/00001');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'selesai',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status_magang', 'selesai');

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'selesai',
        ]);
    }

    public function test_admin_prodi_cannot_update_status_for_other_prodi(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrik, '24/0002/SV/00002');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'selesai',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_super_admin_can_update_status_across_prodi(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $mahasiswa = $this->createMahasiswa($this->prodiTrik, '24/0003/SV/00003');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($superAdmin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'berlangsung',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status_magang', 'berlangsung');

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'berlangsung',
        ]);
    }

    public function test_mahasiswa_cannot_update_own_status_via_api(): void
    {
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0004/SV/00004');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($mahasiswa->user)
            ->putJson("/api/magang/{$magang->magang_id}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Hanya admin yang berwenang mengubah status magang.');

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_mahasiswa_cannot_set_status_when_creating_magang(): void
    {
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0005/SV/00005');

        $response = $this->actingAs($mahasiswa->user)->postJson('/api/magang', [
            'periode_id' => $this->periode->periode_id,
            'status_magang' => 'selesai',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseCount('magang', 0);
    }

    public function test_invalid_status_is_rejected_with_422(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0006/SV/00006');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'menunggu konfirmasi',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status_magang']);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_status_update_preserves_magang_relations(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0007/SV/00007');
        $mitra = $this->createMitra();
        $dosbing = $this->createDosbing();
        $magang = $this->createMagang($mahasiswa, $mitra, $dosbing);

        $response = $this->actingAs($admin)->putJson("/api/magang/{$magang->magang_id}", [
            'status_magang' => 'selesai',
        ]);

        $response->assertOk();

        $fresh = $magang->fresh();
        $this->assertSame((int) $mahasiswa->mahasiswa_id, (int) $fresh->mahasiswa_id);
        $this->assertSame((int) $mitra->mitra_id, (int) $fresh->mitra_id);
        $this->assertSame((int) $dosbing->dosbing_id, (int) $fresh->dosbing_id);
        $this->assertSame((int) $this->periode->periode_id, (int) $fresh->periode_id);
        $this->assertSame('selesai', $fresh->status_magang);

        // Endpoint lain yang menampilkan status tetap berjalan.
        $list = $this->actingAs($admin)->getJson('/api/magang');
        $list->assertOk()
            ->assertJsonPath('data.0.status_magang', 'selesai');
    }

    public function test_admin_can_create_magang_with_official_status(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0008/SV/00008');

        $response = $this->actingAs($admin)->postJson('/api/magang', [
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => $this->periode->periode_id,
            'status_magang' => 'berlangsung',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status_magang', 'berlangsung');

        $this->assertDatabaseHas('magang', [
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'status_magang' => 'berlangsung',
        ]);
    }

    public function test_mahasiswa_cannot_update_status_via_post_alias(): void
    {
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0009/SV/00009');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($mahasiswa->user)
            ->postJson("/api/magang/{$magang->magang_id}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_mitra_cannot_update_status_for_own_magang(): void
    {
        $mitra = $this->createMitra();
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0010/SV/00010');
        $magang = $this->createMagang($mahasiswa, $mitra);

        $response = $this->actingAs($mitra->user)
            ->putJson("/api/magang/{$magang->magang_id}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Hanya admin yang berwenang mengubah status magang.');

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_dosbing_cannot_update_magang_status(): void
    {
        $dosbingUser = User::factory()->create(['role' => 'dosbing']);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0011/SV/00011');
        $magang = $this->createMagang($mahasiswa);

        $response = $this->actingAs($dosbingUser)
            ->putJson("/api/magang/{$magang->magang_id}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }

    public function test_status_only_update_does_not_resync_existing_period_columns(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0012/SV/00012');
        $magang = $this->createMagang($mahasiswa);

        // Simulasikan data historis yang kolom periodenya berbeda dari master.
        DB::table('magang')
            ->where('magang_id', $magang->magang_id)
            ->update([
                'semester_magang' => 5,
                'tahun_ajaran' => 2020,
            ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/magang/{$magang->magang_id}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status_magang', 'selesai');

        $fresh = $magang->fresh();
        $this->assertSame('selesai', $fresh->status_magang);
        $this->assertSame((int) $this->periode->periode_id, (int) $fresh->periode_id);
        $this->assertSame(5, (int) $fresh->semester_magang);
        $this->assertSame(2020, (int) $fresh->tahun_ajaran);
    }

    public function test_status_update_does_not_backfill_periode_id_for_legacy_row(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0013/SV/00013');

        // Data legacy: belum punya periode_id, hanya kolom lama yang terisi.
        $magangId = DB::table('magang')->insertGetId([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => null,
            'semester_magang' => 6,
            'tahun_ajaran' => 2026,
            'status_magang' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/magang/{$magangId}", [
                'status_magang' => 'berlangsung',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status_magang', 'berlangsung');

        $row = DB::table('magang')->where('magang_id', $magangId)->first();
        $this->assertNull($row->periode_id);
        $this->assertSame('berlangsung', $row->status_magang);
    }

    public function test_status_update_works_when_legacy_periode_id_is_not_in_master(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0014/SV/00014');

        // Data legacy dengan relasi periode yang tidak valid. Status harus
        // tetap dapat dikelola admin tanpa memicu error dari hook model.
        $magangId = DB::table('magang')->insertGetId([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => 999999,
            'semester_magang' => 6,
            'tahun_ajaran' => 2026,
            'status_magang' => 'draft',
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/magang/{$magangId}", [
                'status_magang' => 'selesai',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status_magang', 'selesai');

        $row = DB::table('magang')->where('magang_id', $magangId)->first();
        $this->assertSame(999999, (int) $row->periode_id);
        $this->assertSame('selesai', $row->status_magang);
    }

    public function test_explicit_period_change_still_syncs_legacy_columns(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0015/SV/00015');
        $magang = $this->createMagang($mahasiswa);

        $tahunAjaranLama = TahunAjaran::create([
            'tahun_ajaran' => '2025/2026',
            'is_active' => true,
        ]);
        $periodeLama = PeriodeMagang::create([
            'tahun_ajaran_id' => $tahunAjaranLama->tahun_ajaran_id,
            'semester_magang' => 5,
            'jenis_semester' => 'Ganjil',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)
            ->putJson("/api/magang/{$magang->magang_id}", [
                'periode_id' => $periodeLama->periode_id,
            ]);

        $response->assertOk();

        $fresh = $magang->fresh();
        $this->assertSame((int) $periodeLama->periode_id, (int) $fresh->periode_id);
        $this->assertSame(5, (int) $fresh->semester_magang);
        $this->assertSame(2025, (int) $fresh->tahun_ajaran);
    }

    public function test_legacy_rejected_status_remains_readable(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0016/SV/00016');

        $magangId = DB::table('magang')->insertGetId([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => null,
            'semester_magang' => 6,
            'tahun_ajaran' => 2026,
            'status_magang' => 'ditolak',
        ]);

        $response = $this->actingAs($superAdmin)->getJson('/api/magang');

        $response->assertOk()
            ->assertJsonFragment([
                'magang_id' => $magangId,
                'status_magang' => 'ditolak',
            ]);
    }

    public function test_legacy_statuses_cannot_be_written_through_official_flow(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/0017/SV/00017');
        $magang = $this->createMagang($mahasiswa);

        foreach (['ditolak', 'presentasi'] as $legacyStatus) {
            $response = $this->actingAs($admin)
                ->putJson("/api/magang/{$magang->magang_id}", [
                    'status_magang' => $legacyStatus,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['status_magang']);
        }

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'draft',
        ]);
    }
}
