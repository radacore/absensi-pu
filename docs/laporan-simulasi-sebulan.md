# Laporan Simulasi Satu Bulan Penuh — Rekap Presensi Perorangan

**Tanggal:** 15 Oktober 2026
**Pertanyaan yang dijawab:** *"Coba isi satu user penuh selama satu bulan, semua skenario fitur — apakah sudah aman?"*
**Bulan contoh:** Agustus 2026 (31 hari, dimulai hari Sabtu, 2 libur nasional: 17 & 28 Agustus → **19 hari kerja**)

Disusun oleh: **Rahmat Dani S** — Tim Pengembang Aplikasi Absensi

---

## Jawaban singkat

**Rekapnya konsisten.** Angka di layar, PDF, dan berkas unduhan (CSV) semuanya berasal dari satu sumber yang sama, dan hasil penjumlahan tiap bagian saling cocok — tidak ada lagi angka yang tampak "hilang" atau terhitung dua kali.

**Simulasi ini menemukan 4 kekeliruan tampilan/hitungan yang nyata.** Keempatnya sudah diperbaiki dan sekarang dijaga oleh tes otomatis. Selain itu ditemukan 2 masalah tampilan kecil (grafik dan tata letak PDF) yang juga sudah diperbaiki.

**Tapi perlu jujur:** simulasi ini membuktikan **angka rekapnya benar**, bukan bahwa aplikasinya "100% bebas cacat". Sisa risiko yang tidak bisa dijawab simulasi (di luar ranah aplikasi) ada di [§5](#5-yang-masih-terbuka).

---

## 1. Bagaimana simulasi ini dilakukan

Satu karyawan (pekerja jam tetap) diisi aktivitas **selama satu bulan penuh**, mencampur semua jenis hari yang mungkin terjadi:

| Jenis hari | Contoh | Jumlah |
| :--- | :--- | :--- |
| Hadir tepat waktu | mayoritas hari | 10 |
| Hadir dalam batas toleransi | 5 Agu, 21 Agu | 2 |
| Terlambat | 4 Agu (20 menit), 24 Agu (55 menit) | 2 |
| Hadir tanpa absen pulang | 12 Agu | — |
| Lupa absen datang (dispensasi disetujui) | 21 Agu | — |
| Lupa absen pulang (dispensasi disetujui) | 25 Agu | — |
| Cuti tahunan disetujui | 13–14 Agu | 2 |
| Dinas luar disetujui | 18–19 Agu | 2 |
| Libur nasional | 17 & 28 Agu | 0 (hari kerja) |
| Tanpa keterangan (alfa) | 11 Agu | 1 |
| Akhir pekan | 1, 2, 8, 9, 15, 16, 22, 23, 29, 30 Agu | 0 (hari kerja) |
| Pulang cepat disetujui | 31 Agu | — |

Sengaja ikut dimasukkan **kasus yang harus DITOLAK** — supaya terbukti tidak ada yang bocor ikut terhitung:

- pengajuan dinas yang **ditolak** (20 Agu) — tidak boleh muncul;
- klaim toleransi yang masih **menunggu** dan yang **ditolak** (26 & 27 Agu) — tidak boleh menambah apa pun;
- cuti yang masih **menunggu** keputusan (31 Agu) — tidak boleh dihitung sebagai cuti.

Karena pekerja shift punya alur penilaian yang berbeda (dinilai dari **jadwal**, bukan hari kerja kantor), dites juga **satu pekerja shift** (Budi, "Regu B") yang dijadwalkan 1–14 Agustus dengan Pola 3 Shift, termasuk absen malam yang pulang lewat tengah malam dan hadir di hari Sabtu/Minggu sesuai jadwal.

Simulasi dijalankan sebagai **tes otomatis yang bisa diulang kapan saja**:

```bash
cd app
php artisan test --filter=SimulasiSatuBulanTest
```

---

## 2. Hasil pemeriksaan angka

### Karyawan jam tetap (Andi) — Agustus 2026

| Bagian | Hasil | Cocok dengan harapan? |
| :--- | :--- | :--- |
| Kehadiran (hadir) | **14** | ✅ 14 baris absensi |
| Dinas | **2** | ✅ 18–19 Agu |
| Cuti | **2** | ✅ 13–14 Agu |
| Tanpa keterangan | **1** | ✅ 11 Agu |
| **Jumlah donat** | **19** | ✅ **sama dengan 19 hari kerja** |
| Tepat waktu | 10 | ✅ |
| Dalam toleransi | 2 | ✅ |
| Terlambat | 2 | ✅ |
| Hadir tanpa jam masuk | 0 | ✅ |
| **Total kehadiran** | **14** | ✅ sama dengan jumlah rincian zona |
| Akumulasi keterlambatan | 75 menit | ✅ 20 + 55 |
| Akumulasi pulang lebih cepat (PSW) | 150 menit | ✅ 30 + 120 |
| Baris tabel harian | 31 | ✅ sebulan penuh |
| Hari terakhir bulan (31 Agu) | ikut terhitung | ✅ |

### Pekerja shift (Budi)

| Bagian | Hasil |
| :--- | :--- |
| Hari dijadwalkan shift | 11 hari (14 hari pertama dikurangi 3 hari libur jadwal) |
| Terlambat | 20 menit, dihitung dari **jam mulai shift** (bukan jam kantor) |
| Hadir walau akhir pekan (Sabtu/Minggu) | ✅ tetap dinilai hari kerja karena dijadwalkan |
| Tanggal di luar jadwal | ✅ bukan pelanggaran, ditandai "Belum ada jadwal shift" |
| Libur jadwal | ✅ tidak dianggap alfa |
| PSW | 0 (shift tidak dihitung PSW) |

### Berkas PDF & CSV

- **PDF rekap** dan **CSV** menampilkan angka yang **persis sama dengan layar** — sudah diuji satu per satu.
- PDF menampilkan 31 baris harian dan **muat dalam satu halaman** (tidak ada satu baris tersisa sendirian di halaman berikutnya).
- Absen malam yang pulang lewat tengah malam tercatat di tanggal yang benar.
- Kolom **Shift** dan **Mulai** muncul untuk pekerja shift, dan **"—"** untuk pekerja jam tetap.

---

## 3. Empat kekeliruan nyata yang ditemukan simulasi

| # | Masalah | Dampak bagi pengguna | Status |
| :--- | :--- | :--- | :--- |
| 1 | **Hari dinas ikut terhitung sebagai "Hadir"** | Donat distribusi bisa berjumlah **lebih banyak daripada jumlah hari kerja** (mis. 21 dari 19 hari) — terbaca seperti kelebihan data | ✅ Diperbaiki |
| 2 | **Hadir tanpa jam masuk tidak punya tempat** | Baris "Total kehadiran" tidak sama dengan penjumlahan zona (tepat + toleransi + terlambat), seolah ada selisih yang salah hitung | ✅ Diperbaiki |
| 3 | **Hari tanpa koordinat dicetak "0.000000, 0.000000 / 0 m"** | Hari yang jamnya diisi lewat persetujuan (tanpa GPS) tercetak seolah absen dari titik nol derajat di tengah laut | ✅ Diperbaiki |
| 4 | **Jarak pulang memakai angka jarak masuk** | Kartu "Absen Pulang" menampilkan jarak yang salah (angka milik absen datang) | ✅ Diperbaiki |

Untuk nomor 1: sekarang **Hadir 14 + Dinas 2 + Cuti 2 + Tanpa keterangan 1 = 19 hari kerja**. Keempat potongan donat tidak lagi saling tumpang tindih, dan baris "Total kehadiran" sama persis dengan penjumlahan rinciannya.

Untuk nomor 3: hari yang jamnya diisi lewat persetujuan sekarang ditandai **"Isian manual"** dengan koordinat dan jarak ditampilkan **"—"**, bukan angka nol yang menyesatkan.

---

## 4. Dua masalah tampilan yang ikut diperbaiki

| # | Masalah | Perbaikan |
| :--- | :--- | :--- |
| 5 | Grafik tren jam absen: judul menempel label jam paling atas, dan label "16:00" tercetak dua kali bertumpuk (label sumbu berhimpit dengan garis acuan jam pulang) | Ruang atas grafik diperlebar; label yang berhimpit dengan garis acuan dilewati. Berlaku di PDF maupun di layar |
| 6 | Tabel rekap harian 31 baris membuat PDF menjadi 10 halaman dengan 1 baris "yatim" di halaman terakhir | Jarak baris dirapatkan → **PDF menjadi 9 halaman**, tabel sebulan muat satu halaman |

---

## 5. Yang masih terbuka

Simulasi ini memeriksa **isi rekap dan berkas hasilnya**. Hal-hal berikut **tidak** bisa dijawab simulasi, dan tetap menjadi tanggung jawab saat pemasangan di server:

1. **Keamanan server** — HTTPS/TLS, cookie sesi aman, kredensial basis data, pencadangan, folder `storage/` dan `.env` tidak boleh bisa diakses dari internet. Semua ini **di luar aplikasi**; panduannya ada di [`docs/panduan-deploy-keamanan.md`](panduan-deploy-keamanan.md).
2. **Kata sandi contoh** (`password123`) **wajib diganti** sebelum dipakai sungguhan.
3. **Belum ada verifikasi dua langkah (2FA)** untuk akun admin/super admin.
4. **Validasi swafoto** masih berupa tautan teks, belum diperiksa sebagai berkas sungguhan (temuan lama, belum dikerjakan).
5. **Batas approver cuti berjenjang** baru sebatas "harus akun admin" — belum memaksa orang yang berbeda di tiap tingkat.

---

## 6. Kesimpulan

| Pertanyaan | Jawaban |
| :--- | :--- |
| Apakah angka rekapnya konsisten untuk sebulan penuh? | **Ya.** Layar, PDF, dan CSV cocok satu sama lain. |
| Apakah semua skenario fitur tercatat dengan benar? | **Ya** — kehadiran, keterlambatan, toleransi, PSW, lupa absen, cuti, dinas, libur, alfa, catatan pekerjaan, kerja shift. |
| Apakah kasus yang harus ditolak benar-benar diabaikan? | **Ya** — pengajuan ditolak/menunggu tidak menambah apa pun. |
| Apakah bebas cacat? | **Tidak bisa dijamin 100%.** Empat kekeliruan nyata ditemukan justru *karena* simulasi ini, dan sudah diperbaiki. |

**Saran:** pertahankan tes `SimulasiSatuBulanTest` sebagai penjaga — jalankan setiap kali ada perubahan pada bagian rekap presensi, supaya kekeliruan seperti nomor 1–4 tidak terulang diam-diam.

---

## 7. Cara mengulang simulasi

```bash
cd app

# Seluruh simulasi (4 tes, 147 pemeriksaan)
php artisan test --filter=SimulasiSatuBulanTest

# Simpan PDF & CSV hasilnya untuk diperiksa manual
SIM_PDF_OUT=/tmp/rekap-agustus.pdf \
SIM_CSV_OUT=/tmp/rekap-agustus.csv \
php artisan test --filter=SimulasiSatuBulanTest
```

Untuk melihat tampilan sebulan penuh di layar/browser (lingkungan lokal saja):

```bash
php artisan db:seed --class=SimulasiTampilanSeeder
```
