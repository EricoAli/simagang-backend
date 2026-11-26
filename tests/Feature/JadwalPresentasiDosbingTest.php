<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Dosbing;
use App\Models\JadwalPresentasi;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPresentasiDosbingTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $trpl;
    private Prodi $trik;
    private int $nimCounter = 20000;
    private int $nipCounter = 198101012010011001;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $this->trpl = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $this->trik = Prodi::where('kode_prodi', 'TRIK')->firstOrFail();
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

    private function createDosbing(Prodi $prodi, ?string $username = null): array
    {
        $user = User::factory()->create([
            'role' => 'dosbing',
            'username' => $username ?? ('dosbing' . $this->nipCounter),
        ]);

        $dosbing = Dosbing::create([
            'user_id' => $user->user_id,
            'nip' => (string) $this->nipCounter++,
            'nama' => 'Dosen ' . $prodi->kode_prodi,
            'prodi_id' => $prodi->prodi_id,
        ]);

        return [$user, $dosbing];
    }

    private function createMahasiswa(Prodi $prodi, string $nama): Mahasiswa
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);

        return Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => sprintf('24/20000/SV/%05d', $this->nimCounter++),
            'nama' => $nama,
            'angkatan' => '2024',
        ]);
    }

    private function createMagang(
        Mahasiswa $mahasiswa,
        ?Dosbing $dosbing = null,
        string $status = 'berlangsung',
        int $semester = 6
    ): Magang {
        return Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'dosbing_id' => $dosbing?->dosbing_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => $semester,
            'status_magang' => $status,
            'role_magang' => 'Software Engineer',
            'jobdesk' => 'Fixture presentasi',
        ]);
    }

    private function payload(Dosbing $examiner, ?Magang $magang = null, array $overrides = []): array
    {
        return array_merge([
            'magang_id' => $magang?->magang_id,
            'dosen_penguji_id' => $examiner->dosbing_id,
            'tanggal_presentasi' => '2027-01-15',
            'waktu_mulai' => '09:00',
            'waktu_selesai' => '10:30',
            'ruangan' => 'CU207',
            'keterangan' => 'Presentasi PI',
        ], $overrides);
    }

    private function createScheduleAs(User $user, Dosbing $examiner, ?Magang $magang = null, array $overrides = []): JadwalPresentasi
    {
        $response = $this->actingAs($user)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($examiner, $magang, $overrides)
        )->assertCreated();

        return JadwalPresentasi::findOrFail($response->json('data.jadwal_id'));
    }

    public function test_dosbing_can_create_schedule_for_own_advisee(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        $mahasiswa = $this->createMahasiswa($this->trpl, 'Mahasiswa Bimbingan');
        $magang = $this->createMagang($mahasiswa, $dosbing);

        $response = $this->actingAs($dosbingUser)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang)
        );

        $response->assertCreated()
            ->assertJsonPath('data.peserta_count', 1)
            ->assertJsonPath('data.punya_peserta', true);

        $jadwalId = $response->json('data.jadwal_id');

        $this->assertDatabaseHas('jadwal_presentasi', [
            'jadwal_id' => $jadwalId,
            'dosen_penguji_id' => $dosbing->dosbing_id,
        ]);
        $this->assertDatabaseHas('jadwal_peserta', [
            'jadwal_id' => $jadwalId,
            'magang_id' => $magang->magang_id,
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
        ]);
    }

    public function test_dosbing_cannot_schedule_other_dosbings_advisee(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        [, $otherDosbing] = $this->createDosbing($this->trpl);
        $otherMagang = $this->createMagang(
            $this->createMahasiswa($this->trpl, 'Mahasiswa Dosen Lain'),
            $otherDosbing
        );

        $this->actingAs($dosbingUser)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $otherMagang)
        )->assertForbidden();

        $this->assertDatabaseCount('jadwal_presentasi', 0);
    }

    public function test_dosbing_can_only_edit_and_delete_own_students_schedule(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        [, $otherDosbing] = $this->createDosbing($this->trpl);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $otherMagang = $this->createMagang(
            $this->createMahasiswa($this->trpl, 'Mahasiswa Lain'),
            $otherDosbing
        );
        $foreignSchedule = $this->createScheduleAs($superAdmin, $otherDosbing, $otherMagang);

        $this->actingAs($dosbingUser)
            ->putJson("/api/jadwal-presentasi/{$foreignSchedule->jadwal_id}", $this->payload($dosbing, null, [
                'ruangan' => 'CU208',
            ]))
            ->assertForbidden();

        $this->actingAs($dosbingUser)
            ->deleteJson("/api/jadwal-presentasi/{$foreignSchedule->jadwal_id}")
            ->assertForbidden();
    }

    public function test_dosbing_advisee_list_contains_only_own_students_and_scheduling_status(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        [, $otherDosbing] = $this->createDosbing($this->trpl);
        $ownMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'Bimbingan Sendiri'), $dosbing);
        $this->createMagang($this->createMahasiswa($this->trpl, 'Bimbingan Lain'), $otherDosbing);

        $response = $this->actingAs($dosbingUser)
            ->getJson('/api/jadwal-presentasi/advisees')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.magang_id', $ownMagang->magang_id)
            ->assertJsonPath('data.0.status_penjadwalan', 'belum_dijadwalkan')
            ->assertJsonPath('data.0.bisa_dijadwalkan', true);

        $this->assertSame('Bimbingan Sendiri', $response->json('data.0.nama'));

        $this->createScheduleAs($dosbingUser, $dosbing, $ownMagang);

        $this->actingAs($dosbingUser)
            ->getJson('/api/jadwal-presentasi/advisees')
            ->assertOk()
            ->assertJsonPath('data.0.status_penjadwalan', 'sudah_dijadwalkan')
            ->assertJsonPath('data.0.bisa_dijadwalkan', false);
    }

    public function test_dosbing_only_lists_schedules_for_own_advisees(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        [, $otherDosbing] = $this->createDosbing($this->trpl);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $ownMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'Sendiri'), $dosbing);
        $otherMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'Lain'), $otherDosbing);

        $ownSchedule = $this->createScheduleAs($dosbingUser, $dosbing, $ownMagang);
        $this->createScheduleAs($superAdmin, $otherDosbing, $otherMagang, [
            'tanggal_presentasi' => '2027-01-16',
        ]);

        $this->actingAs($dosbingUser)
            ->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.jadwal_id', $ownSchedule->jadwal_id);
    }

    public function test_admin_prodi_can_manage_only_own_prodi_schedule(): void
    {
        $admin = $this->createAdmin($this->trpl);
        [, $trplDosbing] = $this->createDosbing($this->trpl);
        [, $trikDosbing] = $this->createDosbing($this->trik);
        $trplMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'TRPL'), $trplDosbing);
        $trikMagang = $this->createMagang($this->createMahasiswa($this->trik, 'TRIK'), $trikDosbing);

        $this->actingAs($admin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($trplDosbing, $trplMagang)
        )->assertCreated();

        $this->actingAs($admin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($trikDosbing, $trplMagang, ['ruangan' => 'CU208'])
        )->assertForbidden();

        $this->actingAs($admin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($trplDosbing, $trikMagang, ['ruangan' => 'CU209'])
        )->assertForbidden();
    }

    public function test_super_admin_can_manage_schedule_across_prodi(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        [, $trikDosbing] = $this->createDosbing($this->trik);
        $trikMagang = $this->createMagang($this->createMahasiswa($this->trik, 'TRIK'), $trikDosbing);

        $this->actingAs($superAdmin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($trikDosbing, $trikMagang)
        )->assertCreated();

        $this->assertDatabaseHas('jadwal_peserta', ['magang_id' => $trikMagang->magang_id]);
    }

    public function test_mahasiswa_and_mitra_cannot_manage_schedules(): void
    {
        [, $dosbing] = $this->createDosbing($this->trpl);
        $mahasiswa = $this->createMahasiswa($this->trpl, 'Mahasiswa Peserta');
        $magang = $this->createMagang($mahasiswa, $dosbing);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $schedule = $this->createScheduleAs($superAdmin, $dosbing, $magang);

        $this->actingAs($mahasiswa->user)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang, ['ruangan' => 'CU210'])
        )->assertForbidden();

        $this->actingAs($mahasiswa->user)
            ->putJson("/api/jadwal-presentasi/{$schedule->jadwal_id}", ['ruangan' => 'CU211'])
            ->assertForbidden();

        $this->actingAs($mahasiswa->user)
            ->deleteJson("/api/jadwal-presentasi/{$schedule->jadwal_id}")
            ->assertForbidden();

        $mitraUser = User::factory()->create(['role' => 'mitra']);

        $this->actingAs($mitraUser)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, null, ['ruangan' => 'CU212'])
        )->assertForbidden();

        $this->actingAs($mitraUser)
            ->getJson('/api/jadwal-presentasi')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_required_schedule_fields_are_validated(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        $magang = $this->createMagang($this->createMahasiswa($this->trpl, 'Validasi'), $dosbing);

        $this->actingAs($dosbingUser)->postJson('/api/jadwal-presentasi', [
            'magang_id' => $magang->magang_id,
            'dosen_penguji_id' => $dosbing->dosbing_id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['tanggal_presentasi', 'waktu_mulai', 'waktu_selesai', 'ruangan']);

        $this->actingAs($dosbingUser)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang, ['waktu_selesai' => '08:00'])
        )->assertUnprocessable()
            ->assertJsonValidationErrors(['waktu_selesai']);
    }

    public function test_duplicate_active_schedule_for_same_student_is_rejected(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        $magang = $this->createMagang($this->createMahasiswa($this->trpl, 'Ganda'), $dosbing);

        $this->createScheduleAs($dosbingUser, $dosbing, $magang);

        $this->actingAs($dosbingUser)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang, [
                'tanggal_presentasi' => '2027-01-20',
                'ruangan' => 'CU300',
            ])
        )->assertUnprocessable()
            ->assertJsonPath('message', 'Mahasiswa sudah memiliki jadwal presentasi aktif pada sesi lain.');

        $this->assertDatabaseCount('jadwal_presentasi', 1);
    }

    public function test_schedule_conflict_on_same_room_or_examiner_is_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        [, $dosbing] = $this->createDosbing($this->trpl);
        $firstMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'Pertama'), $dosbing);
        $secondMagang = $this->createMagang($this->createMahasiswa($this->trpl, 'Kedua'), $dosbing);

        $this->createScheduleAs($superAdmin, $dosbing, $firstMagang);

        $this->actingAs($superAdmin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $secondMagang, ['waktu_mulai' => '10:00', 'waktu_selesai' => '11:00'])
        )->assertUnprocessable()
            ->assertJsonPath('errors.jadwal.0', fn ($value) => str_contains($value, 'Bentrok'));

        $this->actingAs($superAdmin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $secondMagang, [
                'ruangan' => 'CU999',
                'waktu_mulai' => '09:30',
                'waktu_selesai' => '10:30',
            ])
        )->assertUnprocessable();

        $this->actingAs($superAdmin)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $secondMagang, [
                'ruangan' => 'CU999',
                'waktu_mulai' => '10:30',
                'waktu_selesai' => '12:00',
            ])
        )->assertCreated();
    }

    public function test_edit_keeps_participants_and_session_with_participants_cannot_be_deleted(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        $mahasiswa = $this->createMahasiswa($this->trpl, 'Integritas');
        $magang = $this->createMagang($mahasiswa, $dosbing);
        $schedule = $this->createScheduleAs($dosbingUser, $dosbing, $magang);

        $this->actingAs($dosbingUser)
            ->putJson("/api/jadwal-presentasi/{$schedule->jadwal_id}", [
                'ruangan' => 'CU500',
                'waktu_mulai' => '13:00',
                'waktu_selesai' => '14:30',
                'keterangan' => 'Jadwal diubah',
            ])
            ->assertOk()
            ->assertJsonPath('data.ruangan', 'CU500');

        $this->assertDatabaseHas('jadwal_peserta', [
            'jadwal_id' => $schedule->jadwal_id,
            'magang_id' => $magang->magang_id,
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
        ]);

        $this->actingAs($dosbingUser)
            ->deleteJson("/api/jadwal-presentasi/{$schedule->jadwal_id}")
            ->assertUnprocessable();

        $this->actingAs($dosbingUser)
            ->deleteJson("/api/jadwal-presentasi/{$schedule->jadwal_id}/peserta/{$magang->magang_id}")
            ->assertOk();

        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id]);
        $this->assertDatabaseHas('mahasiswa', ['mahasiswa_id' => $mahasiswa->mahasiswa_id]);
        $this->assertDatabaseMissing('jadwal_peserta', ['jadwal_id' => $schedule->jadwal_id]);

        $this->actingAs($dosbingUser)
            ->deleteJson("/api/jadwal-presentasi/{$schedule->jadwal_id}")
            ->assertOk();
    }

    public function test_adding_student_to_another_active_session_is_rejected(): void
    {
        [$dosbingUser, $dosbing] = $this->createDosbing($this->trpl);
        $magang = $this->createMagang($this->createMahasiswa($this->trpl, 'Peserta Aktif'), $dosbing);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->createScheduleAs($dosbingUser, $dosbing, $magang);

        $emptySchedule = $this->createScheduleAs($superAdmin, $dosbing, null, [
            'tanggal_presentasi' => '2027-01-25',
            'ruangan' => 'CU700',
        ]);

        $this->actingAs($superAdmin)->postJson(
            "/api/jadwal-presentasi/{$emptySchedule->jadwal_id}/peserta",
            ['magang_id' => $magang->magang_id]
        )->assertUnprocessable()
            ->assertJsonPath('message', 'Mahasiswa sudah memiliki jadwal presentasi aktif pada sesi lain.');
    }

    public function test_legacy_dosbing_account_with_username_equal_to_nip_is_supported(): void
    {
        $nip = (string) $this->nipCounter++;
        $user = User::factory()->create([
            'role' => 'dosbing',
            'username' => $nip,
        ]);
        $dosbing = Dosbing::create([
            'nip' => $nip,
            'nama' => 'Dosen Legacy',
            'prodi_id' => $this->trpl->prodi_id,
        ]);
        $magang = $this->createMagang($this->createMahasiswa($this->trpl, 'Legacy'), $dosbing);

        $this->actingAs($user)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang)
        )->assertCreated();

        $this->actingAs($user)
            ->getJson('/api/jadwal-presentasi/advisees')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_dosbing_without_link_is_forbidden(): void
    {
        $unlinked = User::factory()->create(['role' => 'dosbing']);
        $dosbing = Dosbing::create([
            'nip' => (string) $this->nipCounter++,
            'nama' => 'Dosen Tanpa Akun',
            'prodi_id' => $this->trpl->prodi_id,
        ]);
        $magang = $this->createMagang($this->createMahasiswa($this->trpl, 'Tanpa Tautan'));

        $this->actingAs($unlinked)
            ->getJson('/api/jadwal-presentasi/advisees')
            ->assertForbidden();

        $this->actingAs($unlinked)->postJson(
            '/api/jadwal-presentasi',
            $this->payload($dosbing, $magang)
        )->assertForbidden();
    }
}
