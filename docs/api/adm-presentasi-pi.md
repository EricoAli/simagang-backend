# ADM Presentasi PI API Reference

**Status:** IMPLEMENTED — BROKEN/DISCONNECTED
**Note:** The schema `jadwal_presentasi` lacks `magang_id` or `mahasiswa_id`. A schedule can be created, but it cannot be linked to a specific student's presentation.

---

## 1. Get List of Jadwal Presentasi

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi`
- **Purpose:** Retrieve a paginated list of presentation schedules.
- **Authentication:** Bearer Token (Sanctum)
- **Role:** Admin, Mahasiswa, Mitra, Dosbing

### Query Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | (Optional) Filter by status (`terjadwal`, `selesai`, `dibatalkan`). |
| `tanggal_presentasi` | date (Y-m-d) | (Optional) Filter by date. |
| `per_page` | integer | (Optional) Number of items per page. Default: 15. |

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "current_page": 1,
  "data": [
    {
      "jadwal_id": 1,
      "tanggal_presentasi": "2026-10-15",
      "waktu_mulai": "09:00",
      "waktu_selesai": "11:00",
      "tempat": null,
      "ruangan": "Ruang Rapat 1",
      "keterangan": "Presentasi akhir",
      "status": "terjadwal",
      "created_at": "...",
      "updated_at": "..."
    }
  ],
  "per_page": 15
}
```

---

## 2. Get Jadwal Presentasi Detail

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Purpose:** Retrieve details of a specific schedule.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`

---

## 3. Create Jadwal Presentasi

- **HTTP Method:** `POST`
- **Endpoint:** `/api/jadwal-presentasi`
- **Purpose:** Create a new presentation schedule.
- **Authentication:** Bearer Token (Sanctum)

### Request Body (JSON or Form-Data)
| Field | Type | Description |
|-------|------|-------------|
| `tanggal_presentasi` | date | **Required.** Format: Y-m-d |
| `waktu_mulai` | string | **Required.** Format: H:i |
| `waktu_selesai` | string | **Required.** Format: H:i (Must be after `waktu_mulai`) |
| `ruangan` | string | **Required.** Max 100 chars. |
| `keterangan` | string | (Optional) |

**Note:** No `magang_id` is accepted or validated because the database table lacks this column.

### Response (Success)
- **Status Code:** `201 Created`
- **Body Example:**
```json
{
  "message": "Jadwal presentasi berhasil dibuat",
  "data": { ... }
}
```

### Response (Error)
- **Status Code:** `422 Unprocessable Entity`
- **Body Example:**
```json
{
  "title": "Validasi gagal",
  "message": "Waktu mulai tidak dapat melebihi waktu akhir"
}
```

---

## 4. Update Jadwal Presentasi

- **HTTP Method:** `PUT`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Purpose:** Update existing presentation schedule.
- **Authentication:** Bearer Token (Sanctum)

### Request Body (JSON)
| Field | Type | Description |
|-------|------|-------------|
| `tanggal_presentasi` | date | (Optional) |
| `waktu_mulai` | string | (Optional) |
| `waktu_selesai` | string | (Optional) |
| `ruangan` | string | **Required.** |
| `keterangan` | string | (Optional) |

### Response (Success)
- **Status Code:** `200 OK`

---

## 5. Delete Jadwal Presentasi

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/jadwal-presentasi/{id}`
- **Purpose:** Delete a schedule.
- **Authentication:** Bearer Token (Sanctum)

### Response (Success)
- **Status Code:** `200 OK`
- **Body Example:**
```json
{
  "message": "Jadwal presentasi berhasil dihapus"
}
```
