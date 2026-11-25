<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Mahasiswa;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagangPeriodHistoryTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;
    private User $admin;
    private Mahasiswa $mahasiswa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $this->admin->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nama' => 'Admin Periode',
        ]);

        $studentUser = User::factory()->create(['role' => 'mahasiswa']);
        $this->mahasiswa = Mahasiswa::create([
            'user_id' => $studentUser->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nim' => '24/12345/SV/90001',
            'nama' => 'Mahasiswa Riwayat',
            'angkatan' => '2024',
        ]);
    }

    private function createPeriod(string $tahunAjaran, int $semester, string $jenis): PeriodeMagang
    {
        $year = TahunAjaran::create([
            'tahun_ajaran' => $tahunAjaran,
            'is_active' => true,
        ]);

        return PeriodeMagang::create([
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => $semester,
            'jenis_semester' => $jenis,
            'is_active' => true,
        ]);
    }

    public function test_admin_creates_magang_with_master_period_and_legacy_columns_are_synced(): void
    {
        $periode = $this->createPeriod('2026/2027', 6, 'Genap');

        $response = $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $periode->periode_id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.periode_id', $periode->periode_id)
            ->assertJsonPath('data.periode.periode_id', $periode->periode_id)
            ->assertJsonPath('data.periode.tahun_ajaran.tahun_ajaran', '2026/2027')
            ->assertJsonPath('data.periode.jenis_semester', 'Genap')
            ->assertJsonPath('data.semester_magang', 6)
            ->assertJsonPath('data.tahun_ajaran', 2026);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $response->json('data.magang_id'),
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $periode->periode_id,
            'semester_magang' => 6,
            'tahun_ajaran' => 2026,
        ]);
    }

    public function test_student_can_have_history_in_different_periods_but_not_duplicate_in_same_period(): void
    {
        $first = $this->createPeriod('2026/2027', 6, 'Genap');
        $second = $this->createPeriod('2027/2028', 7, 'Gasal');

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $first->periode_id,
            'jobdesk' => 'Magang pertama',
        ])->assertCreated();

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $second->periode_id,
            'jobdesk' => 'Magang kedua',
        ])->assertCreated();

        $duplicate = $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $first->periode_id,
        ]);

        $duplicate->assertStatus(422)
            ->assertJsonValidationErrors(['periode_id']);

        $this->assertDatabaseCount('magang', 2);

        $detail = $this->actingAs($this->admin)
            ->getJson('/api/mahasiswa/' . $this->mahasiswa->mahasiswa_id)
            ->assertOk()
            ->assertJsonCount(2, 'magang')
            ->assertJsonPath('magang.0.periode_id', $second->periode_id)
            ->assertJsonPath('magang.1.periode_id', $first->periode_id);

        $this->assertEquals(
            ['2027/2028', '2026/2027'],
            collect($detail->json('magang'))
                ->pluck('periode.tahun_ajaran.tahun_ajaran')
                ->all()
        );
    }

    public function test_period_filters_only_return_matching_magang_and_student_history(): void
    {
        $first = $this->createPeriod('2026/2027', 6, 'Genap');
        $second = $this->createPeriod('2027/2028', 7, 'Gasal');

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $first->periode_id,
        ])->assertCreated();
        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $second->periode_id,
        ])->assertCreated();

        $this->actingAs($this->admin)
            ->getJson('/api/magang?periode_id=' . $first->periode_id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.periode_id', $first->periode_id);

        $this->actingAs($this->admin)
            ->getJson('/api/mahasiswa?periode_id=' . $second->periode_id)
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.mahasiswa_id', $this->mahasiswa->mahasiswa_id)
            ->assertJsonPath('data.0.magang.0.periode_id', $second->periode_id);

        $this->actingAs($this->mahasiswa->user)
            ->getJson('/api/magang?per_page=10')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data.0.periode_id', $second->periode_id)
            ->assertJsonPath('data.1.periode_id', $first->periode_id);
    }

    public function test_unknown_or_missing_period_is_rejected_when_master_periods_exist(): void
    {
        $this->createPeriod('2026/2027', 6, 'Genap');

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => 999999,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['periode_id']);

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'semester_magang' => 8,
            'tahun_ajaran' => 2026,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['periode_id']);

        $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['periode_id']);
    }

    public function test_updating_to_period_already_used_is_rejected_and_history_is_preserved(): void
    {
        $first = $this->createPeriod('2026/2027', 6, 'Genap');
        $second = $this->createPeriod('2027/2028', 7, 'Gasal');

        $firstMagang = $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $first->periode_id,
        ])->assertCreated();

        $secondMagang = $this->actingAs($this->admin)->postJson('/api/magang', [
            'mahasiswa_id' => $this->mahasiswa->mahasiswa_id,
            'periode_id' => $second->periode_id,
        ])->assertCreated();

        $this->actingAs($this->admin)
            ->putJson('/api/magang/' . $secondMagang->json('data.magang_id'), [
                'periode_id' => $first->periode_id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['periode_id']);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $firstMagang->json('data.magang_id'),
            'periode_id' => $first->periode_id,
        ]);
        $this->assertDatabaseHas('magang', [
            'magang_id' => $secondMagang->json('data.magang_id'),
            'periode_id' => $second->periode_id,
        ]);
        $this->assertDatabaseCount('magang', 2);
    }
}
