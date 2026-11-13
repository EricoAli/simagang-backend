<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validasi NIM/nama pada store dan update, serta pengaman parameter
 * sorting/paginasi pada index. Memakai prodi TRPL yang di-seed oleh migration.
 */
class MahasiswaValidationTest extends TestCase
{
    use RefreshDatabase;

    private Prodi $prodi;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();

        $this->admin = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $this->admin->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nama' => 'Admin Test',
        ]);
    }

    private function createMahasiswa(string $nim, string $nama = 'Mahasiswa Test'): Mahasiswa
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);

        return Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $this->prodi->prodi_id,
            'nim' => $nim,
            'nama' => $nama,
            'angkatan' => 2024,
        ]);
    }

    public function test_update_rejects_nim_owned_by_another_mahasiswa()
    {
        $a = $this->createMahasiswa('24/10001/SV/10001', 'Mahasiswa A');
        $b = $this->createMahasiswa('24/10002/SV/10002', 'Mahasiswa B');

        $response = $this->actingAs($this->admin)->postJson('/api/mahasiswa/update', [
            'mahasiswa_id' => $b->mahasiswa_id,
            'nim' => $a->nim,
            'nama' => 'Mahasiswa B',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('nim');
        $this->assertDatabaseHas('mahasiswa', [
            'mahasiswa_id' => $b->mahasiswa_id,
            'nim' => '24/10002/SV/10002',
        ]);
    }

    public function test_update_allows_keeping_own_nim()
    {
        $m = $this->createMahasiswa('24/10003/SV/10003', 'Nama Lama');

        $response = $this->actingAs($this->admin)->postJson('/api/mahasiswa/update', [
            'mahasiswa_id' => $m->mahasiswa_id,
            'nim' => $m->nim,
            'nama' => 'Nama Baru',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('mahasiswa', [
            'mahasiswa_id' => $m->mahasiswa_id,
            'nim' => '24/10003/SV/10003',
            'nama' => 'Nama Baru',
        ]);
    }

    public function test_update_rejects_nama_longer_than_column()
    {
        $m = $this->createMahasiswa('24/10007/SV/10007', 'Nama Asli');

        $response = $this->actingAs($this->admin)->postJson('/api/mahasiswa/update', [
            'mahasiswa_id' => $m->mahasiswa_id,
            'nim' => $m->nim,
            'nama' => str_repeat('a', 101),
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('nama');
        $this->assertDatabaseHas('mahasiswa', [
            'mahasiswa_id' => $m->mahasiswa_id,
            'nama' => 'Nama Asli',
        ]);
    }

    public function test_store_rejects_nim_longer_than_column_without_creating_user()
    {
        $response = $this->actingAs($this->admin)->postJson('/api/mahasiswa', [
            'nim' => '24/123456789012/SV/54321',
            'nama' => 'Mahasiswa NIM Panjang',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('mahasiswa', ['nama' => 'Mahasiswa NIM Panjang']);
        $this->assertDatabaseMissing('users', ['username' => '123456789012']);
    }

    public function test_index_ignores_invalid_sort_and_page_parameters()
    {
        $this->createMahasiswa('24/10004/SV/10004');

        $queries = [
            ['sort_by' => 'kolom_tidak_ada'],
            ['sort_order' => 'menyamping'],
            ['per_page' => 0],
            ['sort_by' => ['x'], 'sort_order' => ['y']],
        ];

        foreach ($queries as $query) {
            $this->actingAs($this->admin)
                ->getJson('/api/mahasiswa?' . http_build_query($query))
                ->assertStatus(200);
        }
    }

    public function test_index_still_honours_valid_sort_parameters()
    {
        $this->createMahasiswa('24/10006/SV/10006');
        $this->createMahasiswa('24/10005/SV/10005');

        $response = $this->actingAs($this->admin)
            ->getJson('/api/mahasiswa?sort_by=nim&sort_order=ASC');

        $response->assertStatus(200);
        $this->assertSame(
            ['24/10005/SV/10005', '24/10006/SV/10006'],
            array_column($response->json('data'), 'nim')
        );
    }
}
