# API Super Admin & Multi-Prodi

**Status:** IMPLEMENTED — TESTED
**Authentication:** Bearer Token (Sanctum)
**Role Whitelist:** `super_admin` only

Dokumen ini adalah referensi Postman-ready untuk seluruh endpoint operasional Super Admin, mencakup manajemen akun Admin Prodi serta pemantauan dashboard agregat dan per prodi.

---

## 1. Get List Admin Prodi

- **Method:** `GET`
- **URL:** `/api/super-admin/admin-prodi`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Fungsi:** Mengambil daftar seluruh akun Admin Prodi dengan dukungan pagination, pencarian nama/username/email, filter prodi, dan filter status aktif.

### Query Parameters
| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `q` | string | Optional | Pencarian berdasarkan nama admin, username, atau email |
| `prodi_id` | integer | Optional | Filter berdasarkan ID prodi (1: TRPL, 2: TRIK, 3: TRI, 4: TRE) |
| `is_active` | boolean | Optional | Filter status keaktifan (`true` / `false` / `1` / `0`) |
| `per_page` | integer | Optional | Jumlah data per halaman (default: 10) |

### Response Success (200 OK)
```json
{
  "current_page": 1,
  "data": [
    {
      "admin_id": 1,
      "user_id": 2,
      "prodi_id": 1,
      "nama": "Koordinator Magang TRPL",
      "user": {
        "user_id": 2,
        "username": "admin_trpl",
        "email": "admin.trpl@simagang.dev",
        "role": "admin",
        "is_active": true,
        "created_at": "2026-10-12T00:00:00.000000Z"
      },
      "prodi": {
        "prodi_id": 1,
        "kode_prodi": "TRPL",
        "nama_prodi": "Teknologi Rekayasa Perangkat Lunak"
      }
    }
  ],
  "per_page": 10,
  "total": 1
}
```

---

## 2. Tambah Akun Admin Prodi Baru

- **Method:** `POST`
- **URL:** `/api/super-admin/admin-prodi`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Fungsi:** Membuat akun pengelola program studi baru. Proses dilakukan dalam transaksi database (membuat user di tabel `users` dengan role `admin` dan entri di tabel `data_admin`). Password disimpan dalam bentuk hash bcrypt.

### Request Body (JSON)
| Field | Type | Required | Aturan Validasi |
|---|---|---|---|
| `username` | string | Required | Maks. 50 karakter, unik di tabel `users` |
| `email` | string | Required | Format email valid, unik di tabel `users` |
| `password` | string | Required | Minimal 6 karakter |
| `nama` | string | Required | Maks. 100 karakter |
| `prodi_id` | integer | Required | Harus ada di tabel `prodi` |

### Contoh Request Body
```json
{
  "username": "admin_trik_2",
  "email": "admin.trik2@simagang.dev",
  "password": "SecretPassword123!",
  "nama": "Staf Pengelola Magang TRIK",
  "prodi_id": 2
}
```

### Response Success (201 Created)
```json
{
  "message": "Akun Admin Prodi berhasil dibuat",
  "data": {
    "admin_id": 5,
    "user_id": 10,
    "prodi_id": 2,
    "nama": "Staf Pengelola Magang TRIK",
    "user": {
      "user_id": 10,
      "username": "admin_trik_2",
      "email": "admin.trik2@simagang.dev",
      "role": "admin",
      "is_active": true
    },
    "prodi": {
      "prodi_id": 2,
      "kode_prodi": "TRIK",
      "nama_prodi": "TRIK"
    }
  }
}
```

### Response Error (422 Unprocessable Entity)
```json
{
  "title": "Validasi Gagal",
  "message": "Username sudah terdaftar",
  "errors": {
    "username": ["Username sudah terdaftar"]
  }
}
```

---

## 3. Detail Akun Admin Prodi

- **Method:** `GET`
- **URL:** `/api/super-admin/admin-prodi/{id}`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Path Parameter:** `id` (integer) — `admin_id`

### Response Success (200 OK)
```json
{
  "admin_id": 1,
  "user_id": 2,
  "prodi_id": 1,
  "nama": "Koordinator Magang TRPL",
  "user": {
    "user_id": 2,
    "username": "admin_trpl",
    "email": "admin.trpl@simagang.dev",
    "role": "admin",
    "is_active": true,
    "created_at": "2026-10-12T00:00:00.000000Z"
  },
  "prodi": {
    "prodi_id": 1,
    "kode_prodi": "TRPL",
    "nama_prodi": "Teknologi Rekayasa Perangkat Lunak"
  }
}
```

---

## 4. Update Akun Admin Prodi

- **Method:** `PUT`
- **URL:** `/api/super-admin/admin-prodi/{id}`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Path Parameter:** `id` (integer) — `admin_id`
- **Fungsi:** Memperbarui data profil, email, penempatan `prodi_id`, atau password Admin Prodi.

### Request Body (JSON)
| Field | Type | Required | Keterangan |
|---|---|---|---|
| `nama` | string | Optional | Nama lengkap admin |
| `email` | string | Optional | Email baru (harus unik jika berubah) |
| `prodi_id` | integer | Optional | ID program studi valid |
| `password` | string | Optional | Password baru (min. 6 karakter) |

### Contoh Request Body
```json
{
  "nama": "Koordinator Magang TRPL (Pembaruan)",
  "email": "koordinator.trpl@simagang.dev"
}
```

### Response Success (200 OK)
```json
{
  "message": "Akun Admin Prodi berhasil diperbarui",
  "data": {
    "admin_id": 1,
    "user_id": 2,
    "prodi_id": 1,
    "nama": "Koordinator Magang TRPL (Pembaruan)",
    "user": {
      "user_id": 2,
      "username": "admin_trpl",
      "email": "koordinator.trpl@simagang.dev",
      "role": "admin",
      "is_active": true
    },
    "prodi": {
      "prodi_id": 1,
      "kode_prodi": "TRPL",
      "nama_prodi": "Teknologi Rekayasa Perangkat Lunak"
    }
  }
}
```

---

## 5. Nonaktifkan / Aktifkan Akun Admin Prodi

- **Method:** `PATCH`
- **URL:** `/api/super-admin/admin-prodi/{id}/status`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Path Parameter:** `id` (integer) — `admin_id`
- **Fungsi:** Mengubah status aktif/nonaktif akun Admin Prodi. Saat akun dinonaktifkan (`is_active: false`), sistem otomatis mencabut seluruh token aktif user tersebut sehingga tidak dapat digunakan kembali.

### Request Body (JSON)
```json
{
  "is_active": false
}
```

### Response Success (200 OK)
```json
{
  "message": "Akun Admin Prodi berhasil dinonaktifkan",
  "data": {
    "admin_id": 1,
    "user_id": 2,
    "username": "admin_trpl",
    "is_active": false
  }
}
```

---

## 6. Dashboard Super Admin

- **Method:** `GET`
- **URL:** `/api/super-admin/dashboard`
- **Authentication:** Bearer Token
- **Role:** Super Admin
- **Fungsi:** Menampilkan metrik statistik magang dan mahasiswa. Menyediakan dua mode: agregat 4 prodi (default) atau filter terfokus pada 1 prodi tertentu.

### Query Parameters
| Parameter | Type | Required | Keterangan |
|---|---|---|---|
| `prodi_id` | integer \| string | Optional | Kosongkan untuk mode agregat seluruh prodi, atau isi `1`..`4` untuk memfilter prodi spesifik |

### Contoh 1: Mode Agregat Seluruh Prodi (`GET /api/super-admin/dashboard`)
```json
{
  "mode": "agregat",
  "statistik_keseluruhan": {
    "total_mahasiswa": 120,
    "total_mahasiswa_aktif_magang": 85,
    "semester_magang_terbaru": 7,
    "total_admin_prodi": 4,
    "total_mitra": 25,
    "total_dosbing": 18
  },
  "breakdown_per_prodi": [
    {
      "prodi_id": 1,
      "kode_prodi": "TRPL",
      "nama_prodi": "Teknologi Rekayasa Perangkat Lunak",
      "jumlah_mahasiswa": 45,
      "jumlah_mahasiswa_aktif_magang": 32,
      "jumlah_admin_prodi": 1
    },
    {
      "prodi_id": 2,
      "kode_prodi": "TRIK",
      "nama_prodi": "TRIK",
      "jumlah_mahasiswa": 30,
      "jumlah_mahasiswa_aktif_magang": 20,
      "jumlah_admin_prodi": 1
    },
    {
      "prodi_id": 3,
      "kode_prodi": "TRI",
      "nama_prodi": "TRI",
      "jumlah_mahasiswa": 25,
      "jumlah_mahasiswa_aktif_magang": 18,
      "jumlah_admin_prodi": 1
    },
    {
      "prodi_id": 4,
      "kode_prodi": "TRE",
      "nama_prodi": "TRE",
      "jumlah_mahasiswa": 20,
      "jumlah_mahasiswa_aktif_magang": 15,
      "jumlah_admin_prodi": 1
    }
  ]
}
```

### Contoh 2: Mode Filter Spesifik (`GET /api/super-admin/dashboard?prodi_id=1`)
```json
{
  "mode": "filter",
  "prodi": {
    "prodi_id": 1,
    "kode_prodi": "TRPL",
    "nama_prodi": "Teknologi Rekayasa Perangkat Lunak"
  },
  "statistik": {
    "jumlah_mahasiswa": 45,
    "jumlah_mahasiswa_aktif_magang": 32,
    "semester_magang_terbaru": 7,
    "jumlah_admin_prodi": 1,
    "jumlah_mitra": 25,
    "jumlah_dosbing": 18
  }
}
```

> [!IMPORTANT]
> **Keterbatasan Relasi Mitra & Dosen pada Filter Dashboard:**
> Pada response mode filter (`?prodi_id=...`), nilai `jumlah_mitra` dan `jumlah_dosbing` **tetap mencerminkan jumlah global institusional tingkat DTEDI**, bukan statistik khusus prodi tersebut. Hal ini disebabkan karena pada skema basis data saat ini, tabel `mitra` dan `dosen_pembimbing` belum memiliki relasi langsung `prodi_id`. Pengembang frontend dan PM tidak boleh mengasumsikan angka tersebut sebagai metrik spesifik per program studi.
