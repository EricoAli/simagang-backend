<?php

namespace Tests\Feature;

use App\Models\DokumenMagang;
use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MitraPeriodAndDokumenTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
    }

    private function period(string $year, int $semester): PeriodeMagang
    {
        $academicYear = TahunAjaran::create([
            'tahun_ajaran' => "{$year}/" . ((int) $year + 1),
            'is_active' => true,
        ]);

        return PeriodeMagang::create([
            'tahun_ajaran_id' => $academicYear->tahun_ajaran_id,
            'semester_magang' => $semester,
            'jenis_semester' => $semester % 2 === 0 ? 'Genap' : 'Gasal',
            'is_active' => true,
        ]);
    }

    private function magang(int $mitraId, int $periodeId, string $nim, ?User $studentUser = null): Magang
    {
        $studentUser ??= User::factory()->create(['role' => 'mahasiswa']);
        $student = Mahasiswa::create([
            'user_id' => $studentUser->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nim' => $nim,
            'nama' => "Mahasiswa {$nim}",
            'angkatan' => 2026,
        ]);

        return Magang::create([
            'mahasiswa_id' => $student->mahasiswa_id,
            'mitra_id' => $mitraId,
            'periode_id' => $periodeId,
            'tahun_ajaran' => 2026,
            'semester_magang' => 6,
            'status_magang' => 'berlangsung',
            'role_magang' => 'Frontend',
        ]);
    }

    public function test_mitra_period_filter_is_scoped_to_own_company(): void
    {
        $mitraUser = User::factory()->create(['role' => 'mitra']);
        $mitra = Mitra::create(['user_id' => $mitraUser->user_id, 'nama_mitra' => 'Mitra A']);
        $other = Mitra::create([
            'user_id' => User::factory()->create(['role' => 'mitra'])->user_id,
            'nama_mitra' => 'Mitra B',
        ]);
        $periodA = $this->period('2026', 6);
        $periodB = $this->period('2027', 6);

        $ownA = $this->magang($mitra->mitra_id, $periodA->periode_id, '26/0001/SV/00001');
        $ownB = $this->magang($mitra->mitra_id, $periodB->periode_id, '26/0002/SV/00002');
        $otherMagang = $this->magang($other->mitra_id, $periodA->periode_id, '26/0003/SV/00003');

        $this->actingAs($mitraUser)->getJson('/api/magang')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissing(['magang_id' => $otherMagang->magang_id]);

        $this->actingAs($mitraUser)->getJson('/api/magang?periode_id=' . $periodA->periode_id)
            ->assertOk()
            ->assertJsonPath('data.0.magang_id', $ownA->magang_id)
            ->assertJsonMissing(['magang_id' => $ownB->magang_id])
            ->assertJsonMissing(['magang_id' => $otherMagang->magang_id]);
    }

    public function test_invalid_period_is_rejected_and_pagination_is_preserved(): void
    {
        $user = User::factory()->create(['role' => 'mitra']);
        $mitra = Mitra::create(['user_id' => $user->user_id, 'nama_mitra' => 'Mitra Pagination']);
        $period = $this->period('2026', 6);
        $this->magang($mitra->mitra_id, $period->periode_id, '26/0004/SV/00004');

        $this->actingAs($user)->getJson('/api/magang?periode_id=999999')->assertStatus(422);
        $this->actingAs($user)->getJson('/api/magang?per_page=1&page=1')
            ->assertOk()
            ->assertJsonPath('per_page', 1)
            ->assertJsonPath('current_page', 1);
    }

    public function test_admin_and_super_admin_can_still_use_period_filter(): void
    {
        $mitra = Mitra::create(['user_id' => User::factory()->create(['role' => 'mitra'])->user_id, 'nama_mitra' => 'Mitra Admin']);
        $period = $this->period('2026', 6);
        $magang = $this->magang($mitra->mitra_id, $period->periode_id, '26/0006/SV/00006');

        $adminUser = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $adminUser->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nama' => 'Admin Periode Test',
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($adminUser)->getJson('/api/magang?periode_id=' . $period->periode_id)
            ->assertOk()
            ->assertJsonPath('data.0.magang_id', $magang->magang_id);
        $this->actingAs($superAdmin)->getJson('/api/magang?periode_id=' . $period->periode_id)
            ->assertOk()
            ->assertJsonPath('data.0.magang_id', $magang->magang_id);
    }

    public function test_document_type_is_validated_and_metadata_is_consistent(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => 'mahasiswa']);
        $mitra = Mitra::create([
            'user_id' => User::factory()->create(['role' => 'mitra'])->user_id,
            'nama_mitra' => 'Mitra Dokumen',
        ]);
        $period = $this->period('2026', 6);
        $magang = $this->magang($mitra->mitra_id, $period->periode_id, '26/0005/SV/00005', $user);

        $this->actingAs($user)->post('/api/dokumen-magang', [
            'magang_id' => $magang->magang_id,
            'jenis_dokumen' => 'jenis_tidak_dikenal',
            'file' => UploadedFile::fake()->create('dokumen.pdf', 20, 'application/pdf'),
        ])->assertStatus(422);

        $response = $this->actingAs($user)->post('/api/dokumen-magang', [
            'magang_id' => $magang->magang_id,
            'jenis_dokumen' => 'doc_surat_penerimaan',
            'file' => UploadedFile::fake()->create('penerimaan.pdf', 20, 'application/pdf'),
        ])->assertCreated()
            ->assertJsonPath('data.jenis_dokumen', 'doc_surat_penerimaan')
            ->assertJsonPath('data.jenis_dokumen_label', 'Surat Penerimaan Magang');

        $document = DokumenMagang::findOrFail($response->json('data.dokumen_id'));
        $this->assertSame('doc_surat_penerimaan', $document->jenis_dokumen);
        Storage::disk('public')->assertExists($document->file_path);

        $otherStudent = User::factory()->create(['role' => 'mahasiswa']);
        $this->actingAs($otherStudent)
            ->getJson('/api/dokumen-magang/' . $document->dokumen_id)
            ->assertForbidden();
    }
}
