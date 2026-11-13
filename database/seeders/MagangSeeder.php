<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Magang;
use App\Models\Mahasiswa;
use App\Models\Mitra;
use App\Models\Dosbing;

class MagangSeeder extends Seeder
{
    public function run(): void
    {
        $mahasiswaList = Mahasiswa::all();
        $mitraList = Mitra::all();
        $dosbingList = Dosbing::all();

        if ($mahasiswaList->isEmpty() || $mitraList->isEmpty() || $dosbingList->isEmpty()) {
            return;
        }

        $roles = [
            'Fullstack Web Developer',
            'Backend Engineer',
            'Frontend Engineer',
            'Mobile Application Developer',
            'UI/UX Designer',
            'Quality Assurance Intern',
            'Cloud & DevOps Engineer',
            'IoT Solutions Developer',
        ];

        $jobdesks = [
            'Mengembangkan modul logbook, manajemen dokumen magang, dan integrasi API Sanctum.',
            'Membuat arsitektur microservices dan optimasi performa database MySQL.',
            'Merancang antarmuka dashboard responsif berbasis Vue.js dan Vue Material.',
            'Mengembangkan aplikasi Android internal untuk pelaporan kegiatan lapangan.',
            'Menyusun wireframe, design system, dan usability testing aplikasi mitra.',
        ];

        $statuses = ['berlangsung', 'berlangsung', 'selesai', 'berlangsung', 'draft'];

        foreach ($mahasiswaList as $index => $mhs) {
            $tahunAjaran = $mhs->angkatan == 2022 ? 2026 : 2026;
            $mitra = $mitraList[$index % $mitraList->count()];
            $dosbing = $dosbingList[$index % $dosbingList->count()];
            $role = $roles[$index % count($roles)];
            $jobdesk = $jobdesks[$index % count($jobdesks)];
            $status = $statuses[$index % count($statuses)];

            // Buat atau perbarui magang ke-1
            Magang::updateOrCreate(
                [
                    'mahasiswa_id'      => $mhs->mahasiswa_id,
                    'jumlah_magang_ke'  => 1,
                ],
                [
                    'mitra_id'         => $mitra->mitra_id,
                    'dosbing_id'       => $dosbing->dosbing_id,
                    'tahun_ajaran'     => $tahunAjaran,
                    'semester_magang'  => 6,
                    'role_magang'      => $role,
                    'jobdesk'          => $jobdesk,
                    'tanggal_mulai'    => '2026-02-01',
                    'tanggal_selesai'  => '2026-07-31',
                    'periode_bulan'    => 6,
                    'status_magang'    => $status,
                ]
            );

            // Mahasiswa angkatan 2022 tertentu diberi magang ke-2 (semester 7) berstatus berlangsung/selesai
            if ($mhs->angkatan == 2022 && $index < 4) {
                $mitra2 = $mitraList[($index + 2) % $mitraList->count()];
                $dosbing2 = $dosbingList[($index + 1) % $dosbingList->count()];
                Magang::updateOrCreate(
                    [
                        'mahasiswa_id'      => $mhs->mahasiswa_id,
                        'jumlah_magang_ke'  => 2,
                    ],
                    [
                        'mitra_id'         => $mitra2->mitra_id,
                        'dosbing_id'       => $dosbing2->dosbing_id,
                        'tahun_ajaran'     => 2026,
                        'semester_magang'  => 7,
                        'role_magang'      => 'Senior Software Engineer Intern',
                        'jobdesk'          => 'Penyempurnaan arsitektur enterprise dan automasi pipeline CI/CD.',
                        'tanggal_mulai'    => '2026-08-01',
                        'tanggal_selesai'  => '2026-12-31',
                        'periode_bulan'    => 5,
                        'status_magang'    => 'berlangsung',
                    ]
                );
            }
        }
    }
}
