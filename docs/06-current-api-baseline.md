# Current API Baseline - Repository Inspection

## Repository

https://github.com/WahyuAgg/pmld_si_magang_be

The repository currently has default branch `main`.

## Laravel baseline

`composer.json` currently contains:

- PHP `^8.2`
- Laravel Framework `^11.31`
- Laravel Sanctum `^4.0`
- Laravel Tinker `^2.9`
- Laravel Excel `^3.1`
- PHPUnit `^11.0.1` in dev dependencies

The application is therefore a real Laravel 11.x API project, not an empty
skeleton.

## Current route groups

### Authentication / user

- `GET /api/user`
- `GET /api/user/`
- `PUT /api/user/password`
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/logout`

### Mahasiswa

- `GET /api/mahasiswa`
- `POST /api/mahasiswa`
- `POST /api/mahasiswa/update`
- `GET /api/mahasiswa/show`
- `DELETE /api/mahasiswa/{id}`
- `POST /api/mahasiswa/import`

### Mitra

- `GET /api/mitra`
- `POST /api/mitra`
- `GET /api/mitra/show`
- `PUT /api/mitra/{id}`
- `DELETE /api/mitra/{id}`
- `GET /api/mitra/jumlah`
- `GET /api/mitra/magang/{id}`

### Magang

- `GET /api/magang`
- `POST /api/magang`
- `GET /api/magang/{id}`
- `GET /api/magang/{id}/detail`
- `POST /api/magang/{id}`
- `PUT /api/magang/{id}`
- `DELETE /api/magang/{id}`
- `GET /api/magang/export/excel`

### Logbook

- `GET /api/logbook`
- `POST /api/logbook`
- `GET /api/logbook/{id}`
- `PUT /api/logbook/{id}`
- `DELETE /api/logbook/{id}`

### Documents

- `GET /api/dokumen-magang`
- `POST /api/dokumen-magang`
- `GET /api/dokumen-magang/{id}`
- `POST /api/dokumen-magang/{id}`
- `DELETE /api/dokumen-magang/{id}`
- `GET /api/dokumen-magang/magang/{id}`

### Presentasi PI

- `GET /api/jadwal-presentasi`
- `POST /api/jadwal-presentasi`
- `GET /api/jadwal-presentasi/{id}`
- `PUT /api/jadwal-presentasi/{id}`
- `DELETE /api/jadwal-presentasi/{id}`

### Dosen Pembimbing

- `GET /api/dosbing`
- `POST /api/dosbing`
- `GET /api/dosbing/{id}`
- `PUT /api/dosbing/{id}`
- `DELETE /api/dosbing/{id}`
- `GET /api/dosbing/jumlah`
- `GET /api/dosbing/magang/{id}`

### Foto kegiatan

- `GET /api/foto-magang`
- `POST /api/foto-magang`
- `GET /api/foto-magang/{id}`
- `PUT /api/foto-magang/{id}`
- `DELETE /api/foto-magang/{id}`

### Penilaian Mitra

- `GET /api/penilaian-mitra`
- `POST /api/penilaian-mitra`
- `GET /api/penilaian-mitra/{id}`
- `PUT /api/penilaian-mitra/{id}`
- `DELETE /api/penilaian-mitra/{id}`

### Angkatan

- `GET /api/angkatan`

### Dashboard

- `GET /api/dashboard/admin`
- `GET /api/dashboard/mitra`
- `GET /api/dashboard/mahasiswa`

### Laporan

- `GET /api/laporan`
- `POST /api/laporan`
- `GET /api/laporan/{id}`
- `PUT /api/laporan/{id}`
- `DELETE /api/laporan/{id}`

## Current implementation findings worth investigating

### Authorization middleware is broad

Many route groups use:

`role:admin,mahasiswa,mitra,dosbing`

This does not by itself prove data leakage, but it is a strong signal to inspect
controller-level ownership and authorization.

### `MagangController` has role-specific filtering only in `index()`

The student case filters by the authenticated student's `mahasiswa_id`, while
several detail/update/delete actions operate directly from an ID. Those actions
need an explicit authorization review.

### Semester hardcoding

`Magang` currently restricts `semester_magang` to `[6, 7]`. This matches the old
TRPL-oriented behavior but conflicts with a generalized/multi-prodi architecture
unless it is confirmed as a universal business rule.

### Dashboard assumptions

`fetchDashboardDataMahasiswa()` hardcodes semester sequencing around 6 and 7.
`fetchDashboardDataAdmin()` derives an active count from the latest semester.
Both deserve review during ADM dashboard work.

### Migration inconsistency

The current `Magang` model fillable list contains `supervisor_id`, while the current
migration shown in the repository defines `magang` without that field. This needs a
repository-wide check before relying on supervisor-related fields.

### `logbook` relationship shape

The current migration defines `logbook.magang_id` as unique, so the current schema
models one logbook row per internship even though the product requirements describe
periodic activity records. This discrepancy should be investigated before changing
it because it affects both API semantics and frontend expectations.

### File and status behavior

The current `magang` workflow has only:
- draft
- berlangsung
- selesai
- ditolak

The 2026 edge-case analysis discusses richer verification/revision/approval states.
Do not invent a new lifecycle silently; record the gap and confirm the intended state
machine before a breaking change.
