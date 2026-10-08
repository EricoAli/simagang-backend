# Historical SRS and ERD - 2025 Reference

## Historical SRS

The 2025 SRS is Version 1.0 and was prepared by the SIMAGANG team for TRPL SV
UGM in October 2025.

It describes:

- three main actors: Admin, Mahasiswa, Mitra
- centralized master data
- document management
- Presentasi PI scheduling
- Mitra assessment and supervisor information
- Vue.js frontend
- Laravel backend
- MySQL database
- RESTful API over HTTP/HTTPS

The SRS also states performance/security expectations such as support for an
estimated 150 concurrent active users, common page/dashboard response below
3 seconds under the stated campus-network assumption, and document uploads up
to 10 MB in the historical specification.

## Important historical conflicts

The historical SRS contains a description of pra-magang documents that includes
Pra-KRS. The 2026 requirement docs explicitly keep Pra-KRS in SIMASTER and out of
SIMAGANG.

Therefore:

- use SRS for historical context
- do not use it to override current 2026 scope decisions

## Historical ERD baseline

The provided ERD (`ddesign2.1.html`) shows entities including:

- users
- mahasiswa
- mitra
- dosen_pembimbing
- supervisor
- magang
- logbook
- foto_kegiatan
- dokumen_magang
- dokumen_penilaian_mitra
- penilaian_mitra
- laporan-related structures in the model
- jadwal_presentasi
- Laravel framework support tables

Important relationships visible in the ERD include:

- `magang.mahasiswa_id -> mahasiswa.mahasiswa_id`
- `magang.mitra_id -> mitra.mitra_id`
- `magang.dosbing_id -> dosen_pembimbing.dosbing_id`
- `logbook.magang_id -> magang.magang_id`
- `foto_kegiatan.logbook_id -> logbook.logbook_id`
- `supervisor.mitra_id -> mitra.mitra_id`
- `supervisor.user_id -> users.user_id`

## Critical architecture observation

The historical ERD does not show a dedicated `prodi` entity in the visible model.
That is a major point to investigate before making the system multi-prodi.
Do not invent the final multi-prodi schema without first comparing:

1. current migrations
2. current models
3. actual data constraints
4. current PM requirements
5. future isolation requirements
