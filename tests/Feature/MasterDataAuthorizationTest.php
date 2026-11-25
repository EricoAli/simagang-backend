<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mahasiswa_mitra_and_dosbing_cannot_mutate_mitra_or_dosbing(): void
    {
        foreach (['mahasiswa', 'mitra', 'dosbing'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);

            $this->postJson('/api/mitra', [
                'nama_mitra' => "Mitra {$role}",
                'email' => "mitra-{$role}@example.test",
            ])->assertForbidden();

            $this->putJson('/api/mitra/999999', [
                'nama_mitra' => "Mitra {$role} Updated",
                'email' => "mitra-{$role}@example.test",
            ])->assertForbidden();

            $this->deleteJson('/api/mitra/999999')->assertForbidden();

            $this->postJson('/api/dosbing', [
                'nip' => '198001012010010001',
                'nama' => "Dosen {$role}",
            ])->assertForbidden();

            $this->putJson('/api/dosbing/999999', [
                'nip' => '198001012010010001',
                'nama' => "Dosen {$role} Updated",
            ])->assertForbidden();

            $this->deleteJson('/api/dosbing/999999')->assertForbidden();

            // Read access remains available because both catalogs feed forms
            // used by these roles.
            $this->getJson('/api/mitra')->assertOk();
            $this->getJson('/api/dosbing')->assertOk();
        }
    }

    public function test_admin_prodi_and_super_admin_can_manage_shared_master_catalogs(): void
    {
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $prodi = Prodi::firstOrFail();

        $admin = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $admin->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nama' => 'Admin Uji',
        ]);

        foreach ([$admin, User::factory()->create(['role' => 'super_admin'])] as $manager) {
            $this->actingAs($manager);
            $suffix = $manager->role;

            $mitraResponse = $this->postJson('/api/mitra', [
                'nama_mitra' => "Mitra Uji {$suffix}",
                'email' => "mitra-{$suffix}@example.test",
            ])->assertCreated();
            $mitraId = $mitraResponse->json('data.mitra_id');

            $this->putJson("/api/mitra/{$mitraId}", [
                'nama_mitra' => "Mitra Uji {$suffix} Update",
                'email' => "mitra-{$suffix}@example.test",
            ])->assertOk();
            $this->deleteJson("/api/mitra/{$mitraId}")->assertOk();

            $dosbingResponse = $this->postJson('/api/dosbing', [
                'nip' => $suffix === 'admin' ? '198001012010010011' : '198001012010010012',
                'nama' => "Dosen Uji {$suffix}",
                'prodi_id' => $prodi->prodi_id,
            ])->assertCreated();
            $dosbingId = $dosbingResponse->json('data.dosbing_id');

            $nip = $suffix === 'admin' ? '198001012010010011' : '198001012010010012';
            $this->putJson("/api/dosbing/{$dosbingId}", [
                'nip' => $nip,
                'nama' => "Dosen Uji {$suffix} Update",
                'prodi_id' => $prodi->prodi_id,
            ])->assertOk();
            $this->deleteJson("/api/dosbing/{$dosbingId}")->assertOk();
        }
    }

    public function test_admin_without_prodi_cannot_mutate_master_catalogs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $this->postJson('/api/mitra', [
            'nama_mitra' => 'Mitra Admin Tanpa Prodi',
            'email' => 'mitra-admin-tanpa-prodi@example.test',
        ])->assertForbidden();
        $this->postJson('/api/dosbing', [
            'nip' => '198001012010010099',
            'nama' => 'Dosen Admin Tanpa Prodi',
        ])->assertForbidden();
    }
}
