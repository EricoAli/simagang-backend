<?php

namespace Tests\Feature;

use App\Models\PeriodeMagang;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicPeriodMasterTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_list_year_and_related_period(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $year = $this->actingAs($superAdmin)
            ->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2028/2029'])
            ->assertCreated()
            ->assertJsonPath('data.tahun_ajaran', '2028/2029')
            ->assertJsonPath('data.is_active', true);

        $period = $this->postJson('/api/periode-magang', [
            'tahun_ajaran_id' => $year->json('data.tahun_ajaran_id'),
            'semester_magang' => 8,
            'jenis_semester' => 'Genap',
        ])->assertCreated()
            ->assertJsonPath('data.semester_magang', 8)
            ->assertJsonPath('data.jenis_semester', 'Genap')
            ->assertJsonPath('data.tahun_ajaran.tahun_ajaran', '2028/2029');

        $otherYear = TahunAjaran::create(['tahun_ajaran' => '2030/2031', 'is_active' => false]);
        PeriodeMagang::create([
            'tahun_ajaran_id' => $otherYear->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);

        $this->getJson('/api/periode-magang?tahun_ajaran_id=' . $year->json('data.tahun_ajaran_id'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.periode_id', $period->json('data.periode_id'));
        $this->getJson('/api/periode-magang?available_only=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.periode_id', $period->json('data.periode_id'));

        $this->putJson('/api/periode-magang/' . $period->json('data.periode_id'), [
            'tahun_ajaran_id' => $year->json('data.tahun_ajaran_id'),
            'semester_magang' => 10,
            'jenis_semester' => 'Genap',
        ])->assertOk()
            ->assertJsonPath('data.semester_magang', 10);
    }

    public function test_duplicate_year_period_and_inconsistent_semester_type_are_rejected(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin);

        $year = TahunAjaran::create(['tahun_ajaran' => '2029/2030', 'is_active' => true]);
        $this->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2029/2030'])
            ->assertUnprocessable();

        $payload = [
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => 7,
            'jenis_semester' => 'Gasal',
        ];
        $this->postJson('/api/periode-magang', $payload)->assertCreated();
        $this->postJson('/api/periode-magang', $payload)->assertUnprocessable();

        $this->postJson('/api/periode-magang', [
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'Gasal',
        ])->assertUnprocessable();
    }

    public function test_only_super_admin_can_change_period_master_and_active_flags(): void
    {
        $year = TahunAjaran::create(['tahun_ajaran' => '2030/2031', 'is_active' => true]);
        $period = PeriodeMagang::create([
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => 6,
            'jenis_semester' => 'Genap',
            'is_active' => true,
        ]);
        $payload = [
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'semester_magang' => 8,
            'jenis_semester' => 'Genap',
        ];

        foreach (['admin', 'mahasiswa', 'mitra', 'dosbing'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/tahun-ajaran')->assertOk();
            $this->postJson('/api/tahun-ajaran', ['tahun_ajaran' => '2031/2032'])
                ->assertForbidden();
            $this->putJson("/api/tahun-ajaran/{$year->tahun_ajaran_id}", ['is_active' => false])
                ->assertForbidden();
            $this->postJson('/api/periode-magang', $payload)->assertForbidden();
            $this->putJson("/api/periode-magang/{$period->periode_id}", ['is_active' => false])
                ->assertForbidden();
        }

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)
            ->putJson("/api/tahun-ajaran/{$year->tahun_ajaran_id}", ['is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);
        $this->putJson("/api/periode-magang/{$period->periode_id}", ['semester_magang' => 8, 'jenis_semester' => 'Genap'])
            ->assertOk()
            ->assertJsonPath('data.semester_magang', 8);

        $this->assertDatabaseHas('tahun_ajaran', [
            'tahun_ajaran_id' => $year->tahun_ajaran_id,
            'is_active' => false,
        ]);
    }
}
