<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Hardening Mitra & Penilaian:
 * - file penilaian hanya PDF (maks 5 MB) dan alur ganti file yang aman
 * - otorisasi GET /api/mitra/magang/{id}
 * - penolakan hapus Mitra yang masih punya relasi magang (409)
 */
class MitraPenilaianSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected $prodiTRPL;
    protected $prodiTRIK;

    protected $userMitraA;
    protected $mitraA;
    protected $userMitraB;
    protected $mitraB;
    protected $userMhsA;
    protected $magangA;
    protected $userMhsB;
    protected $magangB;

    protected function setUp(): void
    {
        parent::setUp();

        // Pengaman: jangan pernah menulis data ke database selain database testing.
        $this->assertStringEndsWith(
            '_testing',
            DB::connection()->getDatabaseName(),
            'Test dihentikan: koneksi tidak mengarah ke database testing.'
        );

        Storage::fake('public');
        Storage::fake('local');

        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();

        [$this->userMitraA, $this->mitraA] = $this->createMitra();
        [$this->userMitraB, $this->mitraB] = $this->createMitra();

        [$this->userMhsA, $mhsA] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$this->userMhsB, $mhsB] = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $this->magangA = $this->createMagang($mhsA->mahasiswa_id, $this->mitraA->mitra_id);
        $this->magangB = $this->createMagang($mhsB->mahasiswa_id, $this->mitraB->mitra_id);
    }

    private function createUser(string $role): User
    {
        return User::factory()->create([
            'username'  => $role . '_' . Str::lower(Str::random(10)),
            'email'     => $role . '_' . Str::lower(Str::random(10)) . '@test.local',
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    private function createAdmin(int $prodiId): User
    {
        $user = $this->createUser('admin');
        Admin::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nama'     => 'Admin ' . Str::random(5),
        ]);
        return $user;
    }

    private function createMahasiswa(int $prodiId): array
    {
        $user = $this->createUser('mahasiswa');
        $mahasiswa = Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nim'      => 'NIM' . Str::upper(Str::random(12)),
            'nama'     => 'Mahasiswa ' . Str::random(5),
            'angkatan' => 2023,
        ]);
        return [$user, $mahasiswa];
    }

    private function createMitra(): array
    {
        $user = $this->createUser('mitra');
        $mitra = Mitra::create([
            'user_id'    => $user->user_id,
            'nama_mitra' => 'PT Uji ' . Str::random(8),
            'email'      => $user->email,
        ]);
        return [$user, $mitra];
    }

    private function createMagang(int $mahasiswaId, ?int $mitraId, string $status = 'draft'): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'mitra_id'        => $mitraId,
            'semester_magang' => 6,
            'role_magang'     => 'Developer',
            'jobdesk'         => 'Backend',
            'status_magang'   => $status,
        ]);
    }

    private function payload(Magang $magang, ?UploadedFile $file = null): array
    {
        return [
            'magang_id'                        => $magang->magang_id,
            'nilai_teknis'                     => 85,
            'nilai_profesionalisme_etika'      => 88,
            'nilai_komunikasi_presentasi'      => 82,
            'nilai_proyek_pengalaman_industri' => 90,
            'supervisor'                       => 'Rina',
            'jabatan_supervisor'               => 'Engineering Manager',
            'file'                             => $file ?? UploadedFile::fake()->create('penilaian.pdf', 100, 'application/pdf'),
        ];
    }

    /** Buat penilaian lewat API supaya file fisiknya benar-benar tersimpan. */
    private function createPenilaianViaApi(): NilaiMitra
    {
        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(201);

        return NilaiMitra::where('magang_id', $this->magangA->magang_id)->firstOrFail();
    }

    private function storedPenilaianFiles(): array
    {
        return Storage::disk('public')->allFiles("dokumen-penilaian/{$this->magangA->magang_id}");
    }

    // ------------------------------------------------------------------
    // A. File penilaian
    // ------------------------------------------------------------------

    public function test_store_rejects_non_pdf_content_even_with_pdf_extension()
    {
        $cases = [
            'html dengan nama .pdf' => UploadedFile::fake()->create('nilai.pdf', 10, 'text/html'),
            'executable'            => UploadedFile::fake()->create('nilai.exe', 10, 'application/x-msdownload'),
            'gambar'                => UploadedFile::fake()->create('nilai.png', 10, 'image/png'),
        ];

        foreach ($cases as $case => $file) {
            $response = $this->actingAs($this->userMitraA)
                ->postJson('/api/penilaian-mitra', $this->payload($this->magangA, $file));

            $this->assertSame(422, $response->status(), "Kasus '{$case}' seharusnya 422, didapat {$response->status()}.");
            $response->assertJsonValidationErrors(['file']);
        }

        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangA->magang_id]);
        $this->assertSame([], $this->storedPenilaianFiles());
    }

    public function test_store_enforces_5_mb_limit()
    {
        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload(
                $this->magangA,
                UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf')
            ))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload(
                $this->magangA,
                UploadedFile::fake()->create('pas.pdf', 5120, 'application/pdf')
            ))
            ->assertStatus(201);
    }

    public function test_php_client_filename_is_rejected_even_with_pdf_content()
    {
        // Rule `mimes` Laravel memblokir nama file klien berekstensi PHP.
        $file = UploadedFile::fake()->create('shell.php', 10, 'application/pdf');

        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA, $file))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertSame([], $this->storedPenilaianFiles());
    }

    public function test_stored_file_extension_does_not_follow_client_filename()
    {
        // Isi file PDF, tetapi nama dari klien berekstensi .html
        $file = UploadedFile::fake()->create('nilai.html', 10, 'application/pdf');

        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA, $file))
            ->assertStatus(201);

        $penilaian = NilaiMitra::where('magang_id', $this->magangA->magang_id)->firstOrFail();
        $this->assertStringEndsWith('.pdf', $penilaian->file_path);
        Storage::disk('public')->assertExists($penilaian->file_path);
    }

    public function test_store_cleans_up_file_when_database_insert_fails()
    {
        NilaiMitra::creating(function () {
            throw new \RuntimeException('Simulasi kegagalan database');
        });

        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(500);

        $this->assertSame([], $this->storedPenilaianFiles());
    }

    public function test_file_can_be_replaced_via_post_with_method_put()
    {
        $penilaian = $this->createPenilaianViaApi();
        $oldPath = $penilaian->file_path;

        $this->actingAs($this->userMitraA)
            ->post("/api/penilaian-mitra/{$penilaian->penilaian_id}", [
                '_method' => 'PUT',
                'file'    => UploadedFile::fake()->create('revisi.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(200);

        $penilaian->refresh();
        $this->assertNotSame($oldPath, $penilaian->file_path);
        $this->assertSame('revisi.pdf', $penilaian->nama_file);
        Storage::disk('public')->assertExists($penilaian->file_path);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_update_rejects_non_pdf_and_keeps_old_file()
    {
        $penilaian = $this->createPenilaianViaApi();
        $oldPath = $penilaian->file_path;

        $this->actingAs($this->userMitraA)
            ->post("/api/penilaian-mitra/{$penilaian->penilaian_id}", [
                '_method' => 'PUT',
                'file'    => UploadedFile::fake()->create('revisi.pdf', 10, 'text/html'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertSame($oldPath, $penilaian->fresh()->file_path);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame([$oldPath], $this->storedPenilaianFiles());
    }

    public function test_update_failure_keeps_old_file_and_removes_new_file()
    {
        $penilaian = $this->createPenilaianViaApi();
        $oldPath = $penilaian->file_path;

        NilaiMitra::updating(function () {
            throw new \RuntimeException('Simulasi kegagalan database');
        });

        $this->actingAs($this->userMitraA)
            ->post("/api/penilaian-mitra/{$penilaian->penilaian_id}", [
                '_method' => 'PUT',
                'file'    => UploadedFile::fake()->create('revisi.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame($oldPath, $penilaian->fresh()->file_path);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame([$oldPath], $this->storedPenilaianFiles());
    }

    public function test_update_returning_false_keeps_old_file_and_removes_new_file()
    {
        $penilaian = $this->createPenilaianViaApi();
        $oldPath = $penilaian->file_path;

        // Update dibatalkan tanpa exception (Eloquent update() mengembalikan false).
        NilaiMitra::updating(function () {
            return false;
        });

        $this->actingAs($this->userMitraA)
            ->post("/api/penilaian-mitra/{$penilaian->penilaian_id}", [
                '_method' => 'PUT',
                'file'    => UploadedFile::fake()->create('revisi.pdf', 50, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(500);

        $this->assertSame($oldPath, $penilaian->fresh()->file_path);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame([$oldPath], $this->storedPenilaianFiles());
    }

    // ------------------------------------------------------------------
    // Akun Mitra tanpa profil
    // ------------------------------------------------------------------

    public function test_mitra_account_without_profile_cannot_list_any_penilaian()
    {
        $orphanMitraUser = $this->createUser('mitra');

        // Penilaian pada magang tanpa mitra (mitra_id NULL) dan penilaian biasa.
        [, $mhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magangTanpaMitra = $this->createMagang($mhs->mahasiswa_id, null);
        $penilaianTanpaMitra = NilaiMitra::create([
            'magang_id'                        => $magangTanpaMitra->magang_id,
            'nilai_teknis'                     => 80,
            'nilai_profesionalisme_etika'      => 80,
            'nilai_komunikasi_presentasi'      => 80,
            'nilai_proyek_pengalaman_industri' => 80,
            'supervisor'                       => 'Supervisor',
            'jabatan_supervisor'               => 'Manager',
            'nama_file'                        => 'penilaian.pdf',
            'file_path'                        => "dokumen-penilaian/{$magangTanpaMitra->magang_id}/penilaian.pdf",
        ]);
        $this->createPenilaianViaApi();

        $response = $this->actingAs($orphanMitraUser)->getJson('/api/penilaian-mitra?per_page=100');
        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
        $this->assertSame(0, $response->json('total'));

        // Filter query tidak boleh membuka akses.
        $response = $this->actingAs($orphanMitraUser)
            ->getJson("/api/penilaian-mitra?per_page=100&magang_id={$magangTanpaMitra->magang_id}");
        $response->assertStatus(200);
        $this->assertSame(0, $response->json('total'));

        $this->actingAs($orphanMitraUser)
            ->getJson("/api/penilaian-mitra/{$penilaianTanpaMitra->penilaian_id}")
            ->assertStatus(403);
    }

    // ------------------------------------------------------------------
    // B. GET /api/mitra/magang/{id}
    // ------------------------------------------------------------------

    public function test_mitra_by_magang_is_accessible_by_owner_parties()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        foreach ([$this->userMhsA, $this->userMitraA, $adminTRPL] as $actor) {
            $this->actingAs($actor)
                ->getJson("/api/mitra/magang/{$this->magangA->magang_id}")
                ->assertStatus(200)
                ->assertJsonPath('mitra_id', $this->mitraA->mitra_id);
        }
    }

    public function test_mitra_by_magang_rejects_other_parties()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        // Mitra B, mahasiswa B, dan admin TRPL tidak boleh tahu magang A ditempatkan di mana / magang B ditempatkan di mana.
        $this->actingAs($this->userMitraB)
            ->getJson("/api/mitra/magang/{$this->magangA->magang_id}")
            ->assertStatus(403);

        $this->actingAs($this->userMhsB)
            ->getJson("/api/mitra/magang/{$this->magangA->magang_id}")
            ->assertStatus(403);

        $this->actingAs($adminTRPL)
            ->getJson("/api/mitra/magang/{$this->magangB->magang_id}")
            ->assertStatus(403);
    }

    public function test_mitra_by_magang_returns_404_for_unknown_magang()
    {
        $this->actingAs($this->userMitraA)
            ->getJson('/api/mitra/magang/999999999')
            ->assertStatus(404);
    }

    // ------------------------------------------------------------------
    // C. Penghapusan Mitra
    // ------------------------------------------------------------------

    public function test_cannot_delete_mitra_that_still_has_magang()
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->deleteJson("/api/mitra/{$this->mitraA->mitra_id}");

        $response->assertStatus(409)->assertJsonStructure(['message']);
        $this->assertDatabaseHas('mitra', ['mitra_id' => $this->mitraA->mitra_id]);
        $this->assertDatabaseHas('users', ['user_id' => $this->userMitraA->user_id]);
        $this->assertDatabaseHas('magang', [
            'magang_id' => $this->magangA->magang_id,
            'mitra_id'  => $this->mitraA->mitra_id,
        ]);
    }

    public function test_cannot_delete_mitra_with_finished_and_assessed_magang()
    {
        $superAdmin = $this->createUser('super_admin');
        [$userMitra, $mitra] = $this->createMitra();
        [, $mhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $magang = $this->createMagang($mhs->mahasiswa_id, $mitra->mitra_id, 'selesai');
        NilaiMitra::create([
            'magang_id'                        => $magang->magang_id,
            'nilai_teknis'                     => 80,
            'nilai_profesionalisme_etika'      => 80,
            'nilai_komunikasi_presentasi'      => 80,
            'nilai_proyek_pengalaman_industri' => 80,
            'supervisor'                       => 'Supervisor',
            'jabatan_supervisor'               => 'Manager',
            'nama_file'                        => 'penilaian.pdf',
            'file_path'                        => "dokumen-penilaian/{$magang->magang_id}/penilaian.pdf",
        ]);

        $this->actingAs($superAdmin)
            ->deleteJson("/api/mitra/{$mitra->mitra_id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitra->mitra_id]);
        $this->assertDatabaseHas('users', ['user_id' => $userMitra->user_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id, 'mitra_id' => $mitra->mitra_id]);
        $this->assertDatabaseHas('penilaian_mitra', ['magang_id' => $magang->magang_id]);
    }

    public function test_admin_cannot_delete_mitra_used_only_by_own_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        // Mitra A hanya dipakai mahasiswa TRPL: lolos cek lintas prodi, tetapi tetap ditolak karena punya relasi magang.
        $this->actingAs($adminTRPL)
            ->deleteJson("/api/mitra/{$this->mitraA->mitra_id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $this->magangA->magang_id,
            'mitra_id'  => $this->mitraA->mitra_id,
        ]);
    }

    public function test_deleting_unused_mitra_also_deletes_its_account()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userMitra, $mitra] = $this->createMitra();

        $this->actingAs($adminTRPL)
            ->deleteJson("/api/mitra/{$mitra->mitra_id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('mitra', ['mitra_id' => $mitra->mitra_id]);
        $this->assertDatabaseMissing('users', ['user_id' => $userMitra->user_id]);
    }
}
