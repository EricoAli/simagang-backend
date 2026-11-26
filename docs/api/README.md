# SIMAGANG API Reference (2026 Target)

Dokumen ini adalah referensi API utama untuk SIMAGANG DTEDI (Laravel 13, revisi November 2026), selaras dengan repository FE (`dev`).

## Tujuan Dokumentasi
Memberikan panduan teknis berstandar Postman untuk Project Manager dan Frontend Developer berdasarkan implementasi controller, model, migrasi, route, dan automated tests yang aktif saat ini.

## Scope
Scope dokumentasi saat ini mencakup:
- Master Prodi (`GET /api/prodi`) & Master Tahun Ajaran / Periode Magang Dinamis
- Modul Super Admin (Manajemen Admin Prodi, Master Tahun Ajaran & Periode, & Dashboard Multi-Prodi)
- Modul Admin (Authentication, Provisioning Prodi, & Dashboard Terisolasi + `aktivitas_terbaru`)
- Modul Magang, Presentasi PI Multi-Peserta, Dosen Pembimbing, Mitra, Penilaian Mitra & Export Excel

## Authentication Overview
Seluruh endpoint di bawah `/api` diamankan menggunakan **Laravel Sanctum**.
- Klien harus menyertakan header `Authorization: Bearer <token>`.
- Token didapat melalui endpoint login (`POST /api/auth/login`) yang turut me-load `mitra`, `mahasiswa.prodi`, dan `admin.prodi`.
- Akun yang dinonaktifkan (`is_active = false`) otomatis ditolak saat login (403) dan token aktifnya dicabut.

## Role Overview
- **Super Admin (`super_admin`)**: Pengawas tingkat departemen; mengelola akun Admin Prodi, master Tahun Ajaran & Periode Magang, memantau dashboard agregat 4 prodi, serta memiliki hak koreksi penuh lintas prodi (termasuk hapus darurat magang).
- **Admin Prodi (`admin`)**: Pengelola program studi (`TRPL`, `TRIK`, `TRI`, `TRE`); akses terisolasi pada data mahasiswa, magang, dosen, dokumen, logbook, laporan, penilaian mitra, dan jadwal presentasi prodinya.
- **Mahasiswa (`mahasiswa`)**: End-user; mendaftar & mengelola magang sendiri sebelum dikonfirmasi (`draft`/`ditolak`), mengisi logbook/laporan, melihat jadwal presentasi.
- **Mitra (`mitra`)**: Melihat mahasiswa magang yang ditempatkan di mitranya, memberi/memperbarui penilaian, dan mengekspor Excel magang mitranya.

## Ringkasan Endpoint & Status

| Modul | Endpoint | Method | Fungsi | Status |
|---|---|---|---|---|
| **Prodi** | `/api/prodi` | GET | Daftar 4 program studi DTEDI | IMPLEMENTED — TESTED |
| **Tahun Ajaran** | `/api/tahun-ajaran`, `/{id}` | GET/POST/PUT/PATCH/DELETE | Master tahun ajaran (`YYYY/YYYY`) & toggle aktif | IMPLEMENTED — TESTED |
| **Periode Magang** | `/api/periode-magang`, `/{id}` | GET/POST/PUT/PATCH/DELETE | Master semester & `Gasal`/`Genap` per tahun ajaran | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/admin-prodi/*` | GET/POST/PUT/PATCH | Manajemen akun Admin Prodi | IMPLEMENTED — TESTED |
| **Super Admin** | `/api/super-admin/dashboard` | GET | Dashboard agregat & filter `prodi_id` / `periode_id` / `tahun_ajaran_id` | IMPLEMENTED — TESTED |
| **Dashboard** | `/api/dashboard/admin` | GET | Statistik Admin/SuperAdmin + `aktivitas_terbaru` | IMPLEMENTED — TESTED |
| **Dashboard** | `/api/dashboard/mitra` | GET | Statistik Mitra (mendukung filter periode) | IMPLEMENTED — TESTED |
| **Dosbing** | `/api/dosbing/*` | GET/POST/PUT/DELETE | Master dosen (`prodi_id` opsional; write `admin,super_admin`) | IMPLEMENTED — TESTED |
| **Mitra** | `/api/mitra/*` | GET/POST/PUT/DELETE | Master mitra + filter `periode_id` & search nama/NIM intern | IMPLEMENTED — TESTED |
| **Magang** | `/api/magang/*` | GET/POST/PUT/DELETE | Manajemen magang (periode dinamis, proteksi status & hapus) | IMPLEMENTED — TESTED |
| **Magang Export** | `/api/magang/export/excel` | GET | Export Excel 1 sheet terurut tahun ajaran (nama file tersanitasi) | IMPLEMENTED — TESTED |
| **Jadwal Presentasi** | `/api/jadwal-presentasi/*` | GET/POST/PUT/DELETE | Sesi presentasi + `dosen_penguji_id` & filter `dosbing_id` | IMPLEMENTED — TESTED |
| **Presentasi Peserta** | `/api/jadwal-presentasi/{id}/peserta` | GET/POST/DELETE | Kelola multi-peserta sesi & kandidat eligible | IMPLEMENTED — TESTED |
| **Presentasi Advisees** | `/api/jadwal-presentasi/advisees` | GET | Daftar status penjadwalan mahasiswa bimbingan | IMPLEMENTED — TESTED |

## API Reference Detail
- [**Super Admin, Tahun Ajaran & Periode API**](./super-admin.md)
- [**Admin API & Provisioning**](./admin.md)
- [**ADM Magang API**](./adm-magang.md)
- [**ADM Presentasi PI API**](./adm-presentasi-pi.md)
- [**Dashboard API**](./dashboard.md)
- [**Mahasiswa API**](./mahasiswa.md)

### Jadwal Presentasi Rules
1. **Sesi & Peserta:** Sesi presentasi mendukung `dosen_penguji_id` serta relasi multi-peserta pada tabel `jadwal_presentasi_peserta` (dengan kompatibilitas kolom `magang_id` `ON DELETE SET NULL`).
2. **Aturan Penguji vs Pembimbing:** Dosen penguji pada sesi tidak boleh sama dengan dosen pembimbing (`dosbing_id`) peserta mana pun di sesi tersebut (`422`).
3. **Admin Prodi:** Hanya mengelola jadwal & peserta mahasiswa prodinya. Jadwal historis (`magang_id = NULL` & `prodi_id = NULL` tanpa peserta) tetap dibatasi hanya untuk Super Admin (`403` bagi Admin Prodi).
4. **Mahasiswa:** Hanya dapat melihat jadwal untuk magang miliknya sendiri baik lewat `magang_id` maupun tabel peserta (read-only).
5. **Mitra & Dosen Pembimbing:** Endpoint jadwal utama (`/api/jadwal-presentasi`, `/{id}`) tetap menolak peran historis `mitra` & `dosbing` (`403`).

---

## Catatan Koleksi Postman
Koleksi Postman terbaru disimpan di folder `postman/`, mencakup [SIMAGANG_Admin_SuperAdmin.postman_collection.json](../../postman/SIMAGANG_Admin_SuperAdmin.postman_collection.json) dan koleksi tambahan modul revisi 2026 [SIMAGANG_Revisi_2026_BE1.postman_collection.json](../../postman/SIMAGANG_Revisi_2026_BE1.postman_collection.json).
