# ADM Presentasi PI API Reference

**Status:** IMPLEMENTED — TESTED
**Authentication:** Bearer Token (Sanctum)

---

## 1. List Jadwal Presentasi

- **HTTP Method:** `GET`
- **Endpoint:** `/api/jadwal-presentasi`
- **Role:** `super_admin` (semua + historis), `admin` (ter-scope prodi), `mahasiswa` (hanya sesi yang mengikutsertakan dirinya). (`dosbing` & `mitra` → `403`).

### Query Parameters
| Parameter | Type | Description |
|---|---|---|
| `status` | string | `terjadwal`, `selesai`, `dibatalkan` |
| `tanggal_presentasi` | date | Format `Y-m-d` |
| `magang_id` | integer | Jadwal yang berelasi langsung atau via tabel peserta |
| `mahasiswa_id` | integer | Filter per mahasiswa |
| `dosbing_id` | integer | Filter mahasiswa bimbingan dosen pembimbing terkait |
| `dosen_penguji_id` | integer | Filter berdasarkan dosen penguji |
| `periode_id` | integer | Filter periode magang |
| `tahun_ajaran_id` | integer | Filter tahun ajaran |
| `semester_magang` | integer | Filter semester |
| `q` | string | Cari ruangan, keterangan, nama penguji, nama/NIM peserta |
| `per_page` | integer | Default `15` (maks `500`) |

### Atribut Tambahan pada Item Jadwal
- `dosen_penguji_id`, `dosen_penguji` (string nama), `dosen_penguji_data` (objek penguji), `prodi_id`, `status`, `peserta_count`, `jumlah_peserta`, `peserta_magang`.

---

## 2. Detail, Create, Update, Delete Sesi Presentasi

- `GET /api/jadwal-presentasi/{id}` (`admin` ditolak `403` pada jadwal historis `magang_id = NULL && prodi_id = NULL` tanpa peserta).
- `POST /api/jadwal-presentasi` (**Role:** `admin`, `super_admin`):
  - `magang_id` sekarang **opsional (`nullable`)** agar Admin dapat membuat sesi presentasi lalu menambahkan multi-peserta lewat `DetailSesi`.
  - `dosen_penguji_id` (`nullable|exists:dosen_pembimbing,dosbing_id`): jika `magang_id` diisi, divalidasi agar `dosen_penguji_id !== magang.dosbing_id` (`422`).
- `PUT /api/jadwal-presentasi/{id}` (**Role:** `admin`, `super_admin`):
  - Mendukung pembaruan `dosen_penguji_id`, `tanggal_presentasi`, `waktu_mulai`, `waktu_selesai`, `ruangan`, `keterangan`, `status`, serta `magang_id` opsional dengan validasi penguji ≠ pembimbing peserta.
- `DELETE /api/jadwal-presentasi/{id}` (**Role:** `admin`, `super_admin`).

---

## 3. Manajemen Peserta Sesi Presentasi (Multi-Peserta)

- `GET /api/jadwal-presentasi/{id}/peserta` — Daftar peserta tergabung (dari pivot `jadwal_presentasi_peserta` & legacy `magang_id`).
- `GET /api/jadwal-presentasi/{id}/mahasiswa-eligible` (**Role:** `admin`, `super_admin`) — Mahasiswa magang berstatus `'berlangsung'` pada prodi terkait yang belum masuk sesi ini dan bukan bimbingan dari `dosen_penguji_id` sesi (`?dosbing_id=`, `?periode_id=`).
- `POST /api/jadwal-presentasi/{id}/peserta` (**Role:** `admin`, `super_admin`) — Body `{ "magang_id": <int> }`. Menolak `403` lintas prodi dan `422` jika duplikat atau `dosbing_id === dosen_penguji_id`.
- `DELETE /api/jadwal-presentasi/{id}/peserta/{magangId}` (**Role:** `admin`, `super_admin`) — Mengeluarkan peserta dari sesi.

---

## 4. Daftar Mahasiswa Bimbingan / Penjadwalan

- `GET /api/jadwal-presentasi/advisees` (**Role:** `admin`, `super_admin`, `dosbing`)
- **Query Parameters:** `dosbing_id`, `periode_id`, `tahun_ajaran_id`, `q`
- Mengembalikan status penjadwalan masing-masing magang (`belum_dijadwalkan`, `sudah_dijadwalkan`, `selesai`, `bisa_dijadwalkan`, `jadwal_id`).
