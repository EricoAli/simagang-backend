<?php

namespace Tests\Feature;

use App\Models\DokumenMagang;
use App\Models\Admin;
use App\Models\FotoMagang;
use App\Models\Logbook;
use App\Models\Laporan;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\MagangExportArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class MagangZipExportTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Magang $magang;
    private PeriodeMagang $period;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $this->student = User::factory()->create(['role' => 'mahasiswa']);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->student->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => '26/ZIP/SV/00001',
            'nama' => 'Mahasiswa Export ZIP',
            'angkatan' => 2026,
        ]);
        $year = TahunAjaran::create(['tahun_ajaran' => '2026/2027', 'is_active' => true]);
        $this->period = PeriodeMagang::create([
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);
        $this->magang = Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'periode_id' => $this->period->periode_id,
            'status_magang' => 'berlangsung',
            'role_magang' => 'Backend',
            'jobdesk' => 'Menguji export',
        ]);
    }

    public function test_zip_contains_four_sheets_rows_and_all_attachment_sources(): void
    {
        $logbookOne = Logbook::create([
            'magang_id' => $this->magang->magang_id,
            'tanggal_kegiatan' => '2026-02-01',
            'kegiatan' => 'Kegiatan pertama',
        ]);
        $logbookTwo = Logbook::create([
            'magang_id' => $this->magang->magang_id,
            'tanggal_kegiatan' => '2026-02-02',
            'kegiatan' => 'Kegiatan kedua',
        ]);

        foreach ([$logbookOne, $logbookTwo] as $logbook) {
            $path = "foto_kegiatan/{$this->magang->magang_id}/foto-sama.jpg";
            Storage::disk('public')->put($path, 'image');
            FotoMagang::create([
                'logbook_id' => $logbook->logbook_id,
                'nama_file' => 'foto-sama.jpg',
                'file_path' => $path,
            ]);
        }

        foreach (['doc_surat_penerimaan', 'doc_pra_krs'] as $type) {
            $path = "dokumen_magang/{$type}.pdf";
            Storage::disk('public')->put($path, 'pdf');
            DokumenMagang::create([
                'magang_id' => $this->magang->magang_id,
                'jenis_dokumen' => $type,
                'nama_file' => $type . '.pdf',
                'file_path' => $path,
                'ukuran_file' => 3,
            ]);
        }
        $laporanPath = 'laporan-magang/1/laporan.pdf';
        Storage::disk('public')->put($laporanPath, 'laporan');
        Laporan::create([
            'magang_id' => $this->magang->magang_id,
            'nama_file' => 'laporan.pdf',
            'file_path' => $laporanPath,
        ]);
        $nilaiPath = 'dokumen-penilaian/1/nilai.pdf';
        Storage::disk('public')->put($nilaiPath, 'nilai');
        NilaiMitra::create([
            'magang_id' => $this->magang->magang_id,
            'nilai_teknis' => 80,
            'nilai_profesionalisme_etika' => 80,
            'nilai_komunikasi_presentasi' => 80,
            'nilai_proyek_pengalaman_industri' => 80,
            'supervisor' => 'Supervisor Test',
            'jabatan_supervisor' => 'Manager',
            'nama_file' => 'nilai.pdf',
            'file_path' => $nilaiPath,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get('/api/magang/export/zip?periode_id=' . $this->period->periode_id)
            ->assertOk();
        $this->assertStringContainsString('application/zip', (string) $response->headers->get('content-type'));

        $archive = app(MagangExportArchiveService::class)->create([
            'periode_id' => $this->period->periode_id,
        ]);
        $zipPath = $archive['path'];
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }
        $this->assertContains('Data_Magang.xlsx', $entries);
        $this->assertCount(2, array_filter($entries, fn ($entry) => str_contains($entry, 'Foto_Logbook/')));
        $this->assertCount(4, array_filter($entries, fn ($entry) => str_contains($entry, 'Dokumen_Magang/')));

        $extractPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simagang-extract-' . uniqid();
        mkdir($extractPath);
        $zip->extractTo($extractPath);
        $zip->close();
        $rows = IOFactory::load($extractPath . DIRECTORY_SEPARATOR . 'Data_Magang.xlsx')->getSheetNames();
        $this->assertSame(['Data Magang', 'Logbook', 'Dokumen', 'Foto Logbook'], $rows);
        $logbookRows = IOFactory::load($extractPath . DIRECTORY_SEPARATOR . 'Data_Magang.xlsx')
            ->getSheetByName('Logbook')->toArray();
        $this->assertCount(3, $logbookRows);
        $this->assertSame((string) $this->magang->magang_id, (string) $logbookRows[1][1]);

        @unlink($zipPath);
        $this->deleteDirectory($extractPath);
    }

    public function test_missing_attachment_is_reported_without_failing_export_and_excel_remains_compatible(): void
    {
        DokumenMagang::create([
            'magang_id' => $this->magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'nama_file' => 'hilang.pdf',
            'file_path' => 'dokumen_magang/file-tidak-ada.pdf',
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $zipResponse = $this->actingAs($superAdmin)->get('/api/magang/export/zip')->assertOk();
        $this->assertStringContainsString('application/zip', (string) $zipResponse->headers->get('content-type'));

        $archive = app(MagangExportArchiveService::class)->create([]);
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($archive['path']) === true);
        $this->assertNotFalse($zip->locateName('missing_files.txt'));
        $this->assertStringContainsString('file-tidak-ada.pdf', $zip->getFromName('missing_files.txt'));
        $zip->close();
        @unlink($archive['path']);

        $excelResponse = $this->actingAs($superAdmin)->get('/api/magang/export/excel')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $excelResponse->headers->get('content-type'));
    }

    public function test_mitra_export_zip_is_scoped_to_owned_magang(): void
    {
        $mitraUser = User::factory()->create(['role' => 'mitra']);
        $mitra = Mitra::create(['user_id' => $mitraUser->user_id, 'nama_mitra' => 'Mitra ZIP']);
        $this->magang->update(['mitra_id' => $mitra->mitra_id]);
        $otherMitra = Mitra::create(['user_id' => User::factory()->create(['role' => 'mitra'])->user_id, 'nama_mitra' => 'Mitra Lain']);
        $other = $this->magang->replicate();
        $other->mahasiswa_id = Mahasiswa::create([
            'user_id' => User::factory()->create(['role' => 'mahasiswa'])->user_id,
            'prodi_id' => $this->magang->mahasiswa->prodi_id,
            'nim' => '26/ZIP/SV/00002',
            'nama' => 'Mahasiswa Lain',
            'angkatan' => 2026,
        ])->mahasiswa_id;
        $other->mitra_id = $otherMitra->mitra_id;
        $other->save();

        $response = $this->actingAs($mitraUser)->get('/api/magang/export/zip')->assertOk();
        $this->assertStringNotContainsString((string) $other->magang_id, $response->getContent());
    }

    public function test_admin_and_student_are_allowed_only_through_their_existing_export_scope(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $admin->user_id,
            'prodi_id' => $this->magang->mahasiswa->prodi_id,
            'nama' => 'Admin ZIP Test',
        ]);

        $this->actingAs($admin)->get('/api/magang/export/zip')
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs($this->student)->get('/api/magang/export/zip')
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->actingAs(User::factory()->create(['role' => 'dosbing']))
            ->getJson('/api/magang/export/zip')
            ->assertForbidden();
    }

    private function deleteDirectory(string $directory): void
    {
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            is_dir($file) ? $this->deleteDirectory($file) : @unlink($file);
        }
        @rmdir($directory);
    }
}
