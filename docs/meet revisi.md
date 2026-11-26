**JANGAN MENGUBAH APAPUN ISI FILE INI, INI SOURCE OF TRUTH**

## **Prioritas 1 — MASTER SEMESTER & TAHUN AJARAN**

**BE — paling prioritas**

* Buat **master tahun ajaran**.  
* Buat data periode yang dinamis, tidak boleh hard-code.  
* Data periode minimal membedakan:  
  * Semester magang: 6, 7, dst.  
  * Gasal/Genap  
  * Tahun ajaran, misalnya `2026/2027`  
* Super admin dapat menambah data periode/tahun ajaran.  
* Data mahasiswa nantinya dikaitkan dengan tahun ajaran tersebut.  
* Dropdown semester/periode mengambil data dari master database.

Contoh:

```
Semester 6 | Genap | 2026/2027
Semester 7 | Gasal | 2027/2028
```

Meeting secara eksplisit menyebut ini sebagai **major** dan tidak boleh hard-code.

**FE**

* Ubah dropdown semester/periode agar mengambil dari API.  
* Tambahkan input/pilihan tahun ajaran di halaman mahasiswa/magang.  
* Buat UI CRUD tahun ajaran untuk super admin.

# **Prioritas 2 — PERBAIKAN DATA MAHASISWA**

**BE**

* Saat import CSV, data awal cukup:  
  * NIM  
  * Nama  
  * Prodi  
  * Angkatan  
* Dosen pembimbing tidak wajib saat import.  
* Status magang, semester magang, mitra, dan dosen pembimbing dapat kosong terlebih dahulu.  
* Admin dapat menentukan dosen pembimbing setelah mahasiswa mendapatkan mitra.  
* Pastikan assignment dosen pembimbing tersimpan dengan benar.

Hal ini memang dibahas langsung pada bagian import mahasiswa.

**FE**

* Sesuaikan form/detail mahasiswa agar field yang belum ditentukan dapat kosong.  
* Buat dropdown dosen pembimbing pada sisi admin.

# **Prioritas 3 — PERIODE MAGANG PADA DATA MAHASISWA**

Ini lanjutan langsung dari master semester/tahun ajaran.

**BE**

* Hubungkan mahasiswa dengan:  
  * semester magang  
  * tahun ajaran  
  * gasal/genap  
* Pastikan mahasiswa dapat memiliki riwayat magang pada semester/periode yang berbeda.  
* Validasi agar periode yang sama tidak bisa didaftarkan berulang.

**FE**

* Tambahkan field **Semester Magang** dan **Tahun Ajaran** pada form.  
* Dropdown mengambil data dari master.  
* Tampilkan periode pada detail mahasiswa/magang.

# **Prioritas 4 — STATUS MAGANG**

**BE**

Status dikelola oleh **admin**, bukan mahasiswa.

Status yang dibahas:

```
Belum Mulai
Sedang Magang
Selesai
```

Admin yang mengubah status tersebut.

**FE**

* Ubah tampilan status menjadi lebih jelas.  
* Jangan menggunakan teks **“Menunggu Konfirmasi”** jika maksudnya sebenarnya mahasiswa belum mulai magang.  
* Status harus mengikuti data dari backend.

Meeting secara jelas mengarah bahwa konfirmasi/status magang dilakukan dari sisi admin.

# **Prioritas 5 — ATURAN HAPUS/BATAL MAGANG**

**BE — penting**

* Data yang sudah dikonfirmasi jangan bisa dibatalkan sembarangan.  
* Jika sudah masuk proses/sedang magang, jangan boleh dihapus secara bebas.  
* Validasi harus ada di backend, bukan hanya menyembunyikan tombol di FE.

**FE**

* Tombol hapus/batal disesuaikan dengan status.  
* Jika sudah tidak boleh dibatalkan, tombol bisa disabled atau tidak ditampilkan.

Ini juga termasuk logika yang ditekankan pada meeting.

# **Prioritas 6 — PENJADWALAN PRESENTASI**

**FE**

* Filter mahasiswa berdasarkan **dosen pembimbing**.  
* Ketika memilih dosen, mahasiswa yang muncul hanya mahasiswa bimbingan dosen tersebut.  
* Jadwal yang sudah dibuat harus dapat dilihat mahasiswa.

**BE**

* API/filter mahasiswa berdasarkan dosen pembimbing.  
* Pastikan relasi mahasiswa–dosen benar.  
* API jadwal presentasi harus bisa mengambil jadwal sesuai mahasiswa.

# **Prioritas 7 — HALAMAN MITRA**

**FE**

* Tambahkan **periode/tahun ajaran** pada data mahasiswa.  
* Filter mahasiswa berdasarkan periode.  
* Pastikan perusahaan dapat melihat mahasiswa berdasarkan periode magang.

**BE**

* Tambahkan filter berdasarkan tahun ajaran/periode.  
* Pastikan query tidak hanya berdasarkan nama perusahaan.

Meeting juga mengarah bahwa data magang perlu dikelompokkan berdasarkan tahun ajaran, karena semester 6 akan selalu ada pada setiap angkatan.

# **Prioritas 8 — DOKUMEN**

**FE**

Tambahkan informasi **tipe dokumen**.

Contoh:

| Dokumen | Tipe |
| ----- | ----- |
| Surat Penerimaan | Penerimaan |
| Surat Pernyataan | Pernyataan |
| Dokumen Magang | Magang |

**BE**

* Tambahkan field tipe/jenis dokumen pada database.  
* Sesuaikan API tambah/edit/get dokumen.

# **Prioritas 9 — LOGBOOK**

**FE**

* Perbaiki tampilan input logbook.  
* Pastikan tombol tambah logbook tetap terlihat.  
* Maksimal 5 foto per logbook.

**BE**

* Validasi maksimal 5 foto.  
* Pastikan multiple image tersimpan dengan benar.

# **Prioritas 10 — EXPORT EXCEL**

**BE**

* Data export harus mencantumkan **tahun ajaran/periode**.  
* Data mahasiswa dapat dikelompokkan berdasarkan tahun ajaran.

**FE**

* Pastikan tombol download/export tetap berjalan.  
* Tidak perlu membuat fitur baru besar di FE.

Di meeting, tahun ajaran juga dibahas agar informasi pada Excel tidak lagi bergantung pada hard-code angkatan.

**Pembagian**

### **BE — kerjakan dulu**

1. **Master Tahun Ajaran**  
2. **Master Semester/Periode**  
3. Relasi mahasiswa → semester/periode → tahun ajaran  
4. Import CSV mahasiswa tanpa wajib dosen pembimbing  
5. Assignment dosen pembimbing  
6. Validasi satu mahasiswa pada satu periode  
7. Logic status magang  
8. Validasi hapus/batal magang  
9. API filter mahasiswa berdasarkan dosen  
10. API filter mahasiswa berdasarkan tahun ajaran/periode  
11. Tipe dokumen  
12. Validasi maksimal 5 foto logbook  
13. Penyesuaian export Excel

### **FE — setelah API/struktur BE siap**

1. Form/master **Tahun Ajaran**  
2. Dropdown **Semester \+ Tahun Ajaran**  
3. Form/edit data magang  
4. Tampilan status magang  
5. Tombol hapus/batal sesuai status  
6. Filter mahasiswa berdasarkan dosen  
7. Filter periode pada halaman mitra  
8. Tampilan tipe dokumen  
9. Perbaikan UI logbook  
10. Penyesuaian halaman export

### **Yang dikerjakan bareng**

**Semester \+ Tahun Ajaran → Data Mahasiswa → Data Magang → Status → Mitra → Presentasi**

