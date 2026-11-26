<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DokumenMagang;
use App\Models\Dosbing;
use App\Models\JadwalPresentasi;
use App\Models\Laporan;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Database\Seeders\PeriodeMagangSeeder;
use Database\Seeders\ProdiSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MagangRevisionTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;
    protected Prodi $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([ProdiSeeder::class, PeriodeMagangSeeder::class, SuperAdminSeeder::class]);
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
            'nama' => 'Mahasiswa '.$nim,
            'angkatan' => 2024,
        ]);

        return [$user, $mahasiswa];
    }

    private function createMitra(string $nama = 'PT Uji Revisi'): Mitra
    {
        $user = User::factory()->create(['role' => 'mitra', 'is_active' => true]);

        return Mitra::create([
            'user_id' => $user->user_id,
            'nama_mitra' => $nama,
            'email' => $user->email,
        ]);
    }

    public function test_mahasiswa_can_register_with_dynamic_periode_without_prakrs_and_avoid_tahun_ajaran_collision()
    {
        Storage::fake('public');

        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $mitra = $this->createMitra();
        $tahun = TahunAjaran::where('tahun_ajaran', '2026/2027')->firstOrFail();
        $periode8 = PeriodeMagang::create([
            'tahun_ajaran_id' => $tahun->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $response = $this->actingAs($mhsUser)->post('/api/magang', [
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode8->periode_id,
            'role_magang' => 'Cloud Engineer',
            'periode_bulan' => 6,
            'dokumenPenerimaan' => UploadedFile::fake()->create('penerimaan.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('data.status_magang', 'draft')
            ->assertJsonPath('data.status_label', 'Belum Mulai')
            ->assertJsonPath('data.semester_magang', 8)
            ->assertJsonPath('data.jenis_semester', 'Genap')
            ->assertJsonPath('data.tahun_ajaran', '2026/2027')
            ->assertJsonPath('data.periode_id', $periode8->periode_id);

        // Pastikan tahun_ajaran tetap string (tidak tertimpa object relasi)
        $this->assertIsString($response->json('data.tahun_ajaran'));
        $this->assertEquals('2026/2027', $response->json('data.periode.tahun_ajaran.tahun_ajaran'));
        $this->assertEquals(1, DokumenMagang::where('magang_id', $response->json('data.magang_id'))->count());
    }

    public function test_duplicate_period_is_blocked_unless_previous_magang_was_ditolak()
    {
        Storage::fake('public');

        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $mitra = $this->createMitra();
        $periode6 = PeriodeMagang::where('semester_magang', 6)->firstOrFail();
        $periode7 = PeriodeMagang::where('semester_magang', 7)->firstOrFail();

        $res1 = $this->actingAs($mhsUser)->post('/api/magang', [
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'dokumenPenerimaan' => UploadedFile::fake()->create('penerimaan1.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $res1->assertStatus(201);

        // Duplikat periode yang sama harus ditolak 422 dengan message spesifik
        $resDup = $this->actingAs($mhsUser)->post('/api/magang', [
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'dokumenPenerimaan' => UploadedFile::fake()->create('penerimaan2.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $resDup->assertStatus(422);
        $this->assertNotEquals('Validasi gagal', $resDup->json('message'));

        // Boleh mendaftar periode berbeda (riwayat multi-periode)
        $resDiff = $this->actingAs($mhsUser)->post('/api/magang', [
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode7->periode_id,
            'dokumenPenerimaan' => UploadedFile::fake()->create('penerimaan3.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $resDiff->assertStatus(201);

        // Jika periode 6 ditandai 'ditolak' oleh Admin, mahasiswa boleh mendaftar ulang di periode 6
        $this->actingAs($admin)
            ->putJson("/api/magang/{$res1->json('data.magang_id')}", ['status_magang' => 'ditolak'])
            ->assertStatus(200);

        $resRetry = $this->actingAs($mhsUser)->post('/api/magang', [
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'dokumenPenerimaan' => UploadedFile::fake()->create('penerimaan4.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json']);
        $resRetry->assertStatus(201);
    }

    public function test_mahasiswa_cannot_change_status_magang_and_admin_can_create_without_files()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $mitra = $this->createMitra();
        $periode6 = PeriodeMagang::where('semester_magang', 6)->firstOrFail();

        // Admin membuat data penempatan magang tanpa dokumen PDF (dipakai FE Master Data)
        $createRes = $this->actingAs($admin)->postJson('/api/magang', [
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'status_magang' => 'draft',
        ]);
        $createRes->assertStatus(201)
            ->assertJsonPath('data.status_magang', 'draft');

        $magangId = $createRes->json('data.magang_id');

        // Mahasiswa dilarang mengubah status magang -> 403
        $this->actingAs($mhsUser)->putJson("/api/magang/{$magangId}", [
            'status_magang' => 'berlangsung',
        ])->assertStatus(403);

        $this->actingAs($mhsUser)->putJson("/api/magang/{$magangId}/status", [
            'status_magang' => 'selesai',
        ])->assertStatus(403);

        // Normalisasi alias status bahasa UI & enum baru
        $this->actingAs($admin)->putJson("/api/magang/{$magangId}", [
            'status_magang' => 'sedang_magang',
        ])->assertStatus(200)
            ->assertJsonPath('data.status_magang', 'berlangsung')
            ->assertJsonPath('data.status_label', 'Sedang Magang');

        // Mahasiswa tidak dapat mengganti mitra/periode saat status sudah berlangsung
        $mitraLain = $this->createMitra('PT Mitra Baru');
        $this->actingAs($mhsUser)->putJson("/api/magang/{$magangId}", [
            'mitra_id' => $mitraLain->mitra_id,
        ])->assertStatus(422);
    }

    public function test_tiered_delete_protection_for_mahasiswa_admin_and_super_admin()
    {
        $superAdmin = $this->superAdmin();
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$mhsUser, $mahasiswa] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $mitra = $this->createMitra();
        $periode6 = PeriodeMagang::where('semester_magang', 6)->firstOrFail();
        $periode7 = PeriodeMagang::where('semester_magang', 7)->firstOrFail();

        $draftMagang = Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'status_magang' => 'draft',
        ]);

        $activeMagang = Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode7->periode_id,
            'status_magang' => 'berlangsung',
        ]);

        // Mahasiswa & Admin dilarang menghapus magang berlangsung -> 422
        $this->actingAs($mhsUser)->deleteJson("/api/magang/{$activeMagang->magang_id}")->assertStatus(422);
        $this->actingAs($admin)->deleteJson("/api/magang/{$activeMagang->magang_id}")->assertStatus(422);

        // can_delete = false untuk Admin/Mhs pada berlangsung, true pada Super Admin
        $this->actingAs($admin)->getJson("/api/magang/{$activeMagang->magang_id}")
            ->assertJsonPath('can_delete', false);
        $this->actingAs($superAdmin)->getJson("/api/magang/{$activeMagang->magang_id}")
            ->assertJsonPath('can_delete', true);

        // Mahasiswa dapat menghapus draft tanpa dependensi -> 200
        $this->actingAs($mhsUser)->deleteJson("/api/magang/{$draftMagang->magang_id}")->assertStatus(200);

        // Super Admin tetap dapat menghapus data berlangsung (koreksi darurat) -> 200
        $this->actingAs($superAdmin)->deleteJson("/api/magang/{$activeMagang->magang_id}")->assertStatus(200);
    }

    public function test_magang_index_filters_by_dosbing_periode_status_and_show_detail_includes_all_doc_types()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$u1, $mhs1] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/11111/SV/11111');
        [$u2, $mhs2] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/22222/SV/22222');
        $mitra = $this->createMitra('PT Filter Hebat');
        $dosbing1 = Dosbing::create(['prodi_id' => $this->prodiTRPL->prodi_id, 'nip' => '198001012005011001', 'nama' => 'Dosen Satu']);
        $dosbing2 = Dosbing::create(['prodi_id' => $this->prodiTRPL->prodi_id, 'nip' => '198001012005011002', 'nama' => 'Dosen Dua']);

        $periode6 = PeriodeMagang::where('semester_magang', 6)->firstOrFail();

        $m1 = Magang::create([
            'mahasiswa_id' => $mhs1->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'dosbing_id' => $dosbing1->dosbing_id,
            'periode_id' => $periode6->periode_id,
            'status_magang' => 'berlangsung',
        ]);

        Magang::create([
            'mahasiswa_id' => $mhs2->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'dosbing_id' => $dosbing2->dosbing_id,
            'periode_id' => $periode6->periode_id,
            'status_magang' => 'draft',
        ]);

        // Filter dosbing_id + status alias + search mahasiswa NIM
        $resFilter = $this->actingAs($admin)
            ->getJson("/api/magang?dosbing_id={$dosbing1->dosbing_id}&status_magang=sedang_magang&q=11111");
        $resFilter->assertStatus(200);
        $this->assertCount(1, $resFilter->json('data'));
        $this->assertEquals($m1->magang_id, $resFilter->json('data.0.magang_id'));

        // showDetail harus mengembalikan seluruh tipe dokumen (termasuk tipe baru dari BE 2)
        DokumenMagang::create([
            'magang_id' => $m1->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'nama_file' => 'penerimaan.pdf',
            'file_path' => 'dokumen/penerimaan.pdf',
        ]);
        DokumenMagang::create([
            'magang_id' => $m1->magang_id,
            'jenis_dokumen' => 'doc_laporan_magang',
            'nama_file' => 'laporan_lampiran.pdf',
            'file_path' => 'dokumen/laporan_lampiran.pdf',
        ]);

        $detailRes = $this->actingAs($admin)->getJson("/api/magang/{$m1->magang_id}/detail");
        $detailRes->assertStatus(200);
        $this->assertCount(2, $detailRes->json('dokumen'));
    }

    public function test_dosbing_supports_prodi_scoping_and_admin_authorization()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $adminTRIK = $this->createAdmin($this->prodiTRIK->prodi_id);
        [$mhsUser] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/77777/SV/77777');

        // Mahasiswa tidak dapat membuat dosbing -> 403
        $this->actingAs($mhsUser)->postJson('/api/dosbing', [
            'nip' => '198501012010011001',
            'nama' => 'Dosen Terlarang',
        ])->assertStatus(403);

        // Admin TRPL membuat dosbing -> otomatis prodi_id TRPL
        $resCreate = $this->actingAs($adminTRPL)->postJson('/api/dosbing', [
            'nip' => '198501012010011001',
            'nama' => 'Dosen TRPL',
        ]);
        $resCreate->assertStatus(201)
            ->assertJsonPath('data.prodi_id', $this->prodiTRPL->prodi_id);

        $dosbingId = $resCreate->json('data.dosbing_id');

        // Admin TRIK ditolak ubah/hapus dosbing milik TRPL -> 403
        $this->actingAs($adminTRIK)->putJson("/api/dosbing/{$dosbingId}", [
            'nip' => '198501012010011001',
            'nama' => 'Diubah TRIK',
        ])->assertStatus(403);

        // getDosbingByMagangId dengan ID tidak ada -> 404 (tidak crash null pointer)
        $this->actingAs($adminTRPL)->getJson('/api/dosbing/magang/999999')->assertStatus(404);
    }

    public function test_nilai_mitra_and_mitra_dashboard_support_periode_filters_and_search()
    {
        [$mhsUser1, $mhs1] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/88881/SV/88881');
        [$mhsUser2, $mhs2] = $this->createMahasiswa($this->prodiTRPL->prodi_id, '24/88882/SV/88882');

        $mitraUser = User::factory()->create(['role' => 'mitra', 'is_active' => true]);
        $mitra = Mitra::create([
            'user_id' => $mitraUser->user_id,
            'nama_mitra' => 'PT Penilaian Uji',
            'email' => $mitraUser->email,
        ]);

        $periode6 = PeriodeMagang::where('semester_magang', 6)->firstOrFail();
        $periode7 = PeriodeMagang::where('semester_magang', 7)->firstOrFail();

        $magang6 = Magang::create([
            'mahasiswa_id' => $mhs1->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode6->periode_id,
            'status_magang' => 'selesai',
        ]);
        $magang7 = Magang::create([
            'mahasiswa_id' => $mhs2->mahasiswa_id,
            'mitra_id' => $mitra->mitra_id,
            'periode_id' => $periode7->periode_id,
            'status_magang' => 'berlangsung',
        ]);

        NilaiMitra::create([
            'magang_id' => $magang6->magang_id,
            'nilai_teknis' => 90,
            'nilai_profesionalisme_etika' => 90,
            'nilai_komunikasi_presentasi' => 90,
            'nilai_proyek_pengalaman_industri' => 90,
            'keterangan' => 'Bagus sekali',
            'supervisor' => 'Pak Lead',
            'jabatan_supervisor' => 'CTO',
            'nama_file' => 'nilai6.pdf',
            'file_path' => 'nilai/nilai6.pdf',
        ]);

        // Dashboard Mitra filter periode6 -> 1 penilaian, 1 magang
        $this->actingAs($mitraUser)->getJson("/api/dashboard/mitra?periode_id={$periode6->periode_id}")
            ->assertStatus(200)
            ->assertJsonPath('jumlah_penilaian', 1)
            ->assertJsonPath('jumlah_magang', 1);

        // Nilai Mitra filter periode_id & q=NIM mahasiswa
        $this->actingAs($mitraUser)->getJson("/api/penilaian-mitra?periode_id={$periode6->periode_id}&q=88881")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actingAs($mitraUser)->getJson("/api/penilaian-mitra?periode_id={$periode7->periode_id}")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
