<?php

namespace Tests\Feature;

use App\Models\DokumenMagang;
use App\Models\Laporan;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporanDokumenStorageTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        Storage::fake('public');
    }

    private function createMahasiswa(): array
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        $mahasiswa = Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $this->prodiTRPL->prodi_id,
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
            'nama_mitra' => 'PT Mitra Test',
            'email'      => 'mitra' . rand(100, 999) . '@test.id',
            'narahubung' => 'Bapak Rudi',
        ]);
        return [$user, $mitra];
    }

    private function createMagang(int $mahasiswaId, int $mitraId): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'mitra_id'        => $mitraId,
            'semester_magang' => 6,
            'role_magang'     => 'Quality Assurance',
            'jobdesk'         => 'Testing API',
            'periode_bulan'   => 5,
            'status_magang'   => 'berlangsung',
        ]);
    }

    public function test_dokumen_magang_upload_and_update_supports_put_and_post_on_public_disk()
    {
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();
        [$mitraUser, $mitra] = $this->createMitra();
        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id);

        $fakePdf = UploadedFile::fake()->create('surat_penerimaan.pdf', 300, 'application/pdf');

        // 1. Upload dokumen awal
        $resStore = $this->actingAs($mhsUser)->postJson('/api/dokumen-magang', [
            'magang_id'     => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'file'          => $fakePdf,
        ]);
        $resStore->assertStatus(201);
        $dokumenId = $resStore->json('data.dokumen_id');

        $dokumen = DokumenMagang::find($dokumenId);
        $this->assertNotNull($dokumen);
        Storage::disk('public')->assertExists($dokumen->file_path);

        // 2. Update via PUT (JSON/metadata)
        $resPut = $this->actingAs($mhsUser)->putJson("/api/dokumen-magang/{$dokumenId}", [
            'jenis_dokumen' => 'doc_pra_krs',
        ]);
        $resPut->assertStatus(200);
        $this->assertEquals('doc_pra_krs', $dokumen->fresh()->jenis_dokumen);

        // 3. Update via POST (FormData dengan file baru)
        $newPdf = UploadedFile::fake()->create('pra_krs_rev.pdf', 400, 'application/pdf');
        $resPostUpdate = $this->actingAs($mhsUser)->post("/api/dokumen-magang/{$dokumenId}", [
            'jenis_dokumen' => 'doc_pra_krs',
            'file'          => $newPdf,
        ]);
        $resPostUpdate->assertStatus(200);

        $updatedDokumen = $dokumen->fresh();
        Storage::disk('public')->assertExists($updatedDokumen->file_path);
    }

    public function test_laporan_upload_and_update_supports_put_and_post_on_public_disk()
    {
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();
        [$mitraUser, $mitra] = $this->createMitra();
        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id);

        $laporanPdf = UploadedFile::fake()->create('laporan_akhir.pdf', 500, 'application/pdf');

        // 1. Store laporan
        $resStore = $this->actingAs($mhsUser)->postJson('/api/laporan', [
            'magang_id' => $magang->magang_id,
            'file'      => $laporanPdf,
        ]);
        $resStore->assertStatus(201);
        $laporanId = $resStore->json('data.laporan_id');

        $laporan = Laporan::find($laporanId);
        $this->assertNotNull($laporan);
        Storage::disk('public')->assertExists($laporan->file_path);

        // 2. Update via POST multipart (FormData pattern dari Frontend)
        $revisedPdf = UploadedFile::fake()->create('laporan_revisi_final.pdf', 600, 'application/pdf');
        $resUpdate = $this->actingAs($mhsUser)->post("/api/laporan/{$laporanId}", [
            'file' => $revisedPdf,
        ]);
        $resUpdate->assertStatus(200);

        $updatedLaporan = $laporan->fresh();
        Storage::disk('public')->assertExists($updatedLaporan->file_path);
        $this->assertStringStartsWith('laporan_revisi_final', $updatedLaporan->nama_file);
    }

    public function test_dokumen_magang_rejects_disallowed_file_types_with_422()
    {
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();
        [$mitraUser, $mitra] = $this->createMitra();
        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id);

        $badFile = UploadedFile::fake()->create('payload.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($mhsUser)->postJson('/api/dokumen-magang', [
            'magang_id'     => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'file'          => $badFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    public function test_dokumen_and_laporan_download_serves_files()
    {
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();
        [$mitraUser, $mitra] = $this->createMitra();
        $magang = $this->createMagang($mahasiswa->mahasiswa_id, $mitra->mitra_id);

        $filePath = 'dokumen_magang/sample_test.pdf';
        Storage::disk('public')->put($filePath, 'PDF content test');

        $dokumen = DokumenMagang::create([
            'magang_id'     => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'nama_file'     => 'sample_test.pdf',
            'file_path'     => $filePath,
        ]);

        $resDownload = $this->actingAs($mhsUser)->get("/api/dokumen-magang/{$dokumen->dokumen_id}/download");
        $resDownload->assertStatus(200);

        // Download laporan
        $lapPath = 'laporan-magang/laporan_sample.pdf';
        Storage::disk('public')->put($lapPath, 'Laporan PDF content');

        $laporan = Laporan::create([
            'magang_id' => $magang->magang_id,
            'nama_file' => 'laporan_sample.pdf',
            'file_path' => $lapPath,
        ]);

        $resLapDownload = $this->actingAs($mhsUser)->get("/api/laporan/{$laporan->laporan_id}/download");
        $resLapDownload->assertStatus(200);
    }

    public function test_unauthorized_student_cannot_modify_other_students_dokumen()
    {
        [$mhsUserA, $mhsA] = $this->createMahasiswa();
        [$mhsUserB, $mhsB] = $this->createMahasiswa();
        [$mitraUser, $mitra] = $this->createMitra();

        $magangA = $this->createMagang($mhsA->mahasiswa_id, $mitra->mitra_id);

        $dokumenA = DokumenMagang::create([
            'magang_id'     => $magangA->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'nama_file'     => 'doc_a.pdf',
            'file_path'     => 'dokumen_magang/doc_a.pdf',
        ]);

        // Mahasiswa B mencoba update dokumen milik Mahasiswa A
        $response = $this->actingAs($mhsUserB)->putJson("/api/dokumen-magang/{$dokumenA->dokumen_id}", [
            'jenis_dokumen' => 'doc_pra_krs',
        ]);

        $response->assertStatus(403);
    }
}
