# ADM Presentasi PI API Reference

**Status:** IMPLEMENTED

**Schema note:** `jadwal_presentasi` does not have a `magang_id` column. The
link between a schedule and students is stored in `jadwal_peserta`
(`jadwal_id`, `magang_id`, `mahasiswa_id`). One schedule can hold several
participants. The examiner is stored in `jadwal_presentasi.dosen_penguji_id`.

**Statuses:** `terjadwal`, `selesai`, `dibatalkan`.

## Role Access Matrix

| Role | List | Detail | Create / Update / Delete | Participants |
|------|------|--------|--------------------------|--------------|
| Super Admin | All prodi | All | Yes | Yes |
| Admin Prodi | Schedules whose examiner is from own prodi | Own prodi | Own prodi | Students from own prodi |
| Dosbing | Schedules containing own advisees | Own advisees | Own advisees only | Own advisees only |
| Mahasiswa | Own schedules only | Own schedule only | No | Read only |
| Mitra | Empty list | No | No | No |

Dosbing accounts are linked to `dosen_pembimbing` through
`dosen_pembimbing.user_id`. Legacy accounts whose `users.username` equals
`dosen_pembimbing.nip` are still recognised.

A dosbing may manage a session only when every participant is their advisee.
An empty session may be managed by any linked dosbing.

---

## 1. Get List of Jadwal Presentasi

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Super Admin, Admin, Mahasiswa, Mitra, Dosbing

### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | (Optional) `terjadwal`, `selesai`, or `dibatalkan`. |
| `tanggal_presentasi` | date (Y-m-d) | (Optional) Filter by date. |
| `per_page` | integer | (Optional) Items per page. Default: 15. |

### Response
- **Status Code:** `200 OK`
- Each item includes `dosen_penguji`, `dosen_penguji_data`, `status`,
  `tempat`, `peserta_count`, and `punya_peserta` in addition to the raw
  schedule fields.

---

## 2. Get Dosbing Advisees

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi/advisees`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Dosbing only

### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | (Optional) Search by student name or NIM. |
| `status_magang` | string | (Optional) Filter by magang status. |

### Response
- **Status Code:** `200 OK`
- Each row contains `magang_id`, student identity, `mitra`, `status_magang`,
  `semester_magang`, `status_penjadwalan` (`belum_dijadwalkan`,
  `sudah_dijadwalkan`, `selesai`), `bisa_dijadwalkan`, `jadwal_id`, and the
  related `jadwal` when one exists.

```json
{
  "data": [
    {
      "magang_id": 12,
      "nama": "Nama Mahasiswa",
      "nim": "24/0000/SV/00001",
      "prodi": "TRPL",
      "mitra": "PT Contoh",
      "status_magang": "berlangsung",
      "semester_magang": 6,
      "status_penjadwalan": "belum_dijadwalkan",
      "bisa_dijadwalkan": true,
      "jadwal_id": null,
      "jadwal": null
    }
  ]
}
```

A dosbing without a linked `dosen_pembimbing` record receives `403`.

---

## 3. Get Jadwal Presentasi Detail

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Super Admin, Admin, Mahasiswa (own), Dosbing (own advisees)

### Response
- **Status Code:** `200 OK`
- **Status Code:** `403 Forbidden` when the schedule is outside the caller's scope.

---

## 4. Create Jadwal Presentasi

- **HTTP Method:** `POST`
- **Endpoint:** `/api/jadwal-presentasi`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Super Admin, Admin Prodi, Dosbing

### Request Body
| Field | Type | Description |
|-------|------|-------------|
| `magang_id` | integer | Required for Dosbing. Optional for Admin/Super Admin. |
| `dosen_penguji_id` | integer | **Required.** Existing `dosen_pembimbing.dosbing_id`. |
| `tanggal_presentasi` | date | **Required.** Format: Y-m-d |
| `waktu_mulai` | string | **Required.** Format: H:i |
| `waktu_selesai` | string | **Required.** Format: H:i, after `waktu_mulai`. |
| `ruangan` | string | **Required.** Max 100 chars. |
| `tempat` | string | (Optional) Max 100 chars. |
| `keterangan` | string | (Optional) |

When `magang_id` is supplied, a `jadwal_peserta` row is created in the same
database transaction. Dosbing creation requires `magang_id` belonging to their
own advisee.

### Business Validation
- Student must have `status_magang = berlangsung` and semester `6` or `7`.
- A student may not have another `terjadwal` schedule.
- A schedule may not overlap (same date + overlapping time) with another
  non-cancelled schedule in the same normalised `ruangan` or with the same
  `dosen_penguji_id`.
- Admin Prodi may only use an examiner and student from their own prodi.

### Response
- **Status Code:** `201 Created`
- **Status Code:** `422 Unprocessable Entity` for validation, duplicate active
  schedule, or conflict.
- **Status Code:** `403 Forbidden` for out-of-scope examiner/student.

---

## 5. Update Jadwal Presentasi

- **HTTP Method:** `PUT`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Super Admin, Admin Prodi, Dosbing

### Request Body
Same fields as create. `ruangan` is required. Optional `magang_id` adds a
participant without removing existing participants. Existing participants are
preserved.

The same duplicate-schedule and conflict rules as create apply.

### Response
- **Status Code:** `200 OK`
- **Status Code:** `403`, `404`, `422` as applicable.

---

## 6. Delete Jadwal Presentasi

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Super Admin, Admin Prodi, Dosbing

A schedule that still has participants cannot be deleted. Remove the
participants first. Schedule history is not removed implicitly.

### Response
- **Status Code:** `200 OK`
- **Status Code:** `422 Unprocessable Entity` when participants still exist.

```json
{
  "message": "Sesi jadwal presentasi tidak dapat dihapus karena masih memiliki peserta. Keluarkan peserta terlebih dahulu.",
  "errors": { "peserta": ["Sesi masih memiliki peserta."] }
}
```

---

## 7. Participants

### 7.1 List Participants

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi/{id}/peserta`
- **Role:** Super Admin, Admin Prodi, Dosbing

Admin Prodi sees participants from their prodi. Dosbing sees participants that
are their advisees.

### 7.2 Eligible Students

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi/{id}/mahasiswa-eligible`
- **Role:** Super Admin, Admin Prodi, Dosbing

Returns `berlangsung` magang on semester 6/7 that are not already in this
session and do not have another active schedule.

### 7.3 Add Participant

- **HTTP Method:** `POST`
- **Endpoint:** `/api/jadwal-presentasi/{id}/peserta`
- **Body:** `{ "magang_id": 12 }`
- **Role:** Super Admin, Admin Prodi, Dosbing

Validation is the same as schedule creation (eligibility, duplicate active
schedule, participant scope). Duplicate membership returns `409`.

### 7.4 Remove Participant

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/jadwal-presentasi/{id}/peserta/{magangId}`
- **Role:** Super Admin, Admin Prodi, Dosbing

Only the `jadwal_peserta` row is removed. The student, magang, and schedule
records are kept.
