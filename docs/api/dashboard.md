# Dashboard API Reference

**Status:** IMPLEMENTED — TESTED
**Authentication:** Bearer Token (Sanctum)

---

## 1. Admin Dashboard

- **HTTP Method:** `GET`
- **Endpoint:** `/api/dashboard/admin`
- **Role:** `admin`, `super_admin` (`mahasiswa`, `mitra`, `dosbing` → `403`)
- **Query Parameters:**
  - `prodi_id` (opsional, khusus `super_admin` untuk filter per prodi)
  - `periode_id` (opsional)
  - `tahun_ajaran_id` (opsional)

### Response Success (`200 OK`)
```json
{
  "jumlah_mahasiswa_aktif_magang": 12,
  "jumlah_mahasiswa": 45,
  "jumlah_mitra": 15,
  "jumlah_dosbing": 10,
  "aktivitas_terbaru": [
    {
      "magang_id": 5,
      "nama_mahasiswa": "Budi Santoso",
      "nim": "24/12345/SV/54321",
      "status_magang": "berlangsung",
      "status_label": "Sedang Magang",
      "nama_prodi": "Teknologi Rekayasa Perangkat Lunak",
      "kode_prodi": "TRPL",
      "nama_mitra": "PT Mitra Teknologi",
      "tanggal_mulai": "2026-02-01",
      "tanggal_selesai": "2026-07-31"
    }
  ]
}
```
> **Catatan:** `jumlah_mahasiswa_aktif_magang` menghitung mahasiswa unik yang berstatus `'berlangsung'` pada periode aktif (atau periode terfilter), tanpa hardcode semester 6/7. `aktivitas_terbaru` menampilkan maks 5 aktivitas terbaru `'berlangsung'`/`'selesai'` sesuai scope prodi & filter.

---

## 2. Mitra Dashboard

- **HTTP Method:** `GET`
- **Endpoint:** `/api/dashboard/mitra`
- **Role:** `mitra`
- **Query Parameters (Opsional):** `periode_id`, `tahun_ajaran_id`, `semester_magang`

### Response Success (`200 OK`)
```json
{
  "jumlah_mahasiswa_magang_by_mitra": 5,
  "jumlah_penilaian": 3,
  "jumlah_magang": 5
}
```

---

## 3. Mahasiswa Dashboard

- *(Area BE 2 — tidak diubah pada cakupan revisi BE 1).*
