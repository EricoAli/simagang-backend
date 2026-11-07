<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrossProdiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiTRPL;
    protected $prodiTRIK;

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
            'nim'      => '24/' . rand(10000, 99999) . '/SV/' . rand(1000, 9999),
            'nama'     => 'Mahasiswa ' . rand(100, 999),
            'angkatan' => 2024,
        ]);
        return [$user, $mahasiswa];
    }

    private function createMagang(int $mahasiswaId): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'semester_magang' => 6,
            'role_magang'     => 'Developer',
            'jobdesk'         => 'Backend',
        ]);
    }

    // 12. Admin Prodi tidak dapat membaca atau memodifikasi data magang & logbook prodi lain
    public function test_admin_prodi_cannot_view_update_or_delete_other_prodi_magang()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        // 1. Admin TRPL get detail Magang TRIK -> 403
        $resShow = $this->actingAs($adminTRPL)->getJson("/api/magang/{$magangTRIK->magang_id}");
        $resShow->assertStatus(403);

        // 2. Admin TRPL update Magang TRIK -> 403
        $resUpdate = $this->actingAs($adminTRPL)->putJson("/api/magang/{$magangTRIK->magang_id}", [
            'role_magang' => 'Hacked Role',
        ]);
        $resUpdate->assertStatus(403);

        // 3. Admin TRPL delete Magang TRIK -> 403
        $resDelete = $this->actingAs($adminTRPL)->deleteJson("/api/magang/{$magangTRIK->magang_id}");
        $resDelete->assertStatus(403);
    }

    // 13. Mahasiswa tidak dapat memodifikasi resource magang/logbook milik mahasiswa lain
    public function test_mahasiswa_cannot_view_or_modify_other_student_magang_or_logbook()
    {
        [$userA, $mhsA] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$userB, $mhsB] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $magangB = $this->createMagang($mhsB->mahasiswa_id);
        $logbookB = Logbook::create([
            'magang_id' => $magangB->magang_id,
            'kegiatan'  => 'Mengerjakan modul A',
        ]);

        // Mahasiswa A mencoba akses magang Mahasiswa B -> 403
        $resMagang = $this->actingAs($userA)->getJson("/api/magang/{$magangB->magang_id}");
        $resMagang->assertStatus(403);

        // Mahasiswa A mencoba update magang Mahasiswa B -> 403
        $resMagangUpdate = $this->actingAs($userA)->putJson("/api/magang/{$magangB->magang_id}", [
            'role_magang' => 'Hacked',
        ]);
        $resMagangUpdate->assertStatus(403);

        // Mahasiswa A mencoba update logbook Mahasiswa B -> 403
        $resLogbookUpdate = $this->actingAs($userA)->putJson("/api/logbook/{$logbookB->logbook_id}", [
            'kegiatan' => 'Hacked kegiatan',
        ]);
        $resLogbookUpdate->assertStatus(403);

        // Mahasiswa A mencoba hapus logbook Mahasiswa B -> 403
        $resLogbookDelete = $this->actingAs($userA)->deleteJson("/api/logbook/{$logbookB->logbook_id}");
        $resLogbookDelete->assertStatus(403);
    }

    // 14. Mahasiswa tidak dapat mengelola jadwal presentasi
    public function test_mahasiswa_cannot_create_or_delete_jadwal_presentasi()
    {
        [$user, $mhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $response = $this->actingAs($user)->postJson('/api/jadwal-presentasi', [
            'tanggal_presentasi' => '2026-11-01',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '11:00',
            'ruangan'            => 'Lab 101',
        ]);

        $response->assertStatus(403);
    }

    // 15. Dashboard Admin dibatasi hanya untuk prodi admin tersebut
    public function test_admin_dashboard_is_isolated_to_admin_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRPL)->getJson('/api/dashboard/admin');

        $response->assertStatus(200);
        // Mahasiswa yang dihitung untuk TRPL harus 2, bukan 3
        $this->assertEquals(2, $response->json('jumlah_mahasiswa'));
    }

    // 16. Mitra tidak dapat menghapus atau mengubah magang yang bukan mitra miliknya
    public function test_mitra_cannot_delete_or_modify_magang()
    {
        $userMitra = User::factory()->create(['role' => 'mitra', 'is_active' => true]);
        $mitra = Mitra::create([
            'user_id'    => $userMitra->user_id,
            'nama_mitra' => 'PT Mitra Sejahtera',
        ]);

        [$userMhs, $mhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magang = $this->createMagang($mhs->mahasiswa_id);

        // Mitra mencoba hapus magang -> 403
        $resDelete = $this->actingAs($userMitra)->deleteJson("/api/magang/{$magang->magang_id}");
        $resDelete->assertStatus(403);

        // Mitra mencoba update magang -> 403
        $resUpdate = $this->actingAs($userMitra)->putJson("/api/magang/{$magang->magang_id}", [
            'role_magang' => 'Hacked',
        ]);
        $resUpdate->assertStatus(403);
    }

    // 17. Unduh file fisik dokumen & laporan dilindungi otorisasi kepemilikan dan prodi (Mendukung private storage dan fallback legacy)
    public function test_physical_file_download_is_protected_by_ownership_and_prodi_authorization()
    {
        Storage::fake('local');
        Storage::fake('public');

        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        // A. Tes legacy file di disk public
        $fakeDocPath = 'dokumen_magang/legacy_test.pdf';
        Storage::disk('public')->put($fakeDocPath, 'content of legacy doc');
        $dokumenTRIK = DokumenMagang::create([
            'magang_id'     => $magangTRIK->magang_id,
            'jenis_dokumen' => 'doc_laporan_magang',
            'nama_file'     => 'legacy_test.pdf',
            'file_path'     => $fakeDocPath,
        ]);

        $fakeLaporanPath = 'laporan-magang/legacy_lap.pdf';
        Storage::disk('public')->put($fakeLaporanPath, 'content of legacy lap');
        $laporanTRIK = Laporan::create([
            'magang_id' => $magangTRIK->magang_id,
            'nama_file' => 'legacy_lap.pdf',
            'file_path' => $fakeLaporanPath,
        ]);

        [$userTRPL, $mhsTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        // 1. Mahasiswa prodi lain mencoba unduh -> 403
        $resDocOtherMhs = $this->actingAs($userTRPL)->getJson("/api/dokumen-magang/{$dokumenTRIK->dokumen_id}/download");
        $resDocOtherMhs->assertStatus(403);

        $resLapOtherMhs = $this->actingAs($userTRPL)->getJson("/api/laporan/{$laporanTRIK->laporan_id}/download");
        $resLapOtherMhs->assertStatus(403);

        // 2. Admin prodi lain mencoba unduh -> 403
        $resDocAdminTRPL = $this->actingAs($adminTRPL)->getJson("/api/dokumen-magang/{$dokumenTRIK->dokumen_id}/download");
        $resDocAdminTRPL->assertStatus(403);

        $resLapAdminTRPL = $this->actingAs($adminTRPL)->getJson("/api/laporan/{$laporanTRIK->laporan_id}/download");
        $resLapAdminTRPL->assertStatus(403);

        // 3. Pemilik yang sah (Mahasiswa TRIK) dapat mengunduh legacy file -> 200
        $resDocOwner = $this->actingAs($userTRIK)->get("/api/dokumen-magang/{$dokumenTRIK->dokumen_id}/download");
        $resDocOwner->assertStatus(200);

        $resLapOwner = $this->actingAs($userTRIK)->get("/api/laporan/{$laporanTRIK->laporan_id}/download");
        $resLapOwner->assertStatus(200);

        // B. Tes file baru di disk privat local
        $fakePrivateDocPath = 'dokumen_magang/private_test.pdf';
        Storage::disk('local')->put($fakePrivateDocPath, 'content of private doc');
        $privateDocTRIK = DokumenMagang::create([
            'magang_id'     => $magangTRIK->magang_id,
            'jenis_dokumen' => 'doc_pra_krs',
            'nama_file'     => 'private_test.pdf',
            'file_path'     => $fakePrivateDocPath,
        ]);

        // Pemilik yang sah dapat mengunduh file dari private storage -> 200
        $resPrivateDocOwner = $this->actingAs($userTRIK)->get("/api/dokumen-magang/{$privateDocTRIK->dokumen_id}/download");
        $resPrivateDocOwner->assertStatus(200);

        // Mahasiswa prodi lain ditolak dari private storage -> 403
        $resPrivateDocOther = $this->actingAs($userTRPL)->getJson("/api/dokumen-magang/{$privateDocTRIK->dokumen_id}/download");
        $resPrivateDocOther->assertStatus(403);
    }

    // 18. Role dosbing ditolak pada endpoint index dokumen dan logbook
    public function test_dosbing_role_rejected_from_dokumen_and_logbook_indexes()
    {
        $dosbingUser = User::factory()->create(['role' => 'dosbing', 'is_active' => true]);

        $resDoc = $this->actingAs($dosbingUser)->getJson('/api/dokumen-magang');
        $resDoc->assertStatus(403);

        $resLog = $this->actingAs($dosbingUser)->getJson('/api/logbook');
        $resLog->assertStatus(403);
    }

    // 19. Endpoint show laporan mendukung pencarian berdasarkan laporan_id maupun magang_id
    public function test_laporan_show_supports_both_laporan_id_and_magang_id()
    {
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        $laporanTRIK = Laporan::create([
            'magang_id' => $magangTRIK->magang_id,
            'nama_file' => 'lap.pdf',
            'file_path' => 'laporan-magang/lap.pdf',
        ]);

        // 1. Lookup via magang_id -> 200
        $resMagangId = $this->actingAs($userTRIK)->getJson("/api/laporan/{$magangTRIK->magang_id}");
        $resMagangId->assertStatus(200);
        $this->assertEquals($laporanTRIK->laporan_id, $resMagangId->json('laporan_id'));

        // 2. Lookup via laporan_id -> 200
        $resLaporanId = $this->actingAs($userTRIK)->getJson("/api/laporan/{$laporanTRIK->laporan_id}");
        $resLaporanId->assertStatus(200);
        $this->assertEquals($laporanTRIK->laporan_id, $resLaporanId->json('laporan_id'));
    }
}
