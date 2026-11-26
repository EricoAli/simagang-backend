# ADM Magang API Reference

**Status:** IMPLEMENTED — TESTED
**Authentication:** Bearer Token (Sanctum)

---

## 1. Get List of Magang

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang`
- **Purpose:** Mengambil daftar magang dengan isolasi role (`admin` per prodi, `mahasiswa` milik sendiri, `mitra` milik mitranya, `super_admin` seluruh prodi).

### Query Parameters
| Parameter | Type | Description |
|---|---|---|
| `mahasiswa_id` | integer | Filter berdasarkan ID mahasiswa. |
| `mitra_id` | integer | Filter berdasarkan ID mitra. |
| `dosbing_id` | integer | Filter berdasarkan ID dosen pembimbing. |
| `periode_id` | integer | Filter berdasarkan master `periode_id`. |
| `tahun_ajaran_id` | integer | Filter berdasarkan master `tahun_ajaran_id`. |
| `tahun_ajaran` | string | Prefix/tahun ajaran (mis. `2026/2027` atau `2026`). |
| `jenis_semester` | string | `Gasal` atau `Genap`. |
| `angkatan` | string | Filter angkatan mahasiswa. |
| `semester_magang` | integer | Filter nomor semester (dinamis >= 1). |
| `status_magang` | string | Filter status (`draft`/`belum_mulai`, `berlangsung`/`sedang_magang`, `selesai`, `ditolak`). |
| `penilaian` | string | `none` atau `exist`. |
| `q` | string | Pencarian pada `jobdesk`, `role_magang`, `mahasiswa.nama`, `mahasiswa.nim`, dan `mitra.nama_mitra`. |
| `per_page` | integer | Default: `10` (maks `500`). |

### Field Baru pada Item Magang
- `status_magang`: `'draft' | 'berlangsung' | 'selesai' | 'ditolak'`
- `status_label`: `'Belum Mulai' | 'Sedang Magang' | 'Selesai' | 'Ditolak'`
- `tahun_ajaran`: string (mis. `"2026/2027"`, tetap aman dari collision relasi via relasi `masterTahunAjaran`)
- `periode_id`, `tahun_ajaran_id`, `jenis_semester`, `periode_label`
- `periode`: objek `PeriodeMagang` + `tahun_ajaran`
- `can_delete`: boolean sesuai hak role dan status magang saat ini.

---

## 2. Get Magang Detail & Full Monitoring

- `GET /api/magang/{id}` — Detail record magang + relasi (`mahasiswa.prodi`, `mitra`, `dosenPembimbing`, `periode.tahunAjaran`, `masterTahunAjaran`) + `can_delete`.
- `GET /api/magang/{id}/detail` — Mengembalikan `{ detail, logbook, penilaian, dokumen, laporan, jadwal_presentasi, jadwal_presentasi_list }`.
  - `dokumen` mengembalikan **seluruh tipe dokumen** milik `magang_id` tersebut.
  - `jadwal_presentasi` mengambil jadwal terbaru baik dari kolom `jadwal_presentasi.magang_id` maupun pivot multi-peserta `jadwal_presentasi_peserta`, lengkap dengan `dosen_penguji`.

---

## 3. Create Magang

- **HTTP Method:** `POST`
- **Endpoint:** `/api/magang`
- **Role:**
  - **Mahasiswa:** membuat pendaftaran untuk dirinya sendiri (`status_magang` dipaksa `'draft'`; `dokumenPenerimaan` wajib PDF maks 5MB; `dokumenPraKRS` **opsional/nullable** PDF maks 5MB).
  - **Admin Prodi / Super Admin:** dapat membuat penempatan magang untuk `mahasiswa_id` target (Admin Prodi ter-scope prodinya sendiri, `dokumenPenerimaan` & `dokumenPraKRS` **opsional**, `status_magang` opsional default `'draft'`).
- **Validasi Periode & Anti-Duplikat:**
  - Mendukung `periode_id` dari master **atau** `semester_magang` dinamis (`integer >= 1`, tidak lagi di-hardcode 6/7).
  - Mahasiswa boleh memiliki riwayat multi-periode; pendaftaran ulang pada periode yang sama ditolak `422` **kecuali** pengajuan sebelumnya berstatus `'ditolak'`.

---

## 4. Update Magang & Status

- **HTTP Method:** `PUT` / `PATCH` / `POST` (`/api/magang/{id}` atau `/api/magang/{id}/status`)
- **Aturan Otorisasi:**
  - **Mahasiswa dilarang (`403`)** mengirim `status_magang` atau `status`.
  - Saat status sudah `'berlangsung'` atau `'selesai'`, mahasiswa dilarang (`422`) mengubah mitra atau periode magangnya.
  - **Admin Prodi & Super Admin** dapat memperbarui status magang (mendukung alias seperti `belum_mulai`, `sedang_magang`, `berjalan`, `revisi` yang dinormalisasi ke `draft` / `berlangsung` / `selesai` / `ditolak`).

---

## 5. Delete Magang (Proteksi Berjenjang)

- **HTTP Method:** `DELETE`
- **Endpoint:** `/api/magang/{id}`
- **Aturan:**
  - **Mahasiswa:** hanya boleh menghapus magang miliknya yang berstatus `'draft'` atau `'ditolak'` dan belum memiliki laporan, penilaian mitra, atau jadwal presentasi (`422` jika `'berlangsung'` / `'selesai'`).
  - **Admin Prodi:** hanya untuk magang prodinya yang berstatus `'draft'` atau `'ditolak'`; ditolak (`422`) untuk `'berlangsung'` / `'selesai'` (arahkan ke edit atau ubah status ke `'ditolak'`).
  - **Super Admin:** diizinkan menghapus sebagai override koreksi darurat.

---

## 6. Export Magang to Excel

- **HTTP Method:** `GET`
- **Endpoint:** `/api/magang/export/excel`
- **Role:** `super_admin`, `admin` (ter-scope prodi), `mitra` (ter-scope mitra). (`mahasiswa` ditolak `403`).
- **Query Parameters:** `angkatan`, `prodi_id` (Super Admin), `mitra_id`, `tahun_ajaran_id`, `periode_id`, `tahun_ajaran`, `jenis_semester`, `semester_magang`, `dosbing_id`, `status_magang`.
- **Format Output:**
  - **1 sheet** Excel terurut/dikelompokkan berdasarkan `Tahun Ajaran` (terbaru lebih dulu), `Jenis Semester`, `Semester`, dan `NIM`.
  - Memuat kolom tambahan: `Prodi`, `Tahun Ajaran`, `Periode`, `Semester`, dan `Status Magang`.
  - Bug akses relasi `logbook?->first()` & `laporan?->first()` telah diperbaiki agar tiap baris hanya menampilkan logbook/foto/laporan milik magang bersangkutan.
  - Karakter `/` pada `tahun_ajaran` (contoh `2026/2027`) disanitasi menjadi `-` pada filename header `Content-Disposition`.
