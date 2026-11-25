<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Dosbing;
use App\Models\JadwalPeserta;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPesertaAndProdiFilterTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $trpl;
    private Prodi $trik;
    private int $nimCounter = 10000;
    private int $dosbingCounter = 198001012010011001;

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

    private function createMahasiswa(int $prodiId, string $name, string $angkatan = '2024'): Mahasiswa
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);

        return Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nim' => sprintf('24/12345/SV/%05d', $this->nimCounter++),
            'nama' => $name,
            'angkatan' => $angkatan,
        ]);
    }

    private function createMagang(Mahasiswa $mahasiswa, string $status = 'berlangsung', int $semester = 6): Magang
    {
        return Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => $semester,
            'status_magang' => $status,
            'role_magang' => 'Software Engineer',
            'jobdesk' => 'API testing fixture',
        ]);
    }

    private function createJadwal(?int $prodiId = null): JadwalPresentasi
    {
        $dosenPengujiId = null;
        if ($prodiId) {
            $dosenPengujiId = Dosbing::create([
                'nip' => (string) $this->dosbingCounter++,
                'nama' => 'Dosen Penguji Fixture',
                'prodi_id' => $prodiId,
            ])->dosbing_id;
        }

        return JadwalPresentasi::create([
            'dosen_penguji_id' => $dosenPengujiId,
            'tanggal_presentasi' => '2026-12-10',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '11:00',
            'ruangan' => 'R-101',
            'keterangan' => 'Fixture jadwal',
        ]);
    }

    private function addAs(User $user, JadwalPresentasi $jadwal, Magang $magang)
    {
        return $this->actingAs($user)->postJson(
            "/api/jadwal-presentasi/{$jadwal->jadwal_id}/peserta",
            ['magang_id' => $magang->magang_id]
        );
    }

    public function test_super_admin_can_manage_participants_across_prodi(): void
    {
        $jadwal = $this->createJadwal($this->trpl->prodi_id);
        $trplMagang = $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'TRPL Mahasiswa'));
        $trikMagang = $this->createMagang($this->createMahasiswa($this->trik->prodi_id, 'TRIK Mahasiswa'), 'berlangsung', 7);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->addAs($superAdmin, $jadwal, $trplMagang)->assertCreated();
        $this->addAs($superAdmin, $jadwal, $trikMagang)->assertCreated();

        $participants = $this->actingAs($superAdmin)
            ->getJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/peserta")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$this->trpl->kode_prodi, $this->trik->kode_prodi],
            collect($participants->json('data'))->pluck('mahasiswa.prodi.kode_prodi')->all()
        );
    }

    public function test_admin_can_manage_only_its_own_prodi_participants(): void
    {
        $jadwal = $this->createJadwal($this->trpl->prodi_id);
        $trplMahasiswa = $this->createMahasiswa($this->trpl->prodi_id, 'TRPL Peserta');
        $trikMahasiswa = $this->createMahasiswa($this->trik->prodi_id, 'TRIK Peserta');
        $trplMagang = $this->createMagang($trplMahasiswa);
        $trikMagang = $this->createMagang($trikMahasiswa);
        $trplCandidate = $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'TRPL Kandidat'), 'berlangsung', 7);
        $admin = $this->createAdmin($this->trpl->prodi_id);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->addAs($superAdmin, $jadwal, $trplMagang)->assertCreated();
        $this->addAs($superAdmin, $jadwal, $trikMagang)->assertCreated();

        $this->actingAs($admin)
            ->getJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/peserta")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.magang_id', $trplMagang->magang_id);

        $this->actingAs($admin)
            ->getJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/mahasiswa-eligible")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.magang_id', $trplCandidate->magang_id);

        $this->addAs($admin, $jadwal, $trikMagang)->assertForbidden();
        $this->actingAs($admin)
            ->deleteJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/peserta/{$trikMagang->magang_id}")
            ->assertForbidden();
        $this->addAs($admin, $jadwal, $trplCandidate)->assertCreated();
    }

    public function test_student_and_mitra_cannot_read_or_manage_participant_management_endpoints(): void
    {
        $jadwal = $this->createJadwal();
        $magang = $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'Mahasiswa Fixture'));

        foreach (['mahasiswa', 'mitra', 'dosbing'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $base = "/api/jadwal-presentasi/{$jadwal->jadwal_id}";

            $this->actingAs($user)->getJson("{$base}/peserta")->assertForbidden();
            $this->actingAs($user)->getJson("{$base}/mahasiswa-eligible")->assertForbidden();
            $this->actingAs($user)->postJson("{$base}/peserta", [
                'magang_id' => $magang->magang_id,
            ])->assertForbidden();
            $this->actingAs($user)
                ->deleteJson("{$base}/peserta/{$magang->magang_id}")
                ->assertForbidden();
        }
    }

    public function test_student_only_sees_and_opens_presentation_schedules_where_they_are_a_participant(): void
    {
        $studentUser = User::factory()->create(['role' => 'mahasiswa']);
        $student = Mahasiswa::create([
            'user_id' => $studentUser->user_id,
            'prodi_id' => $this->trpl->prodi_id,
            'nim' => '24/12345/SV/00999',
            'nama' => 'Revaldo Fixture',
            'angkatan' => '2024',
        ]);
        $otherStudent = $this->createMahasiswa($this->trpl->prodi_id, 'Mahasiswa Lain');
        $ownSchedule = $this->createJadwal($this->trpl->prodi_id);
        $otherSchedule = $this->createJadwal();
        $mismatchedSchedule = $this->createJadwal();
        $ownMagang = $this->createMagang($student);
        $otherMagang = $this->createMagang($otherStudent);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->addAs($superAdmin, $ownSchedule, $ownMagang)->assertCreated();
        $this->addAs($superAdmin, $otherSchedule, $otherMagang)->assertCreated();
        JadwalPeserta::create([
            'jadwal_id' => $mismatchedSchedule->jadwal_id,
            'magang_id' => $otherMagang->magang_id,
            'mahasiswa_id' => $student->mahasiswa_id,
        ]);

        $this->actingAs($studentUser)
            ->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.jadwal_id', $ownSchedule->jadwal_id);

        $this->getJson("/api/jadwal-presentasi/{$ownSchedule->jadwal_id}")
            ->assertOk()
            ->assertJsonPath('jadwal_id', $ownSchedule->jadwal_id);

        $this->getJson("/api/magang/{$ownMagang->magang_id}/detail")
            ->assertOk()
            ->assertJsonPath('jadwal_presentasi.0.jadwal_id', $ownSchedule->jadwal_id)
            ->assertJsonPath('jadwal_presentasi.0.magang_id', $ownMagang->magang_id)
            ->assertJsonPath('jadwal_presentasi.0.nama_mahasiswa', 'Revaldo Fixture')
            ->assertJsonPath('jadwal_presentasi.0.dosen_penguji', 'Dosen Penguji Fixture');

        $this->actingAs($superAdmin)
            ->getJson("/api/magang/{$ownMagang->magang_id}/detail")
            ->assertOk()
            ->assertJsonPath('jadwal_presentasi.0.jadwal_id', $ownSchedule->jadwal_id)
            ->assertJsonPath('jadwal_presentasi.0.dosen_penguji', 'Dosen Penguji Fixture');

        $this->actingAs($studentUser)
            ->getJson("/api/magang/{$otherMagang->magang_id}/detail")
            ->assertForbidden();

        $this->getJson("/api/jadwal-presentasi/{$otherSchedule->jadwal_id}")
            ->assertForbidden();
        $this->getJson("/api/jadwal-presentasi/{$mismatchedSchedule->jadwal_id}")
            ->assertForbidden();
    }

    public function test_duplicate_student_is_rejected_and_removal_keeps_magang_and_mahasiswa(): void
    {
        $jadwal = $this->createJadwal($this->trpl->prodi_id);
        $mahasiswa = $this->createMahasiswa($this->trpl->prodi_id, 'Mahasiswa Peserta');
        $magang = $this->createMagang($mahasiswa);
        $admin = $this->createAdmin($this->trpl->prodi_id);

        $this->addAs($admin, $jadwal, $magang)->assertCreated();
        $this->addAs($admin, $jadwal, $magang)->assertStatus(409);

        $this->actingAs($mahasiswa->user)
            ->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin)
            ->deleteJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/peserta/{$magang->magang_id}")
            ->assertOk();

        $this->actingAs($mahasiswa->user)
            ->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson("/api/magang/{$magang->magang_id}/detail")
            ->assertOk()
            ->assertJsonCount(0, 'jadwal_presentasi');

        $this->assertDatabaseMissing('jadwal_peserta', [
            'jadwal_id' => $jadwal->jadwal_id,
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
        ]);
        $this->assertDatabaseHas('mahasiswa', ['mahasiswa_id' => $mahasiswa->mahasiswa_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
    }

    public function test_eligible_candidates_require_ongoing_magang_in_semester_six_or_seven(): void
    {
        $jadwal = $this->createJadwal($this->trpl->prodi_id);
        $valid = $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'Eligible'));
        $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'Draft'), 'draft');
        $this->createMagang($this->createMahasiswa($this->trpl->prodi_id, 'Selesai'), 'selesai');
        $invalidSemesterStudent = $this->createMahasiswa($this->trpl->prodi_id, 'Semester Invalid');
        Magang::withoutEvents(fn () => Magang::create([
            'mahasiswa_id' => $invalidSemesterStudent->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 5,
            'status_magang' => 'berlangsung',
            'role_magang' => 'Invalid fixture',
        ]));
        $admin = $this->createAdmin($this->trpl->prodi_id);

        $this->actingAs($admin)
            ->getJson("/api/jadwal-presentasi/{$jadwal->jadwal_id}/mahasiswa-eligible")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.magang_id', $valid->magang_id);
    }

    public function test_super_admin_prodi_filters_work_and_admin_query_cannot_cross_prodi(): void
    {
        $trplOne = $this->createMahasiswa($this->trpl->prodi_id, 'TRPL Candidate One');
        $trplTwo = $this->createMahasiswa($this->trpl->prodi_id, 'TRPL Candidate Two');
        $trikOne = $this->createMahasiswa($this->trik->prodi_id, 'TRIK Candidate One');
        $this->createMagang($trplOne, 'berlangsung', 6);
        $this->createMagang($trplTwo, 'berlangsung', 6);
        $this->createMagang($trikOne, 'berlangsung', 7);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = $this->createAdmin($this->trpl->prodi_id);

        $this->actingAs($superAdmin)
            ->getJson('/api/mahasiswa')
            ->assertOk()
            ->assertJsonPath('total', 3);
        $this->actingAs($superAdmin)
            ->getJson('/api/magang')
            ->assertOk()
            ->assertJsonPath('total', 3);

        $this->actingAs($superAdmin)
            ->getJson('/api/mahasiswa?prodi_id=' . $this->trpl->prodi_id . '&angkatan=2024&q=TRPL&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('last_page', 2);

        $this->actingAs($superAdmin)
            ->getJson('/api/mahasiswa?prodi_id=' . $this->trik->prodi_id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.prodi_id', $this->trik->prodi_id);

        $this->actingAs($superAdmin)
            ->getJson('/api/magang?prodi_id=' . $this->trpl->prodi_id . '&angkatan=2024&semester_magang=6&q=API&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 2)
            ->assertJsonPath('last_page', 2);

        $this->actingAs($admin)
            ->getJson('/api/mahasiswa?prodi_id=' . $this->trpl->prodi_id)
            ->assertOk()
            ->assertJsonCount(2, 'data');
        $this->actingAs($admin)
            ->getJson('/api/mahasiswa?prodi_id=' . $this->trik->prodi_id)
            ->assertForbidden();
        $this->actingAs($admin)
            ->getJson('/api/magang?prodi_id=' . $this->trik->prodi_id)
            ->assertForbidden();
        $this->actingAs($admin)
            ->getJson('/api/magang?prodi_id=' . $this->trpl->prodi_id . '&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 2);
    }

    public function test_invalid_prodi_filter_returns_validation_error(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)
            ->getJson('/api/mahasiswa?prodi_id=999999')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prodi_id']);
        $this->actingAs($superAdmin)
            ->getJson('/api/magang?prodi_id=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prodi_id']);
    }
}
