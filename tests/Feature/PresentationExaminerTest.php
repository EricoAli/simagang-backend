<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Dosbing;
use App\Models\JadwalPresentasi;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresentationExaminerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProdiSeeder::class);
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nama' => 'Admin Uji',
        ]);

        return $user;
    }

    private function schedulePayload(int $examinerId): array
    {
        return [
            'dosen_penguji_id' => $examinerId,
            'tanggal_presentasi' => '2026-12-10',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '11:00',
            'ruangan' => 'CU207',
            'keterangan' => 'Presentasi PI',
        ];
    }

    public function test_super_admin_creates_schedule_with_examiner_and_response_contains_relation(): void
    {
        $prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $examiner = Dosbing::create([
            'nip' => '198001012010011001',
            'nama' => 'Dosen Penguji TRPL',
            'prodi_id' => $prodi->prodi_id,
        ]);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->postJson(
            '/api/jadwal-presentasi',
            $this->schedulePayload($examiner->dosbing_id)
        )->assertCreated()
            ->assertJsonPath('data.dosen_penguji_id', $examiner->dosbing_id)
            ->assertJsonPath('data.dosen_penguji', 'Dosen Penguji TRPL')
            ->assertJsonPath('data.dosen_penguji_data.dosbing_id', $examiner->dosbing_id);

        $jadwalId = $response->json('data.jadwal_id');
        $this->assertDatabaseHas('jadwal_presentasi', [
            'jadwal_id' => $jadwalId,
            'dosen_penguji_id' => $examiner->dosbing_id,
        ]);

        $this->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonPath('data.0.dosen_penguji', 'Dosen Penguji TRPL');

        $this->getJson("/api/jadwal-presentasi/{$jadwalId}")
            ->assertOk()
            ->assertJsonPath('dosen_penguji', 'Dosen Penguji TRPL');
    }

    public function test_invalid_examiner_id_is_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->postJson('/api/jadwal-presentasi', $this->schedulePayload(999999))
            ->assertUnprocessable();
    }

    public function test_admin_can_choose_own_prodi_examiner_but_not_other_prodi(): void
    {
        $trpl = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $trik = Prodi::where('kode_prodi', 'TRIK')->firstOrFail();
        $ownExaminer = Dosbing::create([
            'nip' => '198001012010011002',
            'nama' => 'Dosen TRPL',
            'prodi_id' => $trpl->prodi_id,
        ]);
        $otherExaminer = Dosbing::create([
            'nip' => '198001012010011003',
            'nama' => 'Dosen TRIK',
            'prodi_id' => $trik->prodi_id,
        ]);
        $admin = $this->createAdmin($trpl->prodi_id);

        $response = $this->actingAs($admin)->postJson(
            '/api/jadwal-presentasi',
            $this->schedulePayload($ownExaminer->dosbing_id)
        )->assertCreated();
        $jadwalId = $response->json('data.jadwal_id');

        $this->postJson('/api/jadwal-presentasi', $this->schedulePayload($otherExaminer->dosbing_id))
            ->assertForbidden();

        $this->getJson('/api/dosbing')
            ->assertOk()
            ->assertJsonFragment(['dosbing_id' => $ownExaminer->dosbing_id])
            ->assertJsonMissing(['dosbing_id' => $otherExaminer->dosbing_id]);

        $this->putJson("/api/jadwal-presentasi/{$jadwalId}", $this->schedulePayload($otherExaminer->dosbing_id))
            ->assertForbidden();
    }
}
