<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\DokumenMagang;
use App\Models\Dosbing;
use App\Models\FotoMagang;
use App\Models\JadwalPeserta;
use App\Models\JadwalPresentasi;
use App\Models\Laporan;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MagangDeletionTest extends TestCase
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

    private function createMagang(
        Mahasiswa $mahasiswa,
        string $status = 'draft',
        ?Mitra $mitra = null
    ): Magang {
        return Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'mitra_id' => $mitra?->mitra_id,
            'periode_id' => $this->periode->periode_id,
            'status_magang' => $status,
        ]);
    }

    private function createDokumen(Magang $magang, string $path = 'dokumen/penerimaan.pdf'): DokumenMagang
    {
        return DokumenMagang::create([
            'magang_id' => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'nama_file' => 'penerimaan.pdf',
            'file_path' => $path,
            'ukuran_file' => 1024,
        ]);
    }

    private function createLogbook(Magang $magang): Logbook
    {
        return Logbook::create([
            'magang_id' => $magang->magang_id,
            'kegiatan' => 'Kegiatan uji',
            'tanggal_kegiatan' => '2026-01-05',
        ]);
    }

    private function createLaporan(Magang $magang): Laporan
    {
        return Laporan::create([
            'magang_id' => $magang->magang_id,
            'nama_file' => 'laporan.pdf',
            'file_path' => 'laporan/laporan.pdf',
        ]);
    }

    private function createPenilaian(Magang $magang): NilaiMitra
    {
        return NilaiMitra::create([
            'magang_id' => $magang->magang_id,
            'nilai_teknis' => 80,
            'nilai_profesionalisme_etika' => 80,
            'nilai_komunikasi_presentasi' => 80,
            'nilai_proyek_pengalaman_industri' => 80,
            'supervisor' => 'Supervisor Uji',
            'jabatan_supervisor' => 'Manager',
            'nama_file' => 'nilai.pdf',
            'file_path' => 'penilaian/nilai.pdf',
        ]);
    }

    private function createPesertaJadwal(Magang $magang): JadwalPeserta
    {
        $jadwal = JadwalPresentasi::create([
            'tanggal_presentasi' => '2026-02-10',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '10:00',
            'status' => 'terjadwal',
        ]);

        return JadwalPeserta::create([
            'jadwal_id' => $jadwal->jadwal_id,
            'magang_id' => $magang->magang_id,
            'mahasiswa_id' => $magang->mahasiswa_id,
        ]);
    }

    public function test_admin_prodi_can_delete_own_draft_without_dependents(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1001/SV/00001');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertOk()
            ->assertJsonPath('message', 'Data magang berhasil dihapus');

        $this->assertDatabaseMissing('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_admin_prodi_cannot_delete_draft_from_other_prodi(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrik, '24/1002/SV/00002');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_super_admin_can_delete_draft_across_prodi(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $mahasiswa = $this->createMahasiswa($this->prodiTrik, '24/1003/SV/00003');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($superAdmin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertOk();

        $this->assertDatabaseMissing('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_mahasiswa_can_cancel_own_draft(): void
    {
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1004/SV/00004');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($mahasiswa->user)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertOk();

        $this->assertDatabaseMissing('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_mahasiswa_cannot_cancel_draft_of_another_student(): void
    {
        $owner = $this->createMahasiswa($this->prodiTrpl, '24/1005/SV/00005');
        $other = $this->createMahasiswa($this->prodiTrpl, '24/1006/SV/00006');
        $magang = $this->createMagang($owner);

        $this->actingAs($other->user)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_mitra_cannot_delete_magang(): void
    {
        $mitra = $this->createMitra();
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1007/SV/00007');
        $magang = $this->createMagang($mahasiswa, 'draft', $mitra);

        $this->actingAs($mitra->user)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertForbidden()
            ->assertJsonPath('message', 'Role ini tidak berwenang menghapus data magang.');

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_dosbing_cannot_delete_magang(): void
    {
        $dosbingUser = User::factory()->create(['role' => 'dosbing']);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1008/SV/00008');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($dosbingUser)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertForbidden();

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_berlangsung_magang_is_protected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1009/SV/00009');
        $magang = $this->createMagang($mahasiswa, 'berlangsung');

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonStructure(['message', 'errors' => ['status_magang']]);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'berlangsung',
        ]);
    }

    public function test_selesai_magang_is_protected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1010/SV/00010');
        $magang = $this->createMagang($mahasiswa, 'selesai');

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'selesai',
        ]);
    }

    public function test_ditolak_magang_is_protected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1011/SV/00011');
        $magang = $this->createMagang($mahasiswa, 'ditolak');

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magang->magang_id,
            'status_magang' => 'ditolak',
        ]);
    }

    public function test_draft_with_document_is_rejected_and_file_is_preserved(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('dokumen/penerimaan.pdf', 'isi-dokumen');

        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1012/SV/00012');
        $magang = $this->createMagang($mahasiswa);
        $dokumen = $this->createDokumen($magang);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.relations.0', 'dokumen magang');

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
        $this->assertDatabaseHas('dokumen_magang', ['dokumen_id' => $dokumen->dokumen_id]);
        Storage::disk('public')->assertExists('dokumen/penerimaan.pdf');
    }

    public function test_draft_with_logbook_is_rejected_and_photos_are_preserved(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('foto/kegiatan.jpg', 'isi-foto');

        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1013/SV/00013');
        $magang = $this->createMagang($mahasiswa);
        $logbook = $this->createLogbook($magang);

        $foto = FotoMagang::create([
            'logbook_id' => $logbook->logbook_id,
            'nama_file' => 'kegiatan.jpg',
            'file_path' => 'foto/kegiatan.jpg',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.relations.0', 'logbook/foto kegiatan');

        $this->assertDatabaseHas('logbook', ['logbook_id' => $logbook->logbook_id]);
        $this->assertDatabaseHas('foto_kegiatan', ['foto_id' => $foto->foto_id]);
        Storage::disk('public')->assertExists('foto/kegiatan.jpg');
    }

    public function test_draft_with_laporan_is_rejected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1014/SV/00014');
        $magang = $this->createMagang($mahasiswa);
        $laporan = $this->createLaporan($magang);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.relations.0', 'laporan magang');

        $this->assertDatabaseHas('laporan', ['laporan_id' => $laporan->laporan_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_draft_with_penilaian_mitra_is_rejected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1015/SV/00015');
        $magang = $this->createMagang($mahasiswa);
        $penilaian = $this->createPenilaian($magang);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.relations.0', 'penilaian mitra');

        $this->assertDatabaseHas('penilaian_mitra', ['penilaian_id' => $penilaian->penilaian_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_draft_with_peserta_jadwal_presentasi_is_rejected(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1016/SV/00016');
        $magang = $this->createMagang($mahasiswa);
        $peserta = $this->createPesertaJadwal($magang);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.relations.0', 'peserta jadwal presentasi');

        $this->assertDatabaseHas('jadwal_peserta', ['peserta_id' => $peserta->peserta_id]);
        $this->assertDatabaseHas('jadwal_presentasi', ['jadwal_id' => $peserta->jadwal_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_repeated_deletion_returns_404(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1017/SV/00017');
        $magang = $this->createMagang($mahasiswa);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertOk();

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$magang->magang_id}")
            ->assertNotFound();
    }

    public function test_deleting_one_draft_keeps_other_period_history(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);
        $mahasiswa = $this->createMahasiswa($this->prodiTrpl, '24/1018/SV/00018');

        $draft = $this->createMagang($mahasiswa);

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

        $riwayat = Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => $periodeLama->periode_id,
            'status_magang' => 'selesai',
        ]);

        $this->actingAs($admin)
            ->deleteJson("/api/magang/{$draft->magang_id}")
            ->assertOk();

        $this->assertDatabaseMissing('magang', ['magang_id' => $draft->magang_id]);
        $this->assertDatabaseHas('magang', [
            'magang_id' => $riwayat->magang_id,
            'status_magang' => 'selesai',
        ]);
    }

    public function test_deleting_nonexistent_magang_returns_404(): void
    {
        $admin = $this->createAdmin($this->prodiTrpl);

        $this->actingAs($admin)
            ->deleteJson('/api/magang/999999')
            ->assertNotFound();
    }
}
