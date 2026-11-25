<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardRecentActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $trpl;
    private Prodi $trik;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->trpl = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $this->trik = Prodi::where('kode_prodi', 'TRIK')->firstOrFail();
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nama' => 'Admin Fixture',
        ]);

        return $user;
    }

    private function createMagang(int $prodiId, string $name, string $status): Magang
    {
        $mahasiswaUser = User::factory()->create(['role' => 'mahasiswa']);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $mahasiswaUser->user_id,
            'prodi_id' => $prodiId,
            'nim' => 'NIM-' . uniqid(),
            'nama' => $name,
            'angkatan' => '2024',
        ]);

        return Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 6,
            'status_magang' => $status,
            'tanggal_mulai' => '2026-01-12',
            'tanggal_selesai' => '2026-06-12',
        ]);
    }

    private function setActivityTime(Magang $magang, string $timestamp): void
    {
        DB::table('magang')
            ->where('magang_id', $magang->magang_id)
            ->update(['updated_at' => $timestamp]);
    }

    public function test_super_admin_sees_latest_ongoing_and_completed_activities_across_prodi(): void
    {
        $older = $this->createMagang($this->trpl->prodi_id, 'TRPL Aktif', 'berlangsung');
        $newer = $this->createMagang($this->trik->prodi_id, 'TRIK Selesai', 'selesai');
        $draft = $this->createMagang($this->trpl->prodi_id, 'Draft Tidak Ditampilkan', 'draft');
        $rejected = $this->createMagang($this->trik->prodi_id, 'Ditolak Tidak Ditampilkan', 'ditolak');

        $this->setActivityTime($older, '2026-11-19 09:00:00');
        $this->setActivityTime($newer, '2026-11-20 09:00:00');
        $this->setActivityTime($draft, '2026-11-21 09:00:00');
        $this->setActivityTime($rejected, '2026-11-22 09:00:00');

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->getJson('/api/dashboard/admin')
            ->assertOk()
            ->assertJsonCount(2, 'aktivitas_terbaru')
            ->assertJsonPath('aktivitas_terbaru.0.magang_id', $newer->magang_id)
            ->assertJsonPath('aktivitas_terbaru.0.nama_mahasiswa', 'TRIK Selesai')
            ->assertJsonPath('aktivitas_terbaru.0.kode_prodi', 'TRIK')
            ->assertJsonPath('aktivitas_terbaru.0.status_magang', 'selesai')
            ->assertJsonPath('aktivitas_terbaru.1.magang_id', $older->magang_id)
            ->assertJsonPath('aktivitas_terbaru.1.status_magang', 'berlangsung');

        $this->actingAs($superAdmin)
            ->getJson('/api/magang/' . $newer->magang_id)
            ->assertOk()
            ->assertJsonPath('magang_id', $newer->magang_id);
    }

    public function test_admin_sees_only_own_prodi_activities_and_cannot_open_foreign_detail(): void
    {
        $ownMagang = $this->createMagang($this->trpl->prodi_id, 'TRPL Mahasiswa', 'berlangsung');
        $foreignMagang = $this->createMagang($this->trik->prodi_id, 'TRIK Mahasiswa', 'selesai');
        $admin = $this->createAdmin($this->trpl->prodi_id);

        $this->actingAs($admin)
            ->getJson('/api/dashboard/admin')
            ->assertOk()
            ->assertJsonCount(1, 'aktivitas_terbaru')
            ->assertJsonPath('aktivitas_terbaru.0.magang_id', $ownMagang->magang_id)
            ->assertJsonPath('aktivitas_terbaru.0.kode_prodi', 'TRPL');

        $this->actingAs($admin)
            ->getJson('/api/magang/' . $foreignMagang->magang_id)
            ->assertForbidden();
    }

    public function test_activity_list_is_limited_to_five_items(): void
    {
        for ($index = 1; $index <= 6; $index++) {
            $magang = $this->createMagang(
                $this->trpl->prodi_id,
                'Mahasiswa ' . $index,
                $index % 2 === 0 ? 'selesai' : 'berlangsung'
            );
            $this->setActivityTime($magang, sprintf('2026-11-%02d 09:00:00', $index));
        }

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->getJson('/api/dashboard/admin')
            ->assertOk()
            ->assertJsonCount(5, 'aktivitas_terbaru')
            ->assertJsonPath('aktivitas_terbaru.0.nama_mahasiswa', 'Mahasiswa 6');
    }
}
