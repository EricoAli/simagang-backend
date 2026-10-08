<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Admin;
use App\Models\Prodi;
use App\Models\Mahasiswa;

class MahasiswaProdiTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiTRPL;
    protected $prodiTRIK;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function createAdmin($prodi_id)
    {
        $user = User::factory()->create(['role' => 'admin']);
        $admin = Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi_id,
            'nama' => 'Admin Test'
        ]);
        return $user;
    }

    private function createMahasiswa($prodi_id)
    {
        $user = User::factory()->create(['role' => 'mahasiswa']);
        return Mahasiswa::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodi_id,
            'nim' => '24/12345/SV/5432' . rand(1, 99),
            'nama' => 'Mahasiswa Test',
            'angkatan' => 2024
        ]);
    }

    public function test_admin_trpl_has_trpl_relation_after_seeding()
    {
        $this->seed([\Database\Seeders\ProdiSeeder::class, \Database\Seeders\AdminSeeder::class]);
        
        $admin = User::where('username', 'admin_trpl')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('TRPL', $admin->admin->prodi->kode_prodi);
    }

    public function test_admin_trik_has_trik_relation_after_seeding()
    {
        $this->seed([\Database\Seeders\ProdiSeeder::class, \Database\Seeders\AdminSeeder::class]);
        
        $admin = User::where('username', 'admin_trik')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('TRIK', $admin->admin->prodi->kode_prodi);
    }

    public function test_admin_trpl_create_mahasiswa_defaults_to_trpl()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        $response = $this->actingAs($adminTRPL)->postJson('/api/mahasiswa', [
            'nim' => '24/12345/SV/54321',
            'nama' => 'Mahasiswa TRPL',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '24/12345/SV/54321',
            'prodi_id' => $this->prodiTRPL->prodi_id
        ]);
    }

    public function test_admin_trik_create_mahasiswa_defaults_to_trik()
    {
        $adminTRIK = $this->createAdmin($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRIK)->postJson('/api/mahasiswa', [
            'nim' => '24/12345/SV/54322',
            'nama' => 'Mahasiswa TRIK',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '24/12345/SV/54322',
            'prodi_id' => $this->prodiTRIK->prodi_id
        ]);
    }

    public function test_client_sending_prodi_id_is_ignored()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        $response = $this->actingAs($adminTRPL)->postJson('/api/mahasiswa', [
            'nim' => '24/12345/SV/54323',
            'nama' => 'Mahasiswa TRPL',
            'prodi_id' => $this->prodiTRIK->prodi_id // Attempt to bypass
        ]);

        $response->assertStatus(201);
        // Should still be TRPL
        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '24/12345/SV/54323',
            'prodi_id' => $this->prodiTRPL->prodi_id
        ]);
    }

    public function test_admin_trpl_get_mahasiswa_only_returns_trpl()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        
        $mhsTRPL = $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $mhsTRIK = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRPL)->getJson('/api/mahasiswa');

        $response->assertStatus(200);
        
        // Assert TRPL is in response data, TRIK is not
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($mhsTRPL->mahasiswa_id, $data[0]['mahasiswa_id']);
    }

    public function test_admin_trpl_cannot_view_detail_mahasiswa_trik()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $mhsTRIK = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRPL)->getJson('/api/mahasiswa/' . $mhsTRIK->mahasiswa_id);

        $response->assertStatus(403);
    }

    public function test_admin_trpl_cannot_update_mahasiswa_trik()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $mhsTRIK = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRPL)->putJson('/api/mahasiswa/update', [
            'mahasiswa_id' => $mhsTRIK->mahasiswa_id,
            'nim' => '24/11111/SV/11111',
            'nama' => 'Hacked',
        ]);

        // Actually PUT should be to /update or just use POST /update based on routes
        // The existing route is POST /api/mahasiswa/update
        $response = $this->actingAs($adminTRPL)->postJson('/api/mahasiswa/update', [
            'mahasiswa_id' => $mhsTRIK->mahasiswa_id,
            'nim' => '24/11111/SV/11111',
            'nama' => 'Hacked',
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_trpl_cannot_delete_mahasiswa_trik()
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);
        $mhsTRIK = $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($adminTRPL)->deleteJson('/api/mahasiswa/' . $mhsTRIK->mahasiswa_id);

        $response->assertStatus(403);
    }
}
