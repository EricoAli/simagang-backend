# SIMAGANG API Reference (2026 Target)

Dokumen ini adalah referensi API utama untuk SIMAGANG DTEDI, hasil verifikasi terhadap source code baseline saat ini (Laravel 13, Oktober 2026).

## Tujuan Dokumentasi
Memberikan panduan teknis berstandar Postman untuk Project Manager dan Frontend Developer. Dokumentasi ini hanya memuat behavior API *aktual* yang telah diimplementasikan dan diuji dengan automated testing, bukan sekadar rencana dari dokumen requirements lama.

## Scope
Scope dokumentasi saat ini mencakup:
- Modul Super Admin (Manajemen Admin Prodi & Dashboard Multi-Prodi)
- Modul Admin (Authentication, Provisioning Prodi, & Dashboard Terisolasi)
- Modul Mahasiswa (Manajemen Data Mahasiswa dengan Isolasi Multi-Prodi)
- Penguatan Otorisasi Lintas Prodi (Magang, Logbook, Jadwal Presentasi, Laporan, Dokumen)

## Authentication Overview
Seluruh endpoint di bawah `/api` diamankan menggunakan **Laravel Sanctum**.
- Klien harus menyertakan header `Authorization: Bearer <token>`.
- Token didapat melalui endpoint login (`POST /api/auth/login`).
- Akun yang dinonaktifkan (`is_active = false`) otomatis ditolak saat login (403) dan token aktifnya dicabut.

## Role Overview
- **Super Admin (`super_admin`)**: Bertindak sebagai pengawas tingkat departemen. Memiliki wewenang mengelola akun Admin Prodi dan melihat dashboard agregat 4 prodi.
- **Admin Prodi (`admin`)**: Bertindak sebagai pengelola program studi (Prodi). Memiliki akses hanya ke data mahasiswa dan magang prodinya sendiri.
- **Mahasiswa (`mahasiswa`)**: Bertindak sebagai end-user. Hanya memiliki akses ke data miliknya sendiri.
- **Mitra (`mitra`)** & **Dosen Pembimbing (`dosbing`)**: Peran pendukung operasional magang.

## Ringkasan Endpoint & Status

| Modul | Endpoint | Method | Fungsi | Status |
|---|---|---|---|---|
| **Super Admin** | `/api/super-admin/admin-prodi` | GET | List akun Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/admin-prodi` | POST | Buat akun Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/admin-prodi/{id}` | GET | Detail akun Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/admin-prodi/{id}` | PUT | Update akun Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/admin-prodi/{id}/status` | PATCH | Aktifkan/nonaktifkan Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/dashboard` | GET | Dashboard agregat & filter prodi | IMPLEMENTED — TESTED |
| Admin | Provisioning Seeder / Artisan | N/A | Inisialisasi Admin per Prodi | IMPLEMENTED — TESTED |
| Admin | `/api/dashboard/admin` | GET | Dashboard Admin (Isolasi Prodi) | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa` | GET | List mahasiswa (Isolasi Prodi) | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/show` | GET | Detail profil (Self) | IMPLEMENTED — NOT VERIFIED |
| Mahasiswa | `/api/mahasiswa/{id}` | GET | Detail mahasiswa (Isolasi Prodi) | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa` | POST | Tambah mahasiswa baru | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/update` | POST | Update mahasiswa | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/{id}` | DELETE | Hapus mahasiswa | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/import` | POST | Import via CSV | IMPLEMENTED — NOT VERIFIED |
| Magang | `/api/magang/*` | GET/POST/PUT/DELETE | CRUD Magang (Isolasi Prodi & Hak Akses) | IMPLEMENTED — TESTED |
| Logbook | `/api/logbook/*` | GET/POST/PUT/DELETE | CRUD Logbook (Isolasi Prodi & Hak Akses) | IMPLEMENTED — TESTED |
| Dokumen Magang | `/api/dokumen-magang/{id}/download` | GET | Download Dokumen Fisik (Private Storage) | IMPLEMENTED — TESTED |
| Laporan | `/api/laporan/{id}` | GET | Detail Laporan (Mendukung `laporan_id` / `magang_id`) | IMPLEMENTED — TESTED |
| Laporan | `/api/laporan/{id}/download` | GET | Download Laporan Fisik (Private Storage) | IMPLEMENTED — TESTED |
| Jadwal Presentasi | `/api/jadwal-presentasi` | GET | List Jadwal (Isolasi Prodi / Mhs Self) | IMPLEMENTED — TESTED |
| Jadwal Presentasi | `/api/jadwal-presentasi` | POST | Buat Jadwal (Validasi Prodi & magang_id) | IMPLEMENTED — TESTED |
| Jadwal Presentasi | `/api/jadwal-presentasi/{id}` | GET | Detail Jadwal (Isolasi Prodi / Mhs Self) | IMPLEMENTED — TESTED |
| Jadwal Presentasi | `/api/jadwal-presentasi/{id}` | PUT | Update Jadwal (Validasi target magang_id) | IMPLEMENTED — TESTED |
| Jadwal Presentasi | `/api/jadwal-presentasi/{id}` | DELETE | Hapus Jadwal (Isolasi Prodi & Super Admin) | IMPLEMENTED — TESTED |

## API Reference Detail
- [**Super Admin API & Dashboard**](./super-admin.md)
- [**Admin API & Provisioning**](./admin.md)
- [**Mahasiswa API**](./mahasiswa.md)
- [**Jadwal Presentasi & Otorisasi**](./README.md#jadwal-presentasi-rules)

### Jadwal Presentasi Rules
1. **Relasi Data:** Setiap jadwal baru wajib berelasi dengan `magang_id`. Foreign key dikonfigurasi dengan `ON DELETE SET NULL` agar jadwal historis tetap tersimpan jika data magang dihapus.
2. **Admin Prodi:** Hanya dapat melihat, membuat, mengubah, dan menghapus jadwal mahasiswa prodinya. Ditolak jika mengubah `magang_id` ke mahasiswa prodi lain.
3. **Mahasiswa:** Hanya dapat melihat jadwal untuk magang miliknya sendiri (read-only).
4. **Super Admin:** Memiliki hak penuh melihat, membuat, mengubah, dan menghapus seluruh jadwal di seluruh prodi, termasuk mengelola jadwal historis tanpa relasi (`magang_id = NULL`).
5. **Mitra & Dosen Pembimbing:** Ditolak dengan HTTP 403 Forbidden.

---

## Known Limitations & Catatan Keputusan

1. **Nama Resmi Prodi (NEED REVIEW)**: Nama panjang untuk prodi TRIK, TRI, dan TRE belum dikonfirmasi dari spesifikasi 2026, sehingga data master saat ini masih menggunakan singkatan.
2. **Sinkronisasi Postman Collection**: Repository saat ini hanya memuat rencana Postman (`docs/08-postman-plan.md`). File collection JSON Postman belum tersedia secara lokal di repo, sehingga pembaruan workspace Postman memerlukan import/sinkronisasi manual berdasarkan dokumen referensi di folder `docs/api/`.
