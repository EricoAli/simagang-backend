# API Mahasiswa

Endpoint ini mengatur master data mahasiswa dan telah menerapkan arsitektur isolasi multi-prodi. Admin hanya dapat berinteraksi dengan mahasiswa yang terdaftar di dalam prodinya.

---

## 1. Dapatkan Daftar Mahasiswa

Status: IMPLEMENTED — TESTED

### Method
`GET`

### URL
`/api/mahasiswa`

### Authentication
Bearer Token (Sanctum)

### Role
Admin, Mahasiswa

### Fungsi
Mengambil data daftar mahasiswa. 

### Authorization / Prodi Scope
- **Admin**: Hasil query *list* langsung difilter di level database. Admin hanya akan menerima mahasiswa dari prodinya.
- **Mahasiswa**: Hasil query difilter agar hanya mengembalikan satu data, yaitu dirinya sendiri.

### Request

**Query Parameters:**
| Field | Tipe Data | Required/Optional | Keterangan |
|-------|-----------|-------------------|------------|
| `q` | string | Optional | Pencarian berdasarkan `nama` atau `nim` |
| `angkatan` | integer | Optional | Filter mahasiswa dari tahun angkatan tertentu |
| `semester_magang` | integer | Optional | Filter mahasiswa yang memiliki entri magang di semester ini |
| `sort_by` | string | Optional | Kolom untuk sorting (Default: `mahasiswa_id`) |
| `sort_order` | string | Optional | Arah sorting: `asc` atau `desc` (Default: `desc`) |
| `per_page` | integer | Optional | Limit pagination (Default: `10`) |

### Response Success
**HTTP Status:** `200 OK`

```json
{
  "current_page": 1,
  "data": [
    {
      "mahasiswa_id": 5,
      "user_id": 12,
      "prodi_id": 1,
      "nim": "24/12345/SV/54321",
      "nama": "Budi Santoso",
      "angkatan": "2024",
      "created_at": "2026-10-08T15:20:00.000000Z",
      "updated_at": "2026-10-08T15:20:00.000000Z",
      "prodi": {
        "prodi_id": 1,
        "kode_prodi": "TRPL",
        "nama_prodi": "Teknologi Rekayasa Perangkat Lunak"
      }
    }
  ],
  "first_page_url": "...",
  "from": 1,
  "last_page": 1,
  "per_page": 10,
  "total": 1
}
```

---

## 2. Lihat Detail Mahasiswa (Diri Sendiri)

Status: IMPLEMENTED — NOT VERIFIED (belum di-cover automated test)

### Method
`GET`

### URL
`/api/mahasiswa/show`

### Authentication
Bearer Token (Sanctum)

### Role
Mahasiswa

### Fungsi
Mendapatkan detail profil dari mahasiswa yang sedang login.

### Request
*(Tidak memerlukan parameter/body)*

### Response Success
**HTTP Status:** `200 OK`

```json
{
  "mahasiswa_id": 5,
  "user_id": 12,
  "prodi_id": 1,
  "nim": "24/12345/SV/54321",
  "nama": "Budi Santoso",
  "prodi": {
    "kode_prodi": "TRPL"
  }
}
```

---

## 3. Lihat Detail Mahasiswa Spesifik

Status: IMPLEMENTED — TESTED

### Method
`GET`

### URL
`/api/mahasiswa/{id}`

### Authentication
Bearer Token (Sanctum)

### Role
Admin, Mahasiswa

### Fungsi
Melihat rincian penuh suatu data mahasiswa berdasarkan `mahasiswa_id`.

### Authorization / Prodi Scope
- Admin yang mengakses mahasiswa dari prodi lain akan ditolak (`403 Forbidden`). Test `admin_trpl_cannot_view_detail_mahasiswa_trik` telah membuktikan ini.
- Mahasiswa yang mengakses `id` milik mahasiswa lain akan ditolak (`403 Forbidden`).

### Request

**Path Parameter:**
| Field | Tipe Data | Keterangan |
|-------|-----------|------------|
| `id` | integer | ID Mahasiswa |

### Response Success
**HTTP Status:** `200 OK`

### Error Response
**HTTP Status:** `403 Forbidden`

```json
{
  "message": "Unauthorized. Mahasiswa bukan dari prodi anda."
}
```

---

## 4. Tambah Mahasiswa Baru

Status: IMPLEMENTED — TESTED

### Method
`POST`

### URL
`/api/mahasiswa`

### Authentication
Bearer Token (Sanctum)

### Role
Admin

### Fungsi
Mendaftarkan mahasiswa baru secara individu dan meng-generate akun `user` untuknya (password default diambil dari porsi NIF pada NIM).

### Authorization / Prodi Scope
- Saat *create*, prodi mahasiswa **otomatis** berasal dari prodi Admin yang sedang login (sebagaimana dibuktikan oleh test `admin_trpl_create_mahasiswa_defaults_to_trpl`).
- Memasukkan `prodi_id` di request body tidak akan berpengaruh (di-ignore) (dibuktikan oleh test `client_sending_prodi_id_is_ignored`).

### Request

**Body (JSON):**
| Field | Tipe Data | Required/Optional | Keterangan |
|-------|-----------|-------------------|------------|
| `nim` | string | **Required** | Harus format: `YY/NIU/Fakultas/NIF`. Harus Unik. |
| `nama` | string | **Required** | Nama lengkap mahasiswa. |

**Contoh Request:**
```json
{
  "nim": "24/12345/SV/54321",
  "nama": "Andi Darmawan"
}
```

### Response Success
**HTTP Status:** `201 Created`

```json
{
  "message": "Mahasiswa berhasil ditambahkan.",
  "data": {
    "mahasiswa_id": 6,
    "user_id": 15,
    "prodi_id": 1,
    "nim": "24/12345/SV/54321",
    "nama": "Andi Darmawan",
    "angkatan": "2024",
    "user": {
      "user_id": 15,
      "username": "12345",
      "role": "mahasiswa"
    },
    "prodi": {
      "kode_prodi": "TRPL"
    }
  }
}
```

### Error Response
**HTTP Status:** `422 Unprocessable Entity` (Jika format NIM salah atau NIM sudah ada)

```json
{
  "title": "Validasi Gagal",
  "message": "Mahasiswa dengan NIM tersebut telah terdaftar"
}
```

---

## 5. Update Mahasiswa

Status: IMPLEMENTED — TESTED

### Method
`POST` *(Note: Backend saat ini menggunakan POST untuk update di endpoint ini)*

### URL
`/api/mahasiswa/update`

### Authentication
Bearer Token (Sanctum)

### Role
Admin, Mahasiswa

### Fungsi
Memodifikasi data NIM, Nama, dan Angkatan mahasiswa. 

### Authorization / Prodi Scope
Admin dilarang (403) meng-update mahasiswa dari prodi lain.

### Request

**Body (JSON):**
| Field | Tipe Data | Required/Optional | Keterangan |
|-------|-----------|-------------------|------------|
| `mahasiswa_id`| integer | **Required** | Target mahasiswa yang akan diubah |
| `nim` | string | **Required** | NIM Baru/Lama |
| `nama` | string | **Required** | Nama Baru/Lama |
| `angkatan` | integer | Optional | Tahun angkatan |

### Response Success
**HTTP Status:** `200 OK`

```json
{
  "message": "Mahasiswa berhasil diperbarui",
  "data": {
    "mahasiswa_id": 6,
    "nim": "24/12345/SV/99999",
    "nama": "Andi Darmawan Updated"
  }
}
```

### Error Response (Beda Prodi)
**HTTP Status:** `403 Forbidden`

```json
{
  "message": "Unauthorized. Mahasiswa bukan dari prodi anda."
}
```

---

## 6. Hapus Mahasiswa

Status: IMPLEMENTED — TESTED

### Method
`DELETE`

### URL
`/api/mahasiswa/{id}`

### Authentication
Bearer Token (Sanctum)

### Role
Admin

### Fungsi
Menghapus record mahasiswa beserta akun user-nya secara permanen.

### Authorization / Prodi Scope
Admin tidak dapat menghapus mahasiswa dari prodi lain (`403 Forbidden`).

### Request

**Path Parameter:**
| Field | Tipe Data | Keterangan |
|-------|-----------|------------|
| `id` | integer | ID Mahasiswa |

### Response Success
**HTTP Status:** `200 OK`

```json
{
  "message": "Mahasiswa berhasil dihapus"
}
```

---

## 7. Import Data Mahasiswa (CSV)

Status: IMPLEMENTED — NOT VERIFIED (Belum ada test otomatis)

### Method
`POST`

### URL
`/api/mahasiswa/import`

### Authentication
Bearer Token (Sanctum)

### Role
Admin

### Fungsi
Mengimpor data mahasiswa dari file CSV dengan delimeter `;`.
Semua mahasiswa yang diimpor akan otomatis dimasukkan ke dalam `prodi_id` milik Admin yang melakukan unggahan.

### Request
**Body (FormData):**
- `file`: File berektensi `.csv` atau `.txt` (Required)

### Response Success
**HTTP Status:** `200 OK`

```json
{
  "message": "Import berhasil",
  "imported": 10,
  "errors": []
}
```
