# API Admin & Prodi

**Status:** IMPLEMENTED - TESTED
**Note:** Dokumentasi ini menjelaskan struktur data dan otorisasi dasar terkait entitas Admin dan Prodi, yang menopang arsitektur isolasi data multi-prodi.

---

## 1. Master Data Prodi

Sistem memiliki empat program studi (Prodi) utama di lingkungan DTEDI. Data prodi dikelola sepenuhnya oleh Seeder (server-side).

| ID | Kode Prodi | Status Nama Resmi (2026 PM) | Nama Prodi di Database |
|----|------------|---------------------------|----------------------|
| 1  | TRPL       | **CONFIRMED**             | Teknologi Rekayasa Perangkat Lunak |
| 2  | TRIK       | **NEED REVIEW**           | TRIK |
| 3  | TRI        | **NEED REVIEW**           | TRI |
| 4  | TRE        | **NEED REVIEW**           | TRE |

> *Nama prodi selain TRPL saat ini belum memiliki referensi nama resmi penuh di dalam dokumen 2026, sehingga data tetap menggunakan kependekan.*

---

## 2. Struktur Relasi Admin dan Prodi

Setiap data Admin (di tabel `data_admin`) terikat secara langsung dengan satu `prodi_id`. Hal ini digunakan untuk memastikan bahwa Admin hanya dapat mengelola data mahasiswa, dokumen magang, logbook, dan presensi yang berada di bawah prodinya.

### Admin Provisioning (Pembuatan Akun Admin)

**Status Provisioning:** IMPLEMENTED — TESTED
**Status API Endpoint:** Tidak Ada (By Design)

**Prinsip Desain:**
- Admin harus selalu memiliki tepat satu prodi.
- Admin tidak boleh menentukan/mengubah prodi sendiri secara bebas melalui endpoint publik.
- User biasa (melalui `/api/auth/register`) **tidak diizinkan** mendaftar sebagai Admin. Payload role `admin` ditolak oleh validasi controller (`in:mahasiswa,mitra,dosbing`).

Oleh karena itu, pada environment development/awal, akun Admin dipersiapkan melalui `AdminSeeder`. Seeder ini bersifat *idempotent* dan memastikan setiap prodi memiliki akun Admin yang terhubung dengan tabel `data_admin`.

**Contoh Credentials Admin Development:**
- TRPL: `admin_trpl`
- TRIK: `admin_trik`

*(Password tidak dicantumkan dalam dokumentasi referensi ini demi keamanan).*

---

## 3. Otorisasi Multi-Prodi pada Flow Mahasiswa

Hubungan Admin → Prodi → Mahasiswa telah dibuktikan oleh sistem *Automated Testing* dan menghasilkan aturan berikut:

1. **Auto-Assignment (TESTED):** Ketika Admin menambahkan entitas baru seperti Mahasiswa, sistem secara otomatis akan mengambil `prodi_id` dari profil Admin yang sedang login dan mengabaikan parameter `prodi_id` yang mungkin dikirim oleh klien. (Admin TRPL membuat mahasiswa -> otomatis masuk prodi TRPL).
2. **Isolasi Read (TESTED):** Admin TRPL yang memanggil GET list mahasiswa hanya akan menerima kembalian data mahasiswa TRPL. Admin TRPL dilarang melihat detail profil mahasiswa dari prodi TRIK.
3. **Isolasi Write (TESTED):** Admin TRPL ditolak secara sistem (403 Forbidden) apabila mencoba memodifikasi atau menghapus record mahasiswa dari prodi TRIK.
