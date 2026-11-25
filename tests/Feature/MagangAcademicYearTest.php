<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Mahasiswa;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MagangAcademicYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_start_year_on_magang_create_and_update(): void
    {
        $prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $adminUser = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $adminUser->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nama' => 'Admin Pengujian',
        ]);

        $studentUser = User::factory()->create(['role' => 'mahasiswa']);
        $student = Mahasiswa::create([
            'user_id' => $studentUser->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => '26/12345/SV/67890',
            'nama' => 'Mahasiswa Pengujian',
            'angkatan' => 2026,
        ]);

        $year2026 = TahunAjaran::create([
            'tahun_ajaran' => '2026/2027',
            'is_active' => true,
        ]);
        PeriodeMagang::create([
            'tahun_ajaran_id' => $year2026->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $created = $this->actingAs($adminUser)->postJson('/api/magang', [
            'mahasiswa_id' => $student->mahasiswa_id,
            'tahun_ajaran' => 2026,
            'semester_magang' => 6,
        ])->assertCreated()
            ->assertJsonPath('data.tahun_ajaran', 2026)
            ->assertJsonPath('data.semester_magang', 6);

        $magangId = $created->json('data.magang_id');
        $this->assertDatabaseHas('magang', [
            'magang_id' => $magangId,
            'tahun_ajaran' => 2026,
        ]);

        $year2027 = TahunAjaran::create([
            'tahun_ajaran' => '2027/2028',
            'is_active' => true,
        ]);
        PeriodeMagang::create([
            'tahun_ajaran_id' => $year2027->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $this->putJson("/api/magang/{$magangId}", [
            'tahun_ajaran' => 2027,
        ])->assertOk()
            ->assertJsonPath('data.tahun_ajaran', 2027);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $magangId,
            'tahun_ajaran' => 2027,
        ]);

        $year2028 = TahunAjaran::create([
            'tahun_ajaran' => '2028/2029',
            'is_active' => true,
        ]);
        PeriodeMagang::create([
            'tahun_ajaran_id' => $year2028->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $newSemester = $this->postJson('/api/magang', [
            'mahasiswa_id' => $student->mahasiswa_id,
            'tahun_ajaran' => 2028,
            'semester_magang' => 8,
        ])->assertCreated()
            ->assertJsonPath('data.tahun_ajaran', 2028)
            ->assertJsonPath('data.semester_magang', 8);

        $this->assertDatabaseHas('magang', [
            'magang_id' => $newSemester->json('data.magang_id'),
            'tahun_ajaran' => 2028,
            'semester_magang' => 8,
        ]);
    }
}
