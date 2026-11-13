<?php

namespace Tests\Feature;

use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\NilaiMitra;
use App\Models\Prodi;
use App\Models\User;
use Database\Seeders\ProdiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MitraProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Prodi $prodiTRPL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProdiSeeder::class);
        $this->prodiTRPL = Prodi::where('kode_prodi', 'TRPL')->first();
    }

    private function createMitra(string $nama = 'PT Mitra Awal'): array
    {
        $user = User::factory()->create([
            'role'      => 'mitra',
            'email'     => 'oldemail@corp.com',
            'is_active' => true,
        ]);
        $mitra = Mitra::create([
            'user_id'    => $user->user_id,
            'nama_mitra' => $nama,
            'email'      => 'oldemail@corp.com',
            'narahubung' => 'Narahubung Lama',
        ]);
        return [$user, $mitra];
    }

    private function createMahasiswa(): array
    {
        $user = User::factory()->create(['role' => 'mahasiswa', 'is_active' => true]);
        $mahasiswa = Mahasiswa::create([
            'user_id'  => $user->user_id,
            'prodi_id' => $this->prodiTRPL->prodi_id,
            'nim'      => '24/55555/SV/55555',
            'nama'     => 'Mahasiswa Uji',
            'angkatan' => 2024,
        ]);
        return [$user, $mahasiswa];
    }

    public function test_mitra_can_update_profile_and_persists_to_database()
    {
        [$mitraUser, $mitra] = $this->createMitra();

        $response = $this->actingAs($mitraUser)->putJson('/api/mitra/profile', [
            'nama_mitra' => 'PT Teknologi Masa Depan',
            'narahubung' => 'Dr. Hendra Gunawan',
            'email'      => 'kontak@teknologimasadepan.co.id',
            'no_hp'      => '081234567890',
            'alamat'     => 'Jl. Kaliurang KM 9, Sleman, Yogyakarta',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profil perusahaan berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('mitra', [
            'mitra_id'   => $mitra->mitra_id,
            'nama_mitra' => 'PT Teknologi Masa Depan',
            'narahubung' => 'Dr. Hendra Gunawan',
            'email'      => 'kontak@teknologimasadepan.co.id',
        ]);

        $this->assertDatabaseHas('users', [
            'user_id' => $mitraUser->user_id,
            'email'   => 'kontak@teknologimasadepan.co.id',
        ]);
    }

    public function test_mitra_update_profile_rejects_invalid_email_with_422()
    {
        [$mitraUser, $mitra] = $this->createMitra();

        $response = $this->actingAs($mitraUser)->putJson('/api/mitra/profile', [
            'nama_mitra' => 'PT Valid Name',
            'email'      => 'bukan-alamat-email-valid',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_non_mitra_cannot_update_mitra_profile_returns_403()
    {
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();

        $response = $this->actingAs($mhsUser)->putJson('/api/mitra/profile', [
            'nama_mitra' => 'Hacker Corp',
        ]);

        $response->assertStatus(403);
    }

    public function test_penilaian_mitra_update_supports_supervisor_name_and_position()
    {
        [$mitraUser, $mitra] = $this->createMitra();
        [$mhsUser, $mahasiswa] = $this->createMahasiswa();

        $magang = Magang::create([
            'mahasiswa_id'    => $mahasiswa->mahasiswa_id,
            'mitra_id'        => $mitra->mitra_id,
            'semester_magang' => 6,
            'role_magang'     => 'Software Developer',
            'periode_bulan'   => 5,
            'status_magang'   => 'berlangsung',
        ]);

        $nilai = NilaiMitra::create([
            'magang_id'                        => $magang->magang_id,
            'nilai_teknis'                     => 80,
            'nilai_profesionalisme_etika'      => 85,
            'nilai_komunikasi_presentasi'      => 90,
            'nilai_proyek_pengalaman_industri' => 88,
            'keterangan'                       => 'Bagus',
            'supervisor'                       => 'Supervisor Awal',
            'jabatan_supervisor'               => 'Staff',
            'nama_file'                        => 'penilaian_awal.pdf',
            'file_path'                        => 'penilaian_mitra/penilaian_awal.pdf',
        ]);

        // Mitra update penilaian dengan supervisor baru
        $response = $this->actingAs($mitraUser)->putJson("/api/penilaian-mitra/{$nilai->penilaian_id}", [
            'nilai_teknis'       => 95,
            'supervisor'         => 'Ir. Sutrisno, M.Kom',
            'jabatan_supervisor' => 'VP of Engineering',
            'keterangan'         => 'Kinerja sangat luar biasa',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('penilaian_mitra', [
            'penilaian_id'       => $nilai->penilaian_id,
            'supervisor'         => 'Ir. Sutrisno, M.Kom',
            'jabatan_supervisor' => 'VP of Engineering',
            'nilai_teknis'       => 95,
        ]);
    }
}
