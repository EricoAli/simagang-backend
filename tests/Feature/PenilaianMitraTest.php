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
 * Otorisasi & validasi Penilaian Mitra (MTR-PN).
 *
 * Keputusan yang diacu:
 * - Mitra hanya boleh melihat, membuat, dan mengubah penilaian untuk magang
 *   yang terhubung ke Mitra yang sedang login.
 * - Admin Prodi mengikuti isolasi prodi yang sudah berlaku.
 * - Aturan penguncian, penghapusan oleh pemilik, dan koreksi oleh Admin/Dosen
 *   BELUM diputuskan, sehingga tidak diuji di sini.
 */
class PenilaianMitraTest extends TestCase
{
    use DatabaseTransactions;

    protected $prodiTRPL;
    protected $prodiTRIK;

    /** Mitra A + magang mahasiswa TRPL di Mitra A */
    protected $userMitraA;
    protected $mitraA;
    protected $userMhsA;
    protected $magangA;

    /** Mitra B + magang mahasiswa TRIK di Mitra B */
    protected $userMitraB;
    protected $mitraB;
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

    private function createMagang(int $mahasiswaId, ?int $mitraId): Magang
    {
        return Magang::create([
            'mahasiswa_id'    => $mahasiswaId,
            'mitra_id'        => $mitraId,
            'semester_magang' => 6,
            'role_magang'     => 'Developer',
            'jobdesk'         => 'Backend',
        ]);
    }

    /** Buat penilaian langsung (tanpa factory/seeder yang rusak). */
    private function createPenilaian(Magang $magang, float $nilaiTeknis = 80): NilaiMitra
    {
        return NilaiMitra::create([
            'magang_id'                        => $magang->magang_id,
            'nilai_teknis'                     => $nilaiTeknis,
            'nilai_profesionalisme_etika'      => 80,
            'nilai_komunikasi_presentasi'      => 80,
            'nilai_proyek_pengalaman_industri' => 80,
            'keterangan'                       => 'Penilaian uji',
            'supervisor'                       => 'Supervisor Uji',
            'jabatan_supervisor'               => 'Manager',
            'nama_file'                        => 'penilaian.pdf',
            'file_path'                        => "dokumen-penilaian/{$magang->magang_id}/penilaian.pdf",
        ]);
    }

    private function payload(Magang $magang, array $overrides = []): array
    {
        return array_merge([
            'magang_id'                        => $magang->magang_id,
            'nilai_teknis'                     => 85,
            'nilai_profesionalisme_etika'      => 88,
            'nilai_komunikasi_presentasi'      => 82,
            'nilai_proyek_pengalaman_industri' => 90,
            'keterangan'                       => 'Baik',
            'supervisor'                       => 'Rina',
            'jabatan_supervisor'               => 'Engineering Manager',
            'file'                             => UploadedFile::fake()->create('penilaian.pdf', 100, 'application/pdf'),
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Autentikasi
    // ------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/penilaian-mitra')->assertStatus(401);
        $this->postJson('/api/penilaian-mitra', $this->payload($this->magangA))->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Mitra: hanya magang miliknya
    // ------------------------------------------------------------------

    public function test_mitra_can_create_penilaian_for_own_magang()
    {
        $response = $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA));

        $response->assertStatus(201);
        $this->assertDatabaseHas('penilaian_mitra', [
            'magang_id'    => $this->magangA->magang_id,
            'nilai_teknis' => 85,
        ]);

        $penilaian = NilaiMitra::where('magang_id', $this->magangA->magang_id)->firstOrFail();
        Storage::disk('public')->assertExists($penilaian->file_path);
    }

    public function test_mitra_can_update_own_penilaian()
    {
        $penilaian = $this->createPenilaian($this->magangA, 70);

        $this->actingAs($this->userMitraA)
            ->putJson("/api/penilaian-mitra/{$penilaian->penilaian_id}", ['nilai_teknis' => 95])
            ->assertStatus(200);

        $this->assertDatabaseHas('penilaian_mitra', [
            'penilaian_id' => $penilaian->penilaian_id,
            'nilai_teknis' => 95,
        ]);
    }

    public function test_mitra_cannot_create_penilaian_for_other_mitra_magang()
    {
        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangB))
            ->assertStatus(403);

        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangB->magang_id]);
    }

    public function test_mitra_cannot_view_penilaian_of_other_mitra()
    {
        $penilaianB = $this->createPenilaian($this->magangB);

        $this->actingAs($this->userMitraA)
            ->getJson("/api/penilaian-mitra/{$penilaianB->penilaian_id}")
            ->assertStatus(403);
    }

    public function test_mitra_cannot_update_penilaian_of_other_mitra()
    {
        $penilaianB = $this->createPenilaian($this->magangB, 75);

        $this->actingAs($this->userMitraA)
            ->putJson("/api/penilaian-mitra/{$penilaianB->penilaian_id}", ['nilai_teknis' => 10])
            ->assertStatus(403);

        $this->assertDatabaseHas('penilaian_mitra', [
            'penilaian_id' => $penilaianB->penilaian_id,
            'nilai_teknis' => 75,
        ]);
    }

    public function test_mitra_cannot_delete_penilaian_of_other_mitra()
    {
        $penilaianB = $this->createPenilaian($this->magangB);

        $this->actingAs($this->userMitraA)
            ->deleteJson("/api/penilaian-mitra/{$penilaianB->penilaian_id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('penilaian_mitra', ['penilaian_id' => $penilaianB->penilaian_id]);
    }

    public function test_mitra_index_only_returns_own_penilaian()
    {
        $penilaianA = $this->createPenilaian($this->magangA);
        $penilaianB = $this->createPenilaian($this->magangB);

        $response = $this->actingAs($this->userMitraA)->getJson('/api/penilaian-mitra?per_page=100');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('penilaian_id')->all();
        $this->assertContains($penilaianA->penilaian_id, $ids);
        $this->assertNotContains($penilaianB->penilaian_id, $ids);

        // Filter query tidak boleh dipakai untuk melewati pembatasan.
        $response = $this->actingAs($this->userMitraA)
            ->getJson("/api/penilaian-mitra?per_page=100&mitra_id={$this->mitraB->mitra_id}");
        $response->assertStatus(200);
        $this->assertNotContains(
            $penilaianB->penilaian_id,
            collect($response->json('data'))->pluck('penilaian_id')->all()
        );
    }

    public function test_mitra_account_without_mitra_profile_cannot_create_penilaian()
    {
        $orphanMitraUser = $this->createUser('mitra');

        $this->actingAs($orphanMitraUser)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(403);

        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangA->magang_id]);
    }

    // ------------------------------------------------------------------
    // Mahasiswa & Dosbing
    // ------------------------------------------------------------------

    public function test_mahasiswa_cannot_create_update_or_delete_own_penilaian()
    {
        $this->actingAs($this->userMhsA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(403);
        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangA->magang_id]);

        $penilaian = $this->createPenilaian($this->magangA, 60);

        $this->actingAs($this->userMhsA)
            ->putJson("/api/penilaian-mitra/{$penilaian->penilaian_id}", ['nilai_teknis' => 100])
            ->assertStatus(403);

        $this->actingAs($this->userMhsA)
            ->deleteJson("/api/penilaian-mitra/{$penilaian->penilaian_id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('penilaian_mitra', [
            'penilaian_id' => $penilaian->penilaian_id,
            'nilai_teknis' => 60,
        ]);
    }

    public function test_mahasiswa_cannot_view_other_students_penilaian()
    {
        $penilaianB = $this->createPenilaian($this->magangB);

        $this->actingAs($this->userMhsA)
            ->getJson("/api/penilaian-mitra/{$penilaianB->penilaian_id}")
            ->assertStatus(403);
    }

    public function test_dosbing_cannot_create_penilaian()
    {
        $dosbing = $this->createUser('dosbing');

        $this->actingAs($dosbing)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(403);

        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangA->magang_id]);
    }

    // ------------------------------------------------------------------
    // Admin Prodi: isolasi prodi
    // ------------------------------------------------------------------

    public function test_admin_can_view_penilaian_of_own_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $penilaianA = $this->createPenilaian($this->magangA);

        $this->actingAs($adminTRPL)
            ->getJson("/api/penilaian-mitra/{$penilaianA->penilaian_id}")
            ->assertStatus(200)
            ->assertJsonPath('penilaian_id', $penilaianA->penilaian_id);
    }

    public function test_admin_cannot_view_penilaian_of_other_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $penilaianA = $this->createPenilaian($this->magangA);
        $penilaianB = $this->createPenilaian($this->magangB);

        $this->actingAs($adminTRPL)
            ->getJson("/api/penilaian-mitra/{$penilaianB->penilaian_id}")
            ->assertStatus(403);

        $response = $this->actingAs($adminTRPL)->getJson('/api/penilaian-mitra?per_page=100');
        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('penilaian_id')->all();
        $this->assertContains($penilaianA->penilaian_id, $ids);
        $this->assertNotContains($penilaianB->penilaian_id, $ids);
    }

    // ------------------------------------------------------------------
    // Validasi
    // ------------------------------------------------------------------

    public function test_create_rejects_duplicate_penilaian_for_same_magang()
    {
        $this->createPenilaian($this->magangA);

        $this->actingAs($this->userMitraA)
            ->postJson('/api/penilaian-mitra', $this->payload($this->magangA))
            ->assertStatus(422);

        $this->assertSame(1, NilaiMitra::where('magang_id', $this->magangA->magang_id)->count());
    }

    public function test_create_rejects_invalid_input()
    {
        $cases = [
            'nilai_teknis > 100'          => [['nilai_teknis' => 101], 'nilai_teknis'],
            'nilai_teknis < 0'            => [['nilai_teknis' => -1], 'nilai_teknis'],
            'nilai bukan angka'           => [['nilai_komunikasi_presentasi' => 'abc'], 'nilai_komunikasi_presentasi'],
            'supervisor kosong'           => [['supervisor' => ''], 'supervisor'],
            'jabatan_supervisor kosong'   => [['jabatan_supervisor' => ''], 'jabatan_supervisor'],
            'file tidak dikirim'          => [['file' => null], 'file'],
            'magang_id tidak ada'         => [['magang_id' => 999999999], 'magang_id'],
        ];

        foreach ($cases as $case => [$overrides, $field]) {
            $response = $this->actingAs($this->userMitraA)
                ->postJson('/api/penilaian-mitra', $this->payload($this->magangA, $overrides));

            $this->assertSame(422, $response->status(), "Kasus '{$case}' seharusnya 422, didapat {$response->status()}.");
            $response->assertJsonValidationErrors([$field]);
        }

        $this->assertDatabaseMissing('penilaian_mitra', ['magang_id' => $this->magangA->magang_id]);
    }

    public function test_update_rejects_out_of_range_value()
    {
        $penilaian = $this->createPenilaian($this->magangA, 70);

        $this->actingAs($this->userMitraA)
            ->putJson("/api/penilaian-mitra/{$penilaian->penilaian_id}", ['nilai_teknis' => 150])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nilai_teknis']);

        $this->assertDatabaseHas('penilaian_mitra', [
            'penilaian_id' => $penilaian->penilaian_id,
            'nilai_teknis' => 70,
        ]);
    }
}
