<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Logbook;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogbookDailyEntriesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private Magang $magang;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seed(\Database\Seeders\ProdiSeeder::class);

        $prodi = Prodi::firstOrFail();
        $this->student = User::factory()->create(['role' => 'mahasiswa']);
        $mahasiswa = Mahasiswa::create([
            'user_id' => $this->student->user_id,
            'prodi_id' => $prodi->prodi_id,
            'nim' => 'NIM-' . uniqid(),
            'nama' => 'Mahasiswa Logbook',
            'angkatan' => '2024',
        ]);

        $this->magang = Magang::create([
            'mahasiswa_id' => $mahasiswa->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 6,
            'status_magang' => 'berlangsung',
            'tanggal_mulai' => '2026-01-12',
            'tanggal_selesai' => '2026-06-12',
        ]);
    }

    private function logbookData(array $overrides = []): array
    {
        return array_merge([
            'magang_id' => $this->magang->magang_id,
            'tanggal_kegiatan' => '2026-03-12',
            'kegiatan' => 'Mengerjakan modul logbook.',
            'foto' => [
                UploadedFile::fake()->image('kegiatan-1.jpg', 20, 20),
            ],
        ], $overrides);
    }

    public function test_student_can_create_multiple_daily_entries_with_multiple_photos_and_read_them(): void
    {
        $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData([
                'foto' => [
                    UploadedFile::fake()->image('kegiatan-1.jpg', 20, 20),
                    UploadedFile::fake()->image('kegiatan-2.png', 20, 20),
                ],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.tanggal_kegiatan', '2026-03-12')
            ->assertJsonCount(2, 'data.foto_kegiatan');

        $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData([
                'tanggal_kegiatan' => '2026-03-13',
                'kegiatan' => 'Melanjutkan pengujian.',
            ]))
            ->assertCreated();

        $this->actingAs($this->student)
            ->getJson('/api/logbook?magang_id=' . $this->magang->magang_id)
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.tanggal_kegiatan', '2026-03-13')
            ->assertJsonPath('1.tanggal_kegiatan', '2026-03-12');

        $logbookId = Logbook::firstOrFail()->logbook_id;
        $this->actingAs($this->student)
            ->getJson('/api/logbook/' . $logbookId)
            ->assertOk()
            ->assertJsonPath('data.logbook_id', $logbookId);
    }

    public function test_student_can_update_entry_and_remove_owned_documentation(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData());
        $logbookId = $response->assertCreated()->json('data.logbook_id');
        $fotoId = $response->json('data.foto_kegiatan.0.foto_id');
        $fotoPath = $response->json('data.foto_kegiatan.0.file_path');

        $this->actingAs($this->student)
            ->post('/api/logbook/' . $logbookId, [
                '_method' => 'PUT',
                'tanggal_kegiatan' => '2026-03-14',
                'kegiatan' => 'Deskripsi diperbarui.',
                'foto' => [UploadedFile::fake()->image('tambahan.png', 20, 20)],
                'delete_foto_ids' => [$fotoId],
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.tanggal_kegiatan', '2026-03-14')
            ->assertJsonCount(1, 'data.foto_kegiatan');

        Storage::disk('public')->assertMissing($fotoPath);
    }

    public function test_student_cannot_access_another_students_logbook(): void
    {
        $foreignUser = User::factory()->create(['role' => 'mahasiswa']);
        $foreignProdi = Prodi::firstOrFail();
        $foreignStudent = Mahasiswa::create([
            'user_id' => $foreignUser->user_id,
            'prodi_id' => $foreignProdi->prodi_id,
            'nim' => 'NIM-' . uniqid(),
            'nama' => 'Mahasiswa Lain',
            'angkatan' => '2023',
        ]);
        $foreignMagang = Magang::create([
            'mahasiswa_id' => $foreignStudent->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 7,
            'status_magang' => 'berlangsung',
        ]);
        $foreignLogbook = Logbook::create([
            'magang_id' => $foreignMagang->magang_id,
            'tanggal_kegiatan' => '2026-03-12',
            'kegiatan' => 'Kegiatan mahasiswa lain.',
        ]);

        $this->actingAs($this->student)
            ->getJson('/api/logbook/' . $foreignLogbook->logbook_id)
            ->assertForbidden();

        $this->actingAs($this->student)
            ->putJson('/api/logbook/' . $foreignLogbook->logbook_id, [
                'tanggal_kegiatan' => '2026-03-13',
                'kegiatan' => 'Tidak boleh mengubah.',
            ])
            ->assertForbidden();

        $this->actingAs($this->student)
            ->deleteJson('/api/logbook/' . $foreignLogbook->logbook_id)
            ->assertForbidden();
    }

    public function test_invalid_date_and_invalid_image_are_rejected(): void
    {
        $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData(['tanggal_kegiatan' => 'bukan-tanggal']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tanggal_kegiatan');

        $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData([
                'foto' => [UploadedFile::fake()->create('dokumen.pdf', 30, 'application/pdf')],
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('foto.0');
    }

    public function test_student_cannot_delete_other_entries_photos_by_id(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData());
        $logbookId = $response->assertCreated()->json('data.logbook_id');

        $this->actingAs($this->student)
            ->post('/api/logbook/' . $logbookId, [
                '_method' => 'PUT',
                'tanggal_kegiatan' => '2026-03-12',
                'kegiatan' => 'Kegiatan.',
                'delete_foto_ids' => [999999],
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable();
    }

    public function test_deleting_logbook_removes_only_its_photos_and_entry(): void
    {
        $response = $this->actingAs($this->student)
            ->postJson('/api/logbook', $this->logbookData());
        $logbookId = $response->assertCreated()->json('data.logbook_id');
        $photoPath = $response->json('data.foto_kegiatan.0.file_path');

        $this->actingAs($this->student)
            ->deleteJson('/api/logbook/' . $logbookId)
            ->assertOk();

        $this->assertDatabaseMissing('logbook', ['logbook_id' => $logbookId]);
        $this->assertDatabaseMissing('foto_kegiatan', ['logbook_id' => $logbookId]);
        Storage::disk('public')->assertMissing($photoPath);
        $this->assertDatabaseHas('magang', ['magang_id' => $this->magang->magang_id]);
    }

    public function test_super_admin_and_admin_keep_their_existing_logbook_scope(): void
    {
        $ownLogbook = Logbook::create([
            'magang_id' => $this->magang->magang_id,
            'tanggal_kegiatan' => '2026-03-12',
            'kegiatan' => 'Kegiatan prodi sendiri.',
        ]);

        $otherProdi = Prodi::where('prodi_id', '!=', $this->magang->mahasiswa->prodi_id)->firstOrFail();
        $otherStudentUser = User::factory()->create(['role' => 'mahasiswa']);
        $otherStudent = Mahasiswa::create([
            'user_id' => $otherStudentUser->user_id,
            'prodi_id' => $otherProdi->prodi_id,
            'nim' => 'NIM-' . uniqid(),
            'nama' => 'Mahasiswa Prodi Lain',
            'angkatan' => '2023',
        ]);
        $otherMagang = Magang::create([
            'mahasiswa_id' => $otherStudent->mahasiswa_id,
            'tahun_ajaran' => 2027,
            'semester_magang' => 7,
            'status_magang' => 'berlangsung',
        ]);
        Logbook::create([
            'magang_id' => $otherMagang->magang_id,
            'tanggal_kegiatan' => '2026-03-13',
            'kegiatan' => 'Kegiatan prodi lain.',
        ]);

        $admin = User::factory()->create(['role' => 'admin']);
        Admin::create([
            'user_id' => $admin->user_id,
            'prodi_id' => $this->magang->mahasiswa->prodi_id,
            'nama' => 'Admin Prodi Fixture',
        ]);

        $this->actingAs($admin)
            ->getJson('/api/logbook')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.logbook_id', $ownLogbook->logbook_id);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($superAdmin)
            ->getJson('/api/logbook')
            ->assertOk()
            ->assertJsonCount(2);
    }
}
