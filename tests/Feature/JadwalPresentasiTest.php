<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPresentasiTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiTRPL;
    protected $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ProdiSeeder::class, SuperAdminSeeder::class]);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function getSuperAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
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

    // 1. Admin Prodi dapat membuat jadwal presentasi untuk mahasiswa prodinya
    public function test_admin_prodi_can_create_schedule_for_own_prodi_student()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userTRPL, $mhsTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magangTRPL = $this->createMagang($mhsTRPL->mahasiswa_id);

        $response = $this->actingAs($adminTRPL)->postJson('/api/jadwal-presentasi', [
            'magang_id'          => $magangTRPL->magang_id,
            'tanggal_presentasi' => '2026-11-15',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Lab Software 1',
            'tempat'             => 'Gedung TILC',
            'keterangan'         => 'Presentasi Akhir PI',
            'status'             => 'terjadwal',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('jadwal_presentasi', [
            'magang_id'          => $magangTRPL->magang_id,
            'tanggal_presentasi' => '2026-11-15',
            'ruangan'            => 'Lab Software 1',
        ]);
    }

    // 2. Admin Prodi ditolak saat membuat jadwal untuk mahasiswa prodi lain
    public function test_admin_prodi_cannot_create_schedule_for_other_prodi_student()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        $response = $this->actingAs($adminTRPL)->postJson('/api/jadwal-presentasi', [
            'magang_id'          => $magangTRIK->magang_id,
            'tanggal_presentasi' => '2026-11-15',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Lab TRIK 2',
        ]);

        $response->assertStatus(403);
    }

    // 3. Validasi magang_id yang tidak ada di database
    public function test_create_schedule_fails_with_non_existent_magang_id()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        $response = $this->actingAs($adminTRPL)->postJson('/api/jadwal-presentasi', [
            'magang_id'          => 999999, // Tidak ada
            'tanggal_presentasi' => '2026-11-15',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Lab 101',
        ]);

        $response->assertStatus(422);
    }

    // 4. Admin Prodi tidak dapat melihat, mengubah, atau menghapus jadwal prodi lain
    public function test_admin_prodi_cannot_view_or_modify_other_prodi_schedule()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        $jadwalTRIK = JadwalPresentasi::create([
            'magang_id'          => $magangTRIK->magang_id,
            'tanggal_presentasi' => '2026-11-20',
            'waktu_mulai'        => '13:00',
            'waktu_selesai'      => '14:00',
            'ruangan'            => 'Lab TRIK',
        ]);

        // 1. Show -> 403
        $resShow = $this->actingAs($adminTRPL)->getJson("/api/jadwal-presentasi/{$jadwalTRIK->jadwal_id}");
        $resShow->assertStatus(403);

        // 2. Update -> 403
        $resUpdate = $this->actingAs($adminTRPL)->putJson("/api/jadwal-presentasi/{$jadwalTRIK->jadwal_id}", [
            'ruangan' => 'Hacked Room',
        ]);
        $resUpdate->assertStatus(403);

        // 3. Delete -> 403
        $resDelete = $this->actingAs($adminTRPL)->deleteJson("/api/jadwal-presentasi/{$jadwalTRIK->jadwal_id}");
        $resDelete->assertStatus(403);
    }

    // 5. Super Admin dapat mengakses dan mengelola jadwal seluruh prodi
    public function test_super_admin_can_access_and_manage_schedules_across_prodi()
    {
        $superAdmin = $this->getSuperAdmin();
        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        // 1. Create via Super Admin -> 201
        $resCreate = $this->actingAs($superAdmin)->postJson('/api/jadwal-presentasi', [
            'magang_id'          => $magangTRIK->magang_id,
            'tanggal_presentasi' => '2026-11-22',
            'waktu_mulai'        => '08:00',
            'waktu_selesai'      => '09:00',
            'ruangan'            => 'Auditorium',
        ]);
        $resCreate->assertStatus(201);
        $jadwalId = $resCreate->json('data.jadwal_id');

        // 2. Show via Super Admin -> 200
        $resShow = $this->actingAs($superAdmin)->getJson("/api/jadwal-presentasi/{$jadwalId}");
        $resShow->assertStatus(200);

        // 3. Update via Super Admin -> 200
        $resUpdate = $this->actingAs($superAdmin)->putJson("/api/jadwal-presentasi/{$jadwalId}", [
            'ruangan' => 'Ruang Sidang Baru',
        ]);
        $resUpdate->assertStatus(200);

        // 4. Delete via Super Admin -> 200
        $resDelete = $this->actingAs($superAdmin)->deleteJson("/api/jadwal-presentasi/{$jadwalId}");
        $resDelete->assertStatus(200);
    }

    // 6. Mahasiswa hanya dapat melihat jadwal miliknya sendiri
    public function test_mahasiswa_can_view_own_schedule_but_cannot_view_other_student_schedule()
    {
        [$userA, $mhsA] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$userB, $mhsB] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $magangA = $this->createMagang($mhsA->mahasiswa_id);
        $magangB = $this->createMagang($mhsB->mahasiswa_id);

        $jadwalA = JadwalPresentasi::create([
            'magang_id'          => $magangA->magang_id,
            'tanggal_presentasi' => '2026-11-25',
            'waktu_mulai'        => '10:00',
            'waktu_selesai'      => '11:00',
            'ruangan'            => 'Ruang A',
        ]);

        $jadwalB = JadwalPresentasi::create([
            'magang_id'          => $magangB->magang_id,
            'tanggal_presentasi' => '2026-11-26',
            'waktu_mulai'        => '10:00',
            'waktu_selesai'      => '11:00',
            'ruangan'            => 'Ruang B',
        ]);

        // Mahasiswa A melihat jadwal miliknya -> 200
        $resOwn = $this->actingAs($userA)->getJson("/api/jadwal-presentasi/{$jadwalA->jadwal_id}");
        $resOwn->assertStatus(200);

        // Mahasiswa A melihat jadwal Mahasiswa B -> 403
        $resOther = $this->actingAs($userA)->getJson("/api/jadwal-presentasi/{$jadwalB->jadwal_id}");
        $resOther->assertStatus(403);

        // Mahasiswa A tidak dapat mengubah jadwal -> 403
        $resEdit = $this->actingAs($userA)->putJson("/api/jadwal-presentasi/{$jadwalA->jadwal_id}", [
            'ruangan' => 'Hacked Room',
        ]);
        $resEdit->assertStatus(403);
    }

    // 7. Role dosbing dan mitra ditolak secara eksplisit
    public function test_dosbing_and_mitra_explicitly_rejected_from_schedules()
    {
        $dosbing = User::factory()->create(['role' => 'dosbing', 'is_active' => true]);
        $mitra   = User::factory()->create(['role' => 'mitra', 'is_active' => true]);

        // Dosbing index & show -> 403
        $resDosbingIndex = $this->actingAs($dosbing)->getJson('/api/jadwal-presentasi');
        $resDosbingIndex->assertStatus(403);

        $resDosbingShow = $this->actingAs($dosbing)->getJson('/api/jadwal-presentasi/1');
        $resDosbingShow->assertStatus(403);

        // Mitra index & show -> 403
        $resMitraIndex = $this->actingAs($mitra)->getJson('/api/jadwal-presentasi');
        $resMitraIndex->assertStatus(403);

        $resMitraShow = $this->actingAs($mitra)->getJson('/api/jadwal-presentasi/1');
        $resMitraShow->assertStatus(403);
    }

    // 8. Jadwal historis (magang_id = NULL) dipertahankan dan hanya dapat dikelola oleh Super Admin
    public function test_historical_schedule_with_null_magang_id_is_accessible_only_to_super_admin()
    {
        $adminTRPL  = $this->createAdmin($this->prodiTRPL->prodi_id);
        $superAdmin = $this->getSuperAdmin();

        // Jadwal historis tanpa magang_id
        $jadwalHistoris = JadwalPresentasi::create([
            'magang_id'          => null,
            'tanggal_presentasi' => '2025-10-10',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Aula Lama',
            'keterangan'         => 'Jadwal arsip tahun 2025',
        ]);

        // 1. Admin TRPL memanggil index -> jadwal historis tidak muncul
        $resIndexAdmin = $this->actingAs($adminTRPL)->getJson('/api/jadwal-presentasi');
        $resIndexAdmin->assertStatus(200);
        $jadwalIdsAdmin = collect($resIndexAdmin->json('data'))->pluck('jadwal_id');
        $this->assertNotContains($jadwalHistoris->jadwal_id, $jadwalIdsAdmin);

        // 2. Admin TRPL mencoba akses show, update, delete -> 403
        $resShowAdmin = $this->actingAs($adminTRPL)->getJson("/api/jadwal-presentasi/{$jadwalHistoris->jadwal_id}");
        $resShowAdmin->assertStatus(403);

        $resUpdateAdmin = $this->actingAs($adminTRPL)->putJson("/api/jadwal-presentasi/{$jadwalHistoris->jadwal_id}", [
            'ruangan' => 'Aula Diubah',
        ]);
        $resUpdateAdmin->assertStatus(403);

        $resDeleteAdmin = $this->actingAs($adminTRPL)->deleteJson("/api/jadwal-presentasi/{$jadwalHistoris->jadwal_id}");
        $resDeleteAdmin->assertStatus(403);

        // 3. Super Admin memanggil index -> jadwal historis muncul
        $resIndexSuper = $this->actingAs($superAdmin)->getJson('/api/jadwal-presentasi');
        $resIndexSuper->assertStatus(200);
        $jadwalIdsSuper = collect($resIndexSuper->json('data'))->pluck('jadwal_id');
        $this->assertContains($jadwalHistoris->jadwal_id, $jadwalIdsSuper);

        // 4. Super Admin dapat melihat dan mengelola jadwal historis
        $resShowSuper = $this->actingAs($superAdmin)->getJson("/api/jadwal-presentasi/{$jadwalHistoris->jadwal_id}");
        $resShowSuper->assertStatus(200);

        $resUpdateSuper = $this->actingAs($superAdmin)->putJson("/api/jadwal-presentasi/{$jadwalHistoris->jadwal_id}", [
            'ruangan' => 'Aula Terverifikasi',
        ]);
        $resUpdateSuper->assertStatus(200);
    }

    // 9. Perubahan magang_id ke mahasiswa prodi lain ditolak untuk Admin Prodi
    public function test_updating_magang_id_to_other_prodi_is_rejected_for_admin_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        [$userTRPL, $mhsTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magangTRPL = $this->createMagang($mhsTRPL->mahasiswa_id);

        [$userTRIK, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        $magangTRIK = $this->createMagang($mhsTRIK->mahasiswa_id);

        $jadwal = JadwalPresentasi::create([
            'magang_id'          => $magangTRPL->magang_id,
            'tanggal_presentasi' => '2026-11-28',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Ruang TRPL',
        ]);

        // Admin TRPL mencoba mengalihkan magang_id ke Magang milik mahasiswa TRIK -> 403
        $response = $this->actingAs($adminTRPL)->putJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}", [
            'magang_id' => $magangTRIK->magang_id,
        ]);

        $response->assertStatus(403);
    }

    // 10. Penghapusan data magang tidak menghapus jadwal presentasi (ON DELETE SET NULL)
    public function test_deleting_magang_sets_magang_id_to_null_and_preserves_schedule()
    {
        [$userTRPL, $mhsTRPL] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magangTRPL = $this->createMagang($mhsTRPL->mahasiswa_id);

        $jadwal = JadwalPresentasi::create([
            'magang_id'          => $magangTRPL->magang_id,
            'tanggal_presentasi' => '2026-11-30',
            'waktu_mulai'        => '09:00',
            'waktu_selesai'      => '10:00',
            'ruangan'            => 'Ruang Sidang 1',
        ]);

        $this->assertDatabaseHas('jadwal_presentasi', [
            'jadwal_id' => $jadwal->jadwal_id,
            'magang_id' => $magangTRPL->magang_id,
        ]);

        // Hapus data magang
        $magangTRPL->delete();

        // Jadwal presentasi tetap ada di database, tetapi magang_id berubah menjadi NULL
        $this->assertDatabaseHas('jadwal_presentasi', [
            'jadwal_id' => $jadwal->jadwal_id,
            'magang_id' => null,
        ]);
    }
}
