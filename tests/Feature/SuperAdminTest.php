<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\ProdiSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiTRPL;
    protected $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([ProdiSeeder::class, SuperAdminSeeder::class]);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function getSuperAdmin(): User
    {
        return User::where('role', 'super_admin')->first();
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        Admin::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nama'     => 'Admin ' . rand(100, 999),
        ]);
        return $user;
    }

    private function createMahasiswa(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $prodiId,
            'nim'      => '24/' . rand(10000, 99999) . '/SV/' . rand(1000, 9999),
            'nama'     => 'Mahasiswa ' . rand(100, 999),
            'angkatan' => 2024,
        ]);
        return $user;
    }

    // 1. Super Admin dapat login dengan akun yang diprovisikan secara sah
    public function test_super_admin_can_login_with_provisioned_account()
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'superadmin',
            'password' => 'SuperAdmin#DTEDI2026!',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'access_token',
            'role',
            'user',
            'token_type',
            'expires_at',
        ]);
        $this->assertEquals('super_admin', $response->json('role'));
    }

    // 2. Registrasi publik tidak dapat membuat role super_admin
    public function test_public_registration_cannot_create_super_admin_or_admin()
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Hacker',
            'username'              => 'hacker123',
            'email'                 => 'hacker@example.com',
            'password'              => 'secret123',
            'password_confirmation' => 'secret123',
            'role'                  => 'super_admin',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['username' => 'hacker123']);
    }

    // 3. Admin Prodi dan Mahasiswa ditolak saat mengakses route Super Admin
    public function test_admin_prodi_and_mahasiswa_forbidden_from_super_admin_routes()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $mhs = $this->createMahasiswa($this->prodiTRPL->prodi_id);

        // Admin Prodi access -> 403
        $resAdmin = $this->actingAs($admin)->getJson('/api/super-admin/dashboard');
        $resAdmin->assertStatus(403);

        $resAdminList = $this->actingAs($admin)->getJson('/api/super-admin/admin-prodi');
        $resAdminList->assertStatus(403);

        // Mahasiswa access -> 403
        $resMhs = $this->actingAs($mhs)->getJson('/api/super-admin/dashboard');
        $resMhs->assertStatus(403);

        $resMhsList = $this->actingAs($mhs)->getJson('/api/super-admin/admin-prodi');
        $resMhsList->assertStatus(403);
    }

    // 4. Akun nonaktif tidak dapat login dan tidak dapat memakai token lama
    public function test_deactivated_account_cannot_login_and_cannot_use_existing_token()
    {
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $admin->password = Hash::make('password123');
        $admin->save();

        // Buat token saat masih aktif
        $token = $admin->createToken('test-token')->plainTextToken;

        // Nonaktifkan user
        $admin->is_active = false;
        $admin->save();

        // 1. Coba login saat nonaktif -> 403
        $resLogin = $this->postJson('/api/auth/login', [
            'username' => $admin->username,
            'password' => 'password123',
        ]);
        $resLogin->assertStatus(403);

        // 2. Coba pakai token lama saat nonaktif -> 403
        $resReq = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/mahasiswa');
        $resReq->assertStatus(403);
    }

    // 5. Super Admin dapat membuat Admin Prodi dengan prodi_id valid
    public function test_super_admin_can_create_admin_prodi_with_valid_prodi_id()
    {
        $superAdmin = $this->getSuperAdmin();

        $response = $this->actingAs($superAdmin)->postJson('/api/super-admin/admin-prodi', [
            'username' => 'new_admin_trpl',
            'email'    => 'newadmin@trpl.dev',
            'password' => 'trplpass123',
            'nama'     => 'Koordinator Magang TRPL',
            'prodi_id' => $this->prodiTRPL->prodi_id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'username'  => 'new_admin_trpl',
            'email'     => 'newadmin@trpl.dev',
            'role'      => 'admin',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('data_admin', [
            'prodi_id' => $this->prodiTRPL->prodi_id,
            'nama'     => 'Koordinator Magang TRPL',
        ]);
    }

    // 6. prodi_id tidak valid ditolak
    public function test_create_admin_prodi_fails_if_prodi_id_invalid()
    {
        $superAdmin = $this->getSuperAdmin();

        $response = $this->actingAs($superAdmin)->postJson('/api/super-admin/admin-prodi', [
            'username' => 'invalid_prodi_admin',
            'email'    => 'invalid@prodi.dev',
            'password' => 'password123',
            'nama'     => 'Admin Invalid',
            'prodi_id' => 99999, // Tidak ada
        ]);

        $response->assertStatus(422);
    }

    // 7. Password disimpan sebagai hash
    public function test_password_is_saved_as_hash_and_not_plain()
    {
        $superAdmin = $this->getSuperAdmin();

        $this->actingAs($superAdmin)->postJson('/api/super-admin/admin-prodi', [
            'username' => 'hash_check_admin',
            'email'    => 'hashcheck@admin.dev',
            'password' => 'myplainpassword',
            'nama'     => 'Admin Hash Check',
            'prodi_id' => $this->prodiTRPL->prodi_id,
        ]);

        $user = User::where('username', 'hash_check_admin')->first();
        $this->assertNotEquals('myplainpassword', $user->password);
        $this->assertTrue(Hash::check('myplainpassword', $user->password));
    }

    // 8. Informasi sensitif tidak dikembalikan oleh API
    public function test_sensitive_attributes_are_hidden_in_api_responses()
    {
        $superAdmin = $this->getSuperAdmin();

        $response = $this->actingAs($superAdmin)->getJson('/api/super-admin/admin-prodi');
        $response->assertStatus(200);

        $content = $response->getContent();
        $this->assertStringNotContainsString('password', $content);
        $this->assertStringNotContainsString('tokens', $content);
    }

    // 9. Super Admin dapat mengaktifkan dan menonaktifkan akun Admin Prodi
    public function test_super_admin_can_toggle_admin_prodi_active_status()
    {
        $superAdmin = $this->getSuperAdmin();
        $admin = $this->createAdmin($this->prodiTRPL->prodi_id);
        $adminRecord = Admin::where('user_id', $admin->user_id)->first();

        // 1. Nonaktifkan
        $resDeactivate = $this->actingAs($superAdmin)->patchJson("/api/super-admin/admin-prodi/{$adminRecord->admin_id}/status", [
            'is_active' => false,
        ]);
        $resDeactivate->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'user_id'   => $admin->user_id,
            'is_active' => false,
        ]);

        // 2. Aktifkan kembali
        $resActivate = $this->actingAs($superAdmin)->patchJson("/api/super-admin/admin-prodi/{$adminRecord->admin_id}/status", [
            'is_active' => true,
        ]);
        $resActivate->assertStatus(200);
        $this->assertDatabaseHas('users', [
            'user_id'   => $admin->user_id,
            'is_active' => true,
        ]);
    }

    // 10. Dashboard tanpa filter mengembalikan agregat yang benar
    public function test_super_admin_dashboard_without_filter_returns_correct_aggregate()
    {
        $superAdmin = $this->getSuperAdmin();

        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRIK->prodi_id);

        $response = $this->actingAs($superAdmin)->getJson('/api/super-admin/dashboard');

        $response->assertStatus(200);
        $response->assertJson([
            'mode' => 'agregat',
            'statistik_keseluruhan' => [
                'total_mahasiswa' => 3,
            ],
        ]);
        $this->assertCount(4, $response->json('breakdown_per_prodi'));
    }

    // 11. Dashboard dengan filter mengembalikan data prodi yang benar
    public function test_super_admin_dashboard_with_filter_returns_scoped_prodi_data()
    {
        $superAdmin = $this->getSuperAdmin();

        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRPL->prodi_id);
        $this->createMahasiswa($this->prodiTRIK->prodi_id);

        // Filter TRPL
        $resTRPL = $this->actingAs($superAdmin)->getJson("/api/super-admin/dashboard?prodi_id={$this->prodiTRPL->prodi_id}");
        $resTRPL->assertStatus(200);
        $this->assertEquals('filter', $resTRPL->json('mode'));
        $this->assertEquals(2, $resTRPL->json('statistik.jumlah_mahasiswa'));

        // Filter TRIK
        $resTRIK = $this->actingAs($superAdmin)->getJson("/api/super-admin/dashboard?prodi_id={$this->prodiTRIK->prodi_id}");
        $resTRIK->assertStatus(200);
        $this->assertEquals(1, $resTRIK->json('statistik.jumlah_mahasiswa'));
    }

    // 12. Role sys tidak memiliki universal bypass dan ditolak pada route super_admin
    public function test_sys_role_cannot_bypass_super_admin_routes()
    {
        $sysUser = new User([
            'username'  => 'sysuser',
            'email'     => 'sys@simagang.dev',
            'role'      => 'sys',
            'is_active' => true,
        ]);
        $sysUser->user_id = 99999;

        $response = $this->actingAs($sysUser)->getJson('/api/super-admin/dashboard');
        $response->assertStatus(403);

        $responseAdminProdi = $this->actingAs($sysUser)->getJson('/api/super-admin/admin-prodi');
        $responseAdminProdi->assertStatus(403);
    }

    // 13. Super Admin hanya memperoleh akses yang diberikan secara eksplisit
    public function test_super_admin_cannot_access_routes_scoped_to_other_roles_without_permission()
    {
        $superAdmin = $this->getSuperAdmin();

        // Route yang dibatasi khusus role:admin (misal import mahasiswa) ditolak untuk super_admin
        $response = $this->actingAs($superAdmin)->postJson('/api/mahasiswa/import');
        $response->assertStatus(403);
    }
}
