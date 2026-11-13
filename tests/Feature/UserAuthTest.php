<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
    }

    public function test_auth_login_eager_loads_nested_relations_for_admin_mahasiswa_and_mitra()
    {
        // Admin with Prodi
        $adminUser = User::factory()->create([
            'role'      => 'admin',
            'is_active' => true,
            'password'  => Hash::make('AdminPass123!'),
        ]);
        Admin::create([
            'user_id'  => $adminUser->user_id,
            'prodi_id' => $this->prodiTRPL->prodi_id,
            'nama'     => 'Admin TRPL',
        ]);

        $resAdmin = $this->postJson('/api/auth/login', [
            'username' => $adminUser->username,
            'password' => 'AdminPass123!',
        ]);
        $resAdmin->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'role',
                'user' => [
                    'admin' => [
                        'prodi' => ['nama_prodi', 'kode_prodi']
                    ]
                ]
            ]);

        // Mahasiswa with Prodi
        $mhsUser = User::factory()->create([
            'role'      => 'mahasiswa',
            'is_active' => true,
            'password'  => Hash::make('MhsPass123!'),
        ]);
        Mahasiswa::create([
            'user_id'  => $mhsUser->user_id,
            'prodi_id' => $this->prodiTRPL->prodi_id,
            'nim'      => '24/12345/SV/12345',
            'nama'     => 'Budi Mhs',
            'angkatan' => 2024,
        ]);

        $resMhs = $this->postJson('/api/auth/login', [
            'username' => $mhsUser->username,
            'password' => 'MhsPass123!',
        ]);
        $resMhs->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'role',
                'user' => [
                    'mahasiswa' => [
                        'prodi' => ['nama_prodi', 'kode_prodi']
                    ]
                ]
            ]);

        // Mitra
        $mitraUser = User::factory()->create([
            'role'      => 'mitra',
            'is_active' => true,
            'password'  => Hash::make('MitraPass123!'),
        ]);
        Mitra::create([
            'user_id'    => $mitraUser->user_id,
            'nama_mitra' => 'PT Mitra Terpadu',
            'email'      => 'mitra@terpadu.id',
            'narahubung' => 'Ibu Maria',
        ]);

        $resMitra = $this->postJson('/api/auth/login', [
            'username' => $mitraUser->username,
            'password' => 'MitraPass123!',
        ]);
        $resMitra->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'role',
                'user' => [
                    'mitra' => ['nama_mitra', 'email', 'narahubung']
                ]
            ]);
    }

    public function test_user_password_update_rejects_invalid_old_password_with_422()
    {
        $user = User::factory()->create([
            'password'  => Hash::make('CurrentValid123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/user/password', [
            'password_lama'         => 'WrongOld123!',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'NewSecret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password_lama');
    }

    public function test_user_password_update_rejects_mismatched_confirmation_with_422()
    {
        $user = User::factory()->create([
            'password'  => Hash::make('CurrentValid123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/user/password', [
            'password_lama'         => 'CurrentValid123!',
            'password'              => 'NewSecret123!',
            'password_confirmation' => 'MismatchedSecret123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_user_password_update_success_persists_new_hash_to_database()
    {
        $user = User::factory()->create([
            'password'  => Hash::make('CurrentValid123!'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson('/api/user/password', [
            'password_lama'         => 'CurrentValid123!',
            'password'              => 'BrandNewSecret123!',
            'password_confirmation' => 'BrandNewSecret123!',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password berhasil diubah']);

        $this->assertTrue(Hash::check('BrandNewSecret123!', $user->fresh()->password));
    }
}
