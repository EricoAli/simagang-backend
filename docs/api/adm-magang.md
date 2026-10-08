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

**Status:** CONFLICTING (Forces `doc_pra_krs` which PM docs say is out of scope. Hardcodes semester 6 and 7).

- **HTTP Method:** `POST`
- **Endpoint:** `/api/magang`
- **Purpose:** Register a new magang. Typically done by Mahasiswa.
- **Authentication:** Bearer Token (Sanctum)

### Request Body (Multipart/form-data)
| Field | Type | Description |
|-------|------|-------------|
| `mitra_id` | integer | (Optional) ID of Mitra. |
| `semester_magang` | integer | **Must be 6 or 7.** |
| `role_magang` | string | (Optional) |
| `jobdesk` | string | (Optional) |
| `dokumenPenerimaan` | file (pdf) | **Required.** Max 5MB. |
| `dokumenPraKRS` | file (pdf) | **Required.** Max 5MB. (CONFLICT: Requirement says out of scope). |

### Response (Success)
- **Status Code:** `201 Created`

### Response (Error)
- **Status Code:** `422 Unprocessable Entity` (Validation failure)

**Implementation Note:** Crashes if called by Admin because it explicitly assigns `mahasiswa_id` via `$request->user()->mahasiswa->mahasiswa_id`.

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
| `semester_magang` | integer | (Optional) Must be 6 or 7. |
| `status_magang` | string | (Optional) `draft`, `berlangsung`, `selesai`, `ditolak`. |

### Response (Success)
- **Status Code:** `200 OK`

---

## 6. Delete Magang

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/magang/{id}`
- **Purpose:** Delete a magang record.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "message": "Data magang berhasil dihapus"
}
```
**Implementation Note:** Lacks ownership validation. Any user can delete any magang record.

---

## 7. Export Magang to Excel

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang/export/excel`
- **Purpose:** Export list to excel.
- **Query Parameters:** `angkatan`, `semester_magang`
- **Status Code:** `200 OK` (File Download)
