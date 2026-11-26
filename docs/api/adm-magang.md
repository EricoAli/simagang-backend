# ADM Magang API Reference

**Status:** IMPLEMENTED — BROKEN/RISKY
**Note:** All endpoints currently use `role:admin,mahasiswa,mitra,dosbing`, meaning Mahasiswa can access Admin endpoints (authorization gap).

---

## 1. Get List of Magang

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang`
- **Purpose:** Retrieve a paginated list of all magang entries. Used by Admin for monitoring, and filtered automatically for Mahasiswa.
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Admin, Mahasiswa, Mitra, Dosbing

### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `mahasiswa_id` | integer | (Optional) Filter by specific mahasiswa. |
| `mitra_id` | integer | (Optional) Filter by specific mitra. |
| `angkatan` | string | (Optional) Filter by angkatan. |
| `semester_magang` | integer | (Optional) Filter by semester. |
| `q` | string | (Optional) Search query on `jobdesk` or `role_magang`. |
| `penilaian` | string | (Optional) Filter by `none` (no mitra assessment) or `exist`. |
| `per_page` | integer | (Optional) Number of items per page. Default: 10. |

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "current_page": 1,
  "data": [
    {
      "magang_id": 1,
      "mahasiswa_id": 1,
      "mitra_id": 2,
      "dosbing_id": null,
      "tahun_ajaran": "2026",
      "semester_magang": 6,
      "role_magang": "Backend Developer",
      "jobdesk": "API Development",
      "status_magang": "berlangsung",
      "mahasiswa": { ... },
      "mitra": { ... },
      "dosenPembimbing": null
    }
  ],
  "per_page": 10
}
```
**Implementation Note:** If called by an Admin, the code crashes if the controller tries to implicitly access `$user->mahasiswa` in older controller logic (though `index` checks `$user->role === 'mahasiswa'`). 

---

## 2. Get Magang Detail (Basic)

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang/{id}`
- **Purpose:** Retrieve basic magang details by ID along with its relations (mahasiswa, mitra, dosbing).
- **Authentication:** Bearer Token (Sanctum)
- **Path Parameters:**
  - `id` (integer) - Magang ID

### Response (Success)
- **Status Code:** `200 OK`

---

## 3. Get Magang Detail (Full/Monitoring)

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang/{id}/detail`
- **Purpose:** Retrieve full magang details including logbook entries, mitra assessment, and uploaded documents.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "status": "success",
  "data": {
    "magang": { ... },
    "logbook": [ ... ],
    "penilaian": { ... },
    "dokumen": [ ... ],
    "laporan": { ... }
  }
}
```

---

## 4. Create Magang (Pendaftaran)

**Status:** CONFLICTING (Mahasiswa wajib mengunggah `doc_pra_krs`; dokumen PM menyatakan di luar scope).

- **HTTP Method:** `POST`
- **Endpoint:** `/api/magang`
- **Purpose:** Register a new magang. Typically done by Mahasiswa.
- **Authentication:** Bearer Token (Sanctum)

### Request Body (Multipart/form-data)
| Field | Type | Description |
|-------|------|-------------|
| `mitra_id` | integer | (Optional) ID of Mitra. |
| `semester_magang` | integer | Opsional; divalidasi terhadap master periode bila tersedia. |
| `role_magang` | string | (Optional) |
| `jobdesk` | string | (Optional) |
| `dokumenPenerimaan` | file (pdf) | **Wajib untuk mahasiswa.** Opsional untuk Admin. Max 5MB. |
| `dokumenPraKRS` | file (pdf) | **Wajib untuk mahasiswa.** Opsional untuk Admin. Max 5MB. (CONFLICT: Requirement says out of scope). |

### Response (Success)
- **Status Code:** `201 Created`

### Response (Error)
- **Status Code:** `422 Unprocessable Entity` (Validation failure)

**Implementation Note:** Admin membuat penempatan untuk mahasiswa lain dan wajib mengirim `mahasiswa_id`; mahasiswa memakai data miliknya sendiri.

---

## 5. Update Magang

- **HTTP Method:** `PUT` / `POST`
- **Endpoint:** `/api/magang/{id}`
- **Purpose:** Update magang data.
- **Authentication:** Bearer Token (Sanctum)

### Request Body (Multipart/form-data if uploading)
| Field | Type | Description |
|-------|------|-------------|
| `mahasiswa_id` | integer | (Optional) |
| `mitra_id` | integer | (Optional) |
| `dosbing_id` | integer | (Optional) |
| `semester_magang` | integer | (Optional) Harus sesuai periode master bila diisi. |
| `status_magang` | string | (Optional, hanya Admin/Super Admin) `draft`, `berlangsung`, `selesai`. Nilai lama `ditolak` hanya dipertahankan untuk data historis. |

> Update yang hanya mengirim `status_magang` tidak mengubah `periode_id`,
> `semester_magang`, `tahun_ajaran`, maupun relasi mahasiswa/mitra/dosbing.
> Data legacy tanpa `periode_id` juga tidak di-backfill otomatis oleh update
> status.

### Response (Success)
- **Status Code:** `200 OK`

---

## 6. Delete Magang (Batalkan Pendaftaran Draft)

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/magang/{id}`
- **Purpose:** Membatalkan pendaftaran magang yang masih berstatus `draft`.
- **Authentication:** Bearer Token (Sanctum)

### Authorization
| Role | Kewenangan |
|---|---|
| Super Admin | Boleh menghapus `draft` lintas prodi. |
| Admin Prodi | Boleh menghapus `draft` hanya pada prodi sendiri. |
| Mahasiswa | Boleh membatalkan `draft` miliknya sendiri. |
| Mitra | Ditolak `403`. |
| Dosen Pembimbing | Ditolak `403`. |

### Business Rules
1. Hanya status `draft` (Belum Mulai) yang boleh dihapus. Status `berlangsung`, `selesai`, dan `ditolak` ditolak dengan `422`.
2. Penghapusan ditolak `422` apabila masih terdapat data turunan:
   - dokumen magang,
   - logbook/foto kegiatan,
   - laporan magang,
   - penilaian mitra,
   - peserta jadwal presentasi.
3. Penghapusan hanya menghapus baris `magang`. Tidak ada cascade delete terhadap data turunan; data turunan harus kosong terlebih dahulu.
4. Sesi `jadwal_presentasi` tidak ikut dihapus dan tidak boleh kehilangan peserta secara tidak sengaja.
5. Data magang lain milik mahasiswa yang sama (periode lain) tidak terpengaruh.

### Response (Success)
- **Status Code:** `200 OK`
```json
{
  "message": "Data magang berhasil dihapus"
}
```

### Response (Error)
- `403 Forbidden` — role tidak berwenang atau data di luar prodi/kepemilikan.
- `404 Not Found` — data tidak ditemukan atau sudah dihapus.
- `422 Unprocessable Entity` — status bukan `draft` atau masih ada data turunan.
```json
{
  "message": "Data magang tidak dapat dihapus karena masih memiliki data turunan: logbook/foto kegiatan.",
  "errors": {
    "relations": ["logbook/foto kegiatan"]
  }
}
```
- `500 Internal Server Error` — kegagalan tak terduga dengan pesan umum (detail tidak dibocorkan).

**Implementation Note:** Validasi status, kepemilikan/prodi, dan relasi turunan dilakukan di backend sebelum transaksi penghapusan. Hook `Magang::deleting` tidak dijalankan pada alur ini agar tidak ada file yang terhapus sebelum transaksi berhasil.

---

## 7. Export Magang to Excel

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang/export/excel`
- **Purpose:** Export list to excel.
- **Query Parameters:** `angkatan`, `semester_magang`
- **Status Code:** `200 OK` (File Download)
