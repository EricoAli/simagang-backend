# SIMAGANG API Reference (2026 Target)

Dokumen ini adalah referensi API utama untuk SIMAGANG DTEDI, hasil verifikasi terhadap source code baseline saat ini (Laravel 13, Oktober 2026).

## Tujuan Dokumentasi
Memberikan panduan teknis berstandar Postman untuk Project Manager dan Frontend Developer. Dokumentasi ini hanya memuat behavior API *aktual* yang telah diimplementasikan dan diuji, bukan sekadar rencana dari dokumen requirements lama.

## Scope
Scope dokumentasi saat ini mencakup:
- Modul Admin (Authentication & Provisioning Prodi)
- Modul Mahasiswa (Manajemen Data Mahasiswa dengan Isolasi Multi-Prodi)

## Authentication Overview
Seluruh endpoint di bawah `/api` diamankan menggunakan **Laravel Sanctum**.
- Klien harus menyertakan header `Authorization: Bearer <token>`.
- Token didapat melalui endpoint login (`POST /api/auth/login`).

## Role Overview
- **Admin**: Bertindak sebagai pengelola program studi (Prodi). Memiliki akses tak terbatas ke data di dalam prodinya sendiri, namun ditolak dari data prodi lain.
- **Mahasiswa**: Bertindak sebagai end-user. Hanya memiliki akses ke data miliknya sendiri.
- **Mitra** & **Dosbing**: Peran lain yang saat ini ada dalam middleware namun bukan fokus scope manajemen mahasiswa.

## Ringkasan Endpoint & Status

| Modul | Endpoint | Method | Fungsi | Status |
|---|---|---|---|---|
| Admin | Provisioning Seeder | N/A | Inisialisasi Admin per Prodi | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa` | GET | List mahasiswa | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/show` | GET | Detail profil (Self) | IMPLEMENTED — NOT VERIFIED |
| Mahasiswa | `/api/mahasiswa/{id}` | GET | Detail mahasiswa (Admin) | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa` | POST | Tambah mahasiswa baru | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/update` | POST | Update mahasiswa | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/{id}` | DELETE | Hapus mahasiswa | IMPLEMENTED — TESTED |
| Mahasiswa | `/api/mahasiswa/import` | POST | Import via CSV | IMPLEMENTED — NOT VERIFIED |

## API Reference Detail
- [**Admin API & Provisioning**](./admin.md)
- [**Mahasiswa API**](./mahasiswa.md)

---

## Known Limitations

1. **Isolasi multi-prodi belum menyeluruh**: Modul hilir seperti Magang, Logbook, dan Jadwal Presentasi belum menerapkan pengecekan `prodi_id`, sehingga isolasi data belum sempurna di luar modul Mahasiswa.
2. **Middleware global terlalu luas**: Saat ini semua route menggunakan middleware `role:admin,mahasiswa,mitra,dosbing`. Perlindungan cross-role saat ini sangat bergantung pada pengecekan manual di dalam method Controller.
3. **Nama Resmi Prodi (NEED REVIEW)**: Nama panjang untuk prodi TRIK, TRI, dan TRE belum dikonfirmasi dari spesifikasi 2026, sehingga data master saat ini masih menggunakan singkatan.
4. **Validasi Uniqueness pada Update (NEED REVIEW)**: Endpoint `POST /api/mahasiswa/update` belum mengabaikan (ignore) NIM milik diri sendiri saat validasi uniqueness.
