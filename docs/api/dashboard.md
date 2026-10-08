# Dashboard API Reference

**Status:** IMPLEMENTED — RISKY/FLAWED
**Note:** Endpoints are separated by role but lack strict policy verification. Logic is heavily hardcoded.

---

## 1. Admin Dashboard

- **HTTP Method:** `GET`
- **Endpoint:** `/api/dashboard/admin`
- **Purpose:** Retrieve aggregate statistics for the Admin dashboard.
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Admin, Mahasiswa, Mitra, Dosbing (Due to generic middleware)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "jumlah_mahasiswa_aktif_magang": 45,
  "jumlah_mahasiswa": 120,
  "jumlah_mitra": 15,
  "jumlah_dosbing": 10
}
```

---

## 2. Mitra Dashboard

- **HTTP Method:** `GET`
- **Endpoint:** `/api/dashboard/mitra`
- **Purpose:** Retrieve aggregate statistics for the currently logged-in Mitra.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "jumlah_mahasiswa_magang_by_mitra": 5,
  "jumlah_penilaian": 3,
  "jumlah_magang": 5
}
```

### Response (Error)
- **Status Code:** `500 Internal Server Error` (If called by Admin or Mahasiswa, the controller crashes trying to resolve `auth()->user()->mitra->mitra_id`).

---

## 3. Mahasiswa Dashboard

- **HTTP Method:** `GET`
- **Endpoint:** `/api/dashboard/mahasiswa`
- **Purpose:** Retrieve progress and statistics for the currently logged-in Mahasiswa.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "status": "success",
  "data": {
    "magang_semester": 6,
    "persiapan_magang": 100,
    "pelaksanaan_magang": 100,
    "laporan_magang": 0
  }
}
```

### Response (Error)
- **Status Code:** `404 Not Found` (Returns `{"message": "Data mahasiswa tidak ditemukan"}` if user lacks a mahasiswa relation).

**Implementation Note:** The controller forcefully checks only `semester_magang` 6 and 7. If a student does an internship in a different semester, the endpoint will not calculate their progress correctly. It also checks for `doc_pra_krs` which is currently out-of-scope based on 2026 requirements.
