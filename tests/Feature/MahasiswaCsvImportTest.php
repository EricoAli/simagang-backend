<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MahasiswaCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private function upload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('mahasiswa.csv', $contents);
    }

    private function prodi(string $code): Prodi
    {
        return Prodi::where('kode_prodi', $code)->firstOrFail();
    }

    public function test_super_admin_imports_students_across_programs_without_creating_internships(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $csv = "nama_lengkap,nim,prodi,angkatan\n"
            . "Mahasiswa Satu,24/542620/SV/25063,TRPL,2024\n"
            . "Mahasiswa Dua,23/10102/AB/20102,TRIK,2023\n";

        $this->actingAs($superAdmin)
            ->post('/api/mahasiswa/import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertJsonPath('imported_count', 2)
            ->assertJsonPath('failed_count', 0);

        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '24/542620/SV/25063',
            'nama' => 'Mahasiswa Satu',
            'angkatan' => '2024',
            'prodi_id' => $this->prodi('TRPL')->prodi_id,
        ]);
        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '23/10102/AB/20102',
            'angkatan' => '2023',
            'prodi_id' => $this->prodi('TRIK')->prodi_id,
        ]);
        $this->assertDatabaseCount('magang', 0);
    }

    public function test_invalid_rows_are_reported_without_rolling_back_valid_rows_or_overwriting_existing_data(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $existingUser = User::factory()->create(['username' => '10201', 'role' => 'mahasiswa']);
        Mahasiswa::create([
            'user_id' => $existingUser->user_id,
            'nim' => '24/10201/AB/20201',
            'nama' => 'Nama Lama',
            'angkatan' => '2024',
            'prodi_id' => $this->prodi('TRPL')->prodi_id,
        ]);

        $csv = "nama_lengkap,nim,prodi,angkatan\n"
            . "Valid,24/10301/AB/20301,TRPL,2024\n"
            . "Duplikat,24/10201/AB/20201,TRIK,2024\n"
            . "Prodi Salah,24/10401/AB/20401,UNKNOWN,2024\n"
            . "Angkatan Salah,24/10501/AB/20501,TRPL,2022\n";

        $this->actingAs($superAdmin)
            ->post('/api/mahasiswa/import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertJsonPath('imported_count', 1)
            ->assertJsonPath('failed_count', 3)
            ->assertJsonCount(3, 'errors');

        $this->assertDatabaseHas('mahasiswa', ['nim' => '24/10301/AB/20301']);
        $this->assertDatabaseHas('mahasiswa', [
            'nim' => '24/10201/AB/20201',
            'nama' => 'Nama Lama',
            'prodi_id' => $this->prodi('TRPL')->prodi_id,
        ]);
    }

    public function test_admin_prodi_imports_only_rows_for_own_program(): void
    {
        $trpl = $this->prodi('TRPL');
        $admin = User::factory()->create(['role' => 'admin']);
        Admin::create(['user_id' => $admin->user_id, 'prodi_id' => $trpl->prodi_id, 'nama' => 'Admin TRPL']);
        $csv = "nama_lengkap,nim,prodi,angkatan\n"
            . "Mahasiswa TRPL,24/10601/AB/20601,TRPL,2024\n"
            . "Mahasiswa TRIK,24/10701/AB/20701,TRIK,2024\n";

        $this->actingAs($admin)
            ->post('/api/mahasiswa/import', ['file' => $this->upload($csv)])
            ->assertOk()
            ->assertJsonPath('imported_count', 1)
            ->assertJsonPath('failed_count', 1)
            ->assertJsonPath('errors.0', 'Baris 3 (NIM 24/10701/AB/20701): Admin Prodi hanya dapat mengimpor mahasiswa ke program studinya sendiri.');

        $this->assertDatabaseHas('mahasiswa', ['nim' => '24/10601/AB/20601', 'prodi_id' => $trpl->prodi_id]);
        $this->assertDatabaseMissing('mahasiswa', ['nim' => '24/10701/AB/20701']);
    }

    public function test_missing_or_extra_csv_headers_are_rejected_clearly(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)
            ->post('/api/mahasiswa/import', ['file' => $this->upload("nama_lengkap,nim,prodi\nNama,24/10801/AB/20801,TRPL\n")])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Header CSV tidak sesuai. Gunakan tepat: nama_lengkap,nim,prodi,angkatan.');

        $this->post('/api/mahasiswa/import', ['file' => $this->upload("nama_lengkap,nim,prodi,angkatan,status\nNama,24/10801/AB/20801,TRPL,2024,aktif\n")])
            ->assertUnprocessable();
    }

    public function test_non_admin_roles_cannot_import_students(): void
    {
        foreach (['mahasiswa', 'mitra', 'dosbing'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->post('/api/mahasiswa/import', ['file' => $this->upload("nama_lengkap,nim,prodi,angkatan\n")])
                ->assertForbidden();
        }
    }
}
