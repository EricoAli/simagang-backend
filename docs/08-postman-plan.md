# Postman Collection Plan

## Historical source

Historical published Postman documentation:
https://documenter.getpostman.com/view/38607358/2sB3QCTZK6

Treat that documentation as historical reference only. The original team note
warned that it might not be the latest state after an ERD change.

## Current source

The authoritative API inventory for the new collection should be derived from:

1. current `routes/api.php`
2. controller validation rules
3. current response payloads
4. current authentication behavior
5. current decisions recorded in this repository

## Collection structure

```text
SIMAGANG API
├── 00 - Auth
├── 01 - User
├── 02 - Admin
│   ├── Mahasiswa
│   ├── Dosen Pembimbing
│   ├── Mitra
│   ├── Magang
│   ├── Presentasi PI
│   └── Dashboard
├── 03 - Mahasiswa
│   ├── Persiapan Magang
│   ├── Logbook
│   ├── Dokumen
│   ├── Laporan
│   └── Presentasi PI
├── 04 - Mitra
│   └── Penilaian
└── 90 - Negative / Authorization Tests
```

## Environment variables

Use Postman variables rather than hardcoded server addresses or secrets.

Suggested variables:

- `base_url`
- `admin_token`
- `mahasiswa_token`
- `mitra_token`
- `magang_id`
- `mahasiswa_id`
- `mitra_id`
- `dosbing_id`
- `jadwal_id`
- `laporan_id`

Never commit actual tokens/passwords.

## Test categories

For each endpoint, test at least:

1. happy path
2. validation failure
3. unauthenticated request
4. wrong-role request
5. wrong-resource ownership / ID where applicable
6. not-found ID
7. duplicate/conflict condition where applicable

## ADM smoke sequence

### Student master

1. list students
2. create student
3. update student
4. retrieve student/detail

### Dosen Pembimbing master

1. list
2. create
3. update
4. retrieve

### Mitra master

1. list
2. create
3. update
4. retrieve

### Magang

1. list
2. detail
3. create if the current admin requirement permits it
4. update
5. delete only if current MVP/WBS explicitly supports it
6. export if needed by the current ADM scope

### Presentasi PI

1. list
2. create
3. retrieve
4. update
5. delete only when permitted by the current scope

### Dashboard

1. admin dashboard
2. confirm counts against seeded/fixture data
3. verify empty-state behavior
4. verify role/authorization

## Collection deliverable

The final artifact should be:

`postman/SIMAGANG.postman_collection.json`

A separate environment file may be added later:

`postman/SIMAGANG.local.postman_environment.json`

Do not include real production credentials in either file.
