<?php

namespace Tests\Feature;

use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\PeriodeMagang;
use App\Models\Prodi;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MagangExportPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_contains_master_period_for_each_magang_history(): void
    {
        $this->seed(\Database\Seeders\ProdiSeeder::class);
        $prodi = Prodi::where('kode_prodi', 'TRPL')->firstOrFail();
        $student = Mahasiswa::create([
            'user_id' => User::factory()->create(['role' => 'mahasiswa'])->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => '26/0099/SV/00099',
            'nama' => 'Riwayat Export Test',
            'angkatan' => 2026,
        ]);

        $period2026 = $this->createPeriod('2026/2027', 6, 'Genap');
        $period2027 = $this->createPeriod('2027/2028', 7, 'Gasal');

        Magang::create([
            'mahasiswa_id' => $student->mahasiswa_id,
            'periode_id' => $period2026->periode_id,
            'tahun_ajaran' => 2026,
            'semester_magang' => 6,
            'status_magang' => 'selesai',
        ]);
        Magang::create([
            'mahasiswa_id' => $student->mahasiswa_id,
            'periode_id' => $period2027->periode_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 7,
            'status_magang' => 'berlangsung',
        ]);
        // Data legacy tanpa periode_id tetap harus ikut diekspor.
        Magang::create([
            'mahasiswa_id' => $student->mahasiswa_id,
            'tahun_ajaran' => 2028,
            'semester_magang' => 8,
            'status_magang' => 'draft',
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get('/api/magang/export/excel')
            ->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'simagang-export-');
        file_put_contents($path, $response->streamedContent());
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray();
        } finally {
            @unlink($path);
        }

        $header = $rows[0];
        $periodColumn = array_search('Periode Magang', $header, true);
        $this->assertNotFalse($periodColumn);
        $periodValues = array_column(array_slice($rows, 1), $periodColumn);

        $this->assertCount(3, $periodValues);
        $this->assertContains('2026/2027 - Semester 6 Genap', $periodValues);
        $this->assertContains('2027/2028 - Semester 7 Gasal', $periodValues);
        $this->assertContains('2028 - Semester 8', $periodValues);
    }

    private function createPeriod(string $year, int $semester, string $type): PeriodeMagang
    {
        $academicYear = TahunAjaran::create([
            'tahun_ajaran' => $year,
            'is_active' => true,
        ]);

        return PeriodeMagang::create([
            'tahun_ajaran_id' => $academicYear->tahun_ajaran_id,
            'semester_magang' => $semester,
            'jenis_semester' => $type,
            'is_active' => true,
        ]);
    }
}
