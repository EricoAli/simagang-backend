<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Otorisasi & validasi master data Mitra (ADM-MTR).
 *
 * Keputusan yang diacu:
 * - Mitra bersifat global DTEDI (dipakai lintas prodi).
 * - Admin Prodi dan Super Admin boleh mengelola master Mitra.
 * - Admin Prodi tidak boleh mengubah/menghapus Mitra yang dipakai mahasiswa prodi lain.
 * - Mahasiswa, Mitra, dan Dosbing tidak boleh mengelola master Mitra secara administratif.
 */
class MitraTest extends TestCase
{
    use DatabaseTransactions;

    protected $prodiTRPL;
    protected $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();

        // Pengaman: jangan pernah menulis data ke database selain database testing.
        $this->assertStringEndsWith(
            '_testing',
            DB::connection()->getDatabaseName(),
            'Test dihentikan: koneksi tidak mengarah ke database testing.'
        );

        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
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

    private function createMitra(?string $nama = null): array
    {
        $user = $this->createUser('mitra');
        $mitra = Mitra::create([
            'user_id'    => $user->user_id,
            'nama_mitra' => $nama ?? 'PT Uji ' . Str::random(8),
            'email'      => $user->email,
            'narahubung' => 'Narahubung Uji',
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

    private function validPayload(array $overrides = []): array
    {
        $suffix = Str::lower(Str::random(8));
        return array_merge([
            'nama_mitra' => 'PT Baru ' . $suffix,
            'email'      => 'hrd.' . $suffix . '@perusahaan.test',
            'narahubung' => 'Budi - HR',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Autentikasi
    // ------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected()
    {
        $this->getJson('/api/mitra')->assertStatus(401);
        $this->postJson('/api/mitra', $this->validPayload())->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // Mahasiswa / Mitra / Dosbing tidak boleh mengelola master Mitra
    // ------------------------------------------------------------------

    public function test_mahasiswa_cannot_create_mitra()
    {
        [$userMhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $payload = $this->validPayload();

        $this->actingAs($userMhs)->postJson('/api/mitra', $payload)->assertStatus(403);

        $this->assertDatabaseMissing('mitra', ['nama_mitra' => $payload['nama_mitra']]);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_mahasiswa_cannot_update_or_delete_mitra()
    {
        [$userMhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [$userMitra, $mitra] = $this->createMitra('PT Tetap Utuh');

        $this->actingAs($userMhs)
            ->putJson("/api/mitra/{$mitra->mitra_id}", $this->validPayload(['nama_mitra' => 'PT Diretas']))
            ->assertStatus(403);

        $this->actingAs($userMhs)
            ->deleteJson("/api/mitra/{$mitra->mitra_id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitra->mitra_id, 'nama_mitra' => 'PT Tetap Utuh']);
        $this->assertDatabaseHas('users', ['user_id' => $userMitra->user_id]);
    }

    public function test_mitra_and_dosbing_cannot_manage_mitra_master()
    {
        [, $target] = $this->createMitra('PT Target');

        foreach (['mitra', 'dosbing'] as $role) {
            $actor = $role === 'mitra' ? $this->createMitra()[0] : $this->createUser('dosbing');
            $payload = $this->validPayload();

            $this->actingAs($actor)->postJson('/api/mitra', $payload)
                ->assertStatus(403);
            $this->actingAs($actor)->putJson("/api/mitra/{$target->mitra_id}", $this->validPayload(['nama_mitra' => 'PT Diubah']))
                ->assertStatus(403);
            $this->actingAs($actor)->deleteJson("/api/mitra/{$target->mitra_id}")
                ->assertStatus(403);

            $this->assertDatabaseMissing('mitra', ['nama_mitra' => $payload['nama_mitra']]);
        }

        $this->assertDatabaseHas('mitra', ['mitra_id' => $target->mitra_id, 'nama_mitra' => 'PT Target']);
    }

    public function test_mahasiswa_can_list_mitra_without_seeing_other_students_internships()
    {
        [$userA, $mhsA] = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        [, $mhsB] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        [, $mitra] = $this->createMitra('PT Daftar Bersama');
        $this->createMagang($mhsB->mahasiswa_id, $mitra->mitra_id);

        $response = $this->actingAs($userA)->getJson('/api/mitra?per_page=100&q=PT Daftar Bersama');

        $response->assertStatus(200);
        $this->assertContains($mitra->mitra_id, collect($response->json('data'))->pluck('mitra_id')->all());

        $exposedMahasiswaIds = collect($response->json('data'))
            ->flatMap(fn ($item) => collect($item['magang'] ?? [])->pluck('mahasiswa_id'))
            ->all();
        $this->assertNotContains($mhsB->mahasiswa_id, $exposedMahasiswaIds);
    }

    // ------------------------------------------------------------------
    // Admin Prodi & Super Admin
    // ------------------------------------------------------------------

    public function test_admin_and_super_admin_can_list_mitra()
    {
        $this->createMitra();
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $superAdmin = $this->createUser('super_admin');

        $this->actingAs($admin)->getJson('/api/mitra')->assertStatus(200);
        $this->actingAs($superAdmin)->getJson('/api/mitra')->assertStatus(200);
    }

    public function test_admin_can_create_mitra_with_linked_mitra_account()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $payload = $this->validPayload();

        $response = $this->actingAs($admin)->postJson('/api/mitra', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.nama_mitra', $payload['nama_mitra']);

        $mitra = Mitra::where('nama_mitra', $payload['nama_mitra'])->firstOrFail();
        $this->assertDatabaseHas('users', [
            'user_id' => $mitra->user_id,
            'email'   => $payload['email'],
            'role'    => 'mitra',
        ]);
    }

    public function test_super_admin_can_create_update_and_delete_mitra()
    {
        $superAdmin = $this->createUser('super_admin');
        $payload = $this->validPayload();

        $this->actingAs($superAdmin)->postJson('/api/mitra', $payload)->assertStatus(201);
        $mitra = Mitra::where('nama_mitra', $payload['nama_mitra'])->firstOrFail();

        $this->actingAs($superAdmin)
            ->putJson("/api/mitra/{$mitra->mitra_id}", array_merge($payload, ['narahubung' => 'Sari - HR Lead']))
            ->assertStatus(200);
        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitra->mitra_id, 'narahubung' => 'Sari - HR Lead']);

        $this->actingAs($superAdmin)->deleteJson("/api/mitra/{$mitra->mitra_id}")->assertStatus(200);
        $this->assertDatabaseMissing('mitra', ['mitra_id' => $mitra->mitra_id]);
    }

    public function test_admin_cannot_update_mitra_used_by_other_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        [, $mitra] = $this->createMitra('PT Dipakai TRIK');
        $this->createMagang($mhsTRIK->mahasiswa_id, $mitra->mitra_id);

        $this->actingAs($adminTRPL)
            ->putJson("/api/mitra/{$mitra->mitra_id}", $this->validPayload(['nama_mitra' => 'PT Diubah TRPL']))
            ->assertStatus(403);

        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitra->mitra_id, 'nama_mitra' => 'PT Dipakai TRIK']);
    }

    public function test_admin_cannot_delete_mitra_used_by_other_prodi()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        [, $mhsTRIK] = $this->createMahasiswa($this->prodiTRIK->prodi_id);
        [$userMitra, $mitra] = $this->createMitra();
        $magang = $this->createMagang($mhsTRIK->mahasiswa_id, $mitra->mitra_id);

        $this->actingAs($adminTRPL)
            ->deleteJson("/api/mitra/{$mitra->mitra_id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitra->mitra_id]);
        $this->assertDatabaseHas('users', ['user_id' => $userMitra->user_id]);
        $this->assertDatabaseHas('magang', ['magang_id' => $magang->magang_id, 'mitra_id' => $mitra->mitra_id]);
    }

    // ------------------------------------------------------------------
    // Validasi
    // ------------------------------------------------------------------

    public function test_create_rejects_duplicate_nama_mitra_and_email()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [, $existing] = $this->createMitra('PT Sudah Ada');

        $this->actingAs($admin)
            ->postJson('/api/mitra', $this->validPayload(['nama_mitra' => 'PT Sudah Ada']))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->actingAs($admin)
            ->postJson('/api/mitra', $this->validPayload(['email' => $existing->email]))
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame(1, Mitra::where('nama_mitra', 'PT Sudah Ada')->count());
    }

    public function test_create_rejects_email_already_used_by_non_mitra_account()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        [$userMhs] = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        $this->actingAs($admin)
            ->postJson('/api/mitra', $this->validPayload(['email' => $userMhs->email]))
            ->assertStatus(422);

        // Akun mahasiswa tidak boleh "dibajak" menjadi akun Mitra.
        $this->assertDatabaseMissing('mitra', ['user_id' => $userMhs->user_id]);
        $this->assertDatabaseHas('users', ['user_id' => $userMhs->user_id, 'role' => 'mahasiswa']);
    }

    public function test_create_accepts_emails_with_same_local_part_from_different_domains()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $local = 'hrd' . Str::lower(Str::random(6));

        $first = $this->validPayload(['email' => "{$local}@alpha.test"]);
        $second = $this->validPayload(['email' => "{$local}@beta.test"]);

        $this->actingAs($admin)->postJson('/api/mitra', $first)->assertStatus(201);
        $this->actingAs($admin)->postJson('/api/mitra', $second)->assertStatus(201);

        $userIds = Mitra::whereIn('email', [$first['email'], $second['email']])->pluck('user_id')->unique();
        $this->assertCount(2, $userIds);
    }

    public function test_create_rejects_invalid_input()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);

        $invalidPayloads = [
            'nama_mitra kosong'          => $this->validPayload(['nama_mitra' => '']),
            'email kosong'               => $this->validPayload(['email' => '']),
            'format email salah'         => $this->validPayload(['email' => 'bukan-email']),
            // Kolom mitra.nama_mitra varchar(150)
            'nama_mitra melebihi kolom'  => $this->validPayload(['nama_mitra' => str_repeat('A', 151)]),
            // Kolom mitra.narahubung varchar(100)
            'narahubung melebihi kolom'  => $this->validPayload(['narahubung' => str_repeat('B', 101)]),
        ];

        foreach ($invalidPayloads as $case => $payload) {
            $response = $this->actingAs($admin)->postJson('/api/mitra', $payload);
            $this->assertSame(422, $response->status(), "Kasus '{$case}' seharusnya 422, didapat {$response->status()}.");
        }
    }

    public function test_update_rejects_email_owned_by_another_mitra()
    {
        $superAdmin = $this->createUser('super_admin');
        [, $mitraA] = $this->createMitra();
        [, $mitraB] = $this->createMitra();

        $this->actingAs($superAdmin)
            ->putJson("/api/mitra/{$mitraA->mitra_id}", [
                'nama_mitra' => $mitraA->nama_mitra,
                'email'      => $mitraB->email,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('mitra', ['mitra_id' => $mitraA->mitra_id, 'email' => $mitraA->email]);
    }
}
