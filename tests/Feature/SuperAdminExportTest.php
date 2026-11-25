<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminExportTest extends TestCase
{
    use RefreshDatabase;

    protected $prodiTRPL;
    protected $prodiTRIK;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([\Database\Seeders\ProdiSeeder::class]);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
        $this->prodiTRIK = Prodi::where('kode_prodi', 'TRIK')->first();
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'role' => 'super_admin',
            'username' => 'superadmin_test',
        ]);
    }

    private function createAdmin(int $prodiId): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $user->user_id,
            'prodi_id' => $prodiId,
            'nama' => 'Admin Test',
        ]);
        return $user;
    }

    public function test_super_admin_export_without_prodi_id_succeeds(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get('/api/magang/export/excel');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_super_admin_export_with_valid_prodi_id_succeeds(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get('/api/magang/export/excel?prodi_id=' . $this->prodiTRPL->prodi_id);

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_super_admin_export_with_invalid_prodi_id_returns_422(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->getJson('/api/magang/export/excel?prodi_id=99999');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['prodi_id']);
    }

    public function test_super_admin_export_with_non_integer_prodi_id_returns_422(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->getJson('/api/magang/export/excel?prodi_id=invalid_text');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['prodi_id']);
    }

    public function test_admin_prodi_ignores_query_prodi_id_and_exports_own_prodi(): void
    {
        $adminTRPL = $this->createAdmin($this->prodiTRPL->prodi_id);

        // Admin TRPL attempts to pass prodi_id of TRIK in query
        $response = $this->actingAs($adminTRPL)->get('/api/magang/export/excel?prodi_id=' . $this->prodiTRIK->prodi_id);

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_admin_without_prodi_is_forbidden_from_export(): void
    {
        $adminWithoutProdi = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($adminWithoutProdi)->get('/api/magang/export/excel');

        $response->assertStatus(403);
    }

    public function test_dosbing_is_forbidden_from_export(): void
    {
        $dosbing = User::factory()->create(['role' => 'dosbing']);

        $response = $this->actingAs($dosbing)->get('/api/magang/export/excel');

        $response->assertStatus(403);
    }
}
