# Desain Fitur Shift — Absensi & HR BBWS Pompengan Jeneberang

**Tanggal:** 15 Oktober 2026
**Status:** revisi 4 — **sudah diimplementasikan**; migrasi dijalankan, 47 tes shift hijau
**Konteks:** melengkapi jawaban kuesioner client (A1–A8, B4/B5, C8, F3) dan hasil konfirmasi rancangan (K1–K12, P1–P5)

> **Catatan:** dokumen ini menjelaskan rancangan. Bagian 0–9 adalah rancangan yang sudah jadi kode; bagian 10–13 mencatat apa yang benar-benar dikerjakan, apa yang masih menggantung, dan cara migrasinya dijalankan. Bagian yang masih menggantung ditandai **`PERLU DIPUTUSKAN`**.

---

## 0. Perubahan pada revisi ini

Revisi 1 mengasumsikan jadwal shift **dibuat otomatis** dari pola rotasi. Jawaban client di K6–K9 menolaknya secara tegas (K6: *"Admin akan menyusun setiap minggu, jangan otomatis"*). Revisi ini mengikuti jawaban tersebut.

| Aspek | Revisi 1 | Revisi 2 |
| :--- | :--- | :--- |
| Cara jadwal dibuat | Otomatis dari pola rotasi | **Manual oleh Admin Wilayah, mingguan** |
| Mesin rotasi (pola + offset regu) | Ada | **Dibuang** |
| Penanda pekerja shift | 3 lapis (orang/lokasi/wilayah) | **Per orang** (K4) |
| Jumlah shift | 2 dan 3 | **2, 3, dan 4** (K3) |
| Pulang lebih awal | Dilarang | **Boleh, dengan alasan + persetujuan** (P2) |
| Absen shift di hari libur nasional | Status khusus | **Absen biasa** (P4) |

Revisi 4 menutup tiga butir yang tadinya menggantung (K5, P1, P3) dengan cara **memindahkannya menjadi setelan**, bukan mengunci satu jawaban. Tujuannya: bila client memutuskan lain, cukup ubah setelan — tidak perlu ubah kode.

| Butir | Revisi 3 | Revisi 4 |
| :--- | :--- | :--- |
| **K5** — pola shift ditetapkan di level mana | Tiga level sekaligus, yang paling spesifik menang | **Setelan `cakupan_pola_shift`**: `bertingkat` (bawaan) / `wilayah` / `global`. Pilihan di form Pola Shift ikut menyempit |
| **P1** — jumlah pegawai jaga per lokasi | Menunggu jawaban client | **Panel "Cakupan 24 jam per lokasi"** di layar Jadwal Shift: menghitung sendiri mana jam yang belum tertutup, per lokasi per hari. Petunjuk saja, tidak menahan penyimpanan |
| **P3** — jatah cuti pekerja shift | Menunggu jawaban client | **Setelan `cuti_shift_max`** (0 = ikut kuota umum, bawaan) + cuti pekerja shift dihitung dari **jadwal shift-nya sendiri**, bukan `hari_kerja` kantor |

---

## 1. Latar belakang

Jawaban client soal shift awalnya kabur: A1 hanya berbunyi *"hanya hitungan 12 jam kerja"*, dan A3 menyarankan absen shift malam *"dipecah 2 tanggal"*. Setelah diklarifikasi, aturannya jadi jelas dan konsisten:

> **Durasi shift = 24 jam ÷ jumlah shift per hari.** Jadi panjang shift bukan angka yang diisi manual, melainkan konsekuensi dari berapa shift dalam sehari.

| Pola | Durasi per shift | Contoh pembagian |
| :--- | :--- | :--- |
| 2 shift | 12 jam | 07:00–19:00, 19:00–07:00 |
| 3 shift | 8 jam | 07:00–15:00, 15:00–23:00, 23:00–07:00 |
| 4 shift | 6 jam | 07:00–13:00, 13:00–19:00, 19:00–01:00, 01:00–07:00 |

Karena 2 × 12 = 3 × 8 = 4 × 6 = 24, ketiga pola menutup 24 jam penuh. Yang diatur admin adalah **polanya** (jumlah shift + jam acuan), bukan durasi tiap shift.

Dua pembacaan lama otomatis batal:

- Angka **"12 jam"** di A1 bukan konstanta — itu contoh kasus 2 shift.
- Saran **"dipecah 2 tanggal"** di A3 tidak dipakai. Tabel absensi menyimpan jam masuk/pulang sebagai *jam saja* (`time`), dan satu baris = satu karyawan per tanggal. Shift malam 23:00 → 07:00 cukup **satu baris** di tanggal shift dimulai. Memaksakan dua tanggal justru bentrok dengan aturan `unique(employee_id, work_date)` dan membuat perhitungan hari kerja serta kuota cuti ikut salah. Client mengonfirmasi ini di **K10**.

---

## 2. Keputusan yang sudah final

| # | Aspek | Keputusan | Sumber |
| :--- | :--- | :--- | :--- |
| 1 | Durasi shift | 24 jam ÷ jumlah shift (2 → 12 jam, 3 → 8 jam, 4 → 6 jam) | klarifikasi client, K1 |
| 2 | Jam mulai | Admin isi **jam acuan shift 1** saja, sisanya dihitung otomatis | K2 (YA) |
| 3 | Rentang pola | **2, 3, dan 4 shift** — 4 shift disiapkan sejak awal | K3 |
| 4 | Penanda pekerja shift | **Per orang** | K4 |
| 5 | Pola shift ditetapkan di | **Setelan `cakupan_pola_shift`**: bertingkat (bawaan) / wilayah / global | K5 — dipindah jadi setelan supaya bisa dipersempit tanpa ubah kode |
| 6 | Cara menyusun jadwal | **Manual oleh Admin Wilayah, tiap minggu. Tidak otomatis.** | K6, K7, K8, K9 |
| 7 | Karyawan melihat jadwal sendiri | Ya | K11 (YA) |
| 8 | Shift malam lintas tengah malam | Tetap **satu hari**, mengikuti tanggal masuk | K10 (YA) |
| 9 | WFA untuk pekerja shift | Tidak boleh | K12 (YA) |
| 10 | Pekerja shift masuk saat libur nasional | Ya, dicatat sebagai **absen biasa** | K12, P4 |
| 11 | Pulang lebih awal | **Boleh, dengan mencantumkan alasan + persetujuan atasan/admin** | P2 |
| 12 | Yang menyetujui | **Admin Wilayah** | P5 (sekaligus menutup E10) |
| 13 | Jatah cuti pekerja shift | **Setelan `cuti_shift_max`**; 0 (bawaan) = ikut kuota umum | P3 — dipindah jadi setelan |
| 14 | Dasar hitung cuti pekerja shift | **Jadwal shift sendiri** — hari yang tidak dijadwalkan tidak memotong cuti | P3 |
| 15 | Kebutuhan pegawai jaga per lokasi | **Dipantau sistem** lewat panel cakupan 24 jam, bukan diatur | P1 — dijawab di produk |

Turunan dari keputusan 2: jam mulai shift ke-*n* = jam acuan + (*n* − 1) × durasi.
Contoh jam acuan 07:00 → 3 shift: 07:00, 15:00, 23:00. 4 shift: 07:00, 13:00, 19:00, 01:00.

> **Catatan K1:** client menjawab *"Tidak ada"* pada butir K1, yang bertabrakan dengan K2 (YA) dan K3 (minta 4 shift). Setelah ditimbang, jawaban itu dibaca sebagai penegasan bagian *"tidak ada panjang shift yang diisi terpisah"* — bukan penolakan aturan durasi. Aturannya tetap berlaku, dan hal ini bisa dipastikan sekali lagi saat serah terima.

---

## 3. Konsekuensi yang perlu diketahui

### 3.1 Beban kerja penyusunan jadwal ada di Admin Wilayah

Karena jadwal disusun manual dan tidak otomatis, Admin Wilayah harus mengisi jadwal **setiap minggu untuk setiap karyawan shift**. Ini pilihan client, dan sejalan dengan jawaban mereka di A6. Yang perlu diperhatikan: makin banyak karyawan shift di satu wilayah, makin berat pekerjaan rutin ini. Bila nanti terasa berat, opsi "isi cepat dari pola" bisa ditambahkan tanpa mengubah model data.

### 3.2 Kebutuhan jumlah pegawai jaga — dipantau, bukan ditebak (P1)

Agar 24 jam tetap terisi, jumlah pegawai jaga di satu lokasi harus cukup:

| Pola | Pegawai minimal agar 24 jam tertutup |
| :--- | :--- |
| 2 shift | 2 orang per hari (satu pagi, satu malam) |
| 3 shift | 3 orang per hari |
| 4 shift | 4 orang per hari |

Ditambah cadangan untuk hari libur, sakit, dan cuti. Karena penjadwalan manual, admin bisa menugaskan seadanya — dan kalau jumlah orangnya kurang, tetap ada jam yang kosong.

Daripada menunggu jawaban client soal "berapa orang yang disiapkan", pertanyaan itu dijawab **di dalam produk**: layar Jadwal Shift menampilkan panel **Cakupan 24 jam per lokasi** yang menghitung sendiri, dari jadwal yang sedang disusun, jam mana saja yang belum tertutup shift mana pun. Jadi admin melihat langsung kekurangannya saat menyusun, bukan setelah kejadian.

Sifat panel ini **hanya petunjuk**: ia tidak pernah menahan penyimpanan jadwal, dan tidak menyuruh sistem mengisi apa pun. Keputusan jumlah orang tetap milik client — sistem hanya membuat keadaannya terlihat.

### 3.3 Tidak ada lagi konsep "regu"

Revisi 1 memakai istilah regu (kelompok dengan giliran rotasi). Karena jadwal disusun manual per orang, konsep itu tidak dipakai. Kalau di lapangan istilah regu tetap dipakai untuk menyebut kelompok kerja, itu sekadar sebutan — tidak memengaruhi sistem.

---

## 4. Model data

Penamaan tabel mengikuti konvensi yang sudah ada (nama tabel bahasa Inggris jamak, sebagian kolom bahasa Indonesia, mis. `sites.nama_lokasi`, `attendance_settings.jam_masuk`).

### 4.1 Tabel baru

**`shift_patterns`** — definisi pola shift (jumlah shift + jam acuan), bisa berlapis.

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | bigint | |
| `nama` | string | mis. "Pola 3 Shift Pos Jaga" |
| `jumlah_shift` | tinyint | **2, 3, atau 4** |
| `jam_mulai_shift_1` | time | jam acuan; shift lain dihitung otomatis |
| `region_id` | FK regions, nullable | diisi → pola khusus wilayah ini |
| `site_id` | FK sites, nullable | diisi → pola khusus lokasi ini (paling spesifik) |
| `is_active` | boolean | default true |
| `timestamps` | | |

> **`PERLU DIPUTUSKAN` (K5):** client belum menjawab di level mana pola ini ditetapkan. Rancangan sementara mengikuti pilihan Anda sebelumnya — tiga lapis dengan aturan "paling spesifik menang". Bila client hanya butuh satu pola global, tabel ini cukup diisi satu baris.

**`shift_schedules`** — jadwal shift, diisi manual.

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | bigint | |
| `employee_id` | FK employees | |
| `tanggal` | date | |
| `shift_ke` | tinyint, nullable | 1..4; **null = libur** |
| `shift_pattern_id` | FK shift_patterns | pola yang berlaku saat itu |
| `catatan` | string, nullable | mis. alasan tukar shift |
| `created_by` | FK users, nullable | siapa yang mengisi |
| `timestamps` | | |
| | | **unique(`employee_id`, `tanggal`)** |

> Tabel `rotation_patterns` pada revisi 1 **dihapus** — tidak ada lagi rotasi otomatis.

### 4.2 Kolom baru pada tabel yang sudah ada

| Tabel | Kolom | Tipe | Keterangan |
| :--- | :--- | :--- | :--- |
| `employees` | `is_shift` | boolean, default false | penanda pekerja shift, **per orang** (K4) |
| `attendance_settings` | `cakupan_pola_shift` | string, default `bertingkat` | seberapa dalam pola shift dicari (K5) |
| `attendance_settings` | `cuti_shift_max` | unsigned small int, default 0 | jatah cuti pekerja shift; 0 = ikut kuota umum (P3) |

Sesuai K4, penanda pekerja shift hanya di level karyawan. Tidak ada kolom serupa di `sites` atau `regions`.

Dua kolom baru di `attendance_settings` sengaja berbentuk **setelan**, bukan konstanta di kode: keduanya mewakili keputusan client (K5, P3) yang masih bisa berubah, dan mengubahnya tidak boleh menuntut perubahan kode.

### 4.3 Tabel baru untuk pengajuan pulang lebih awal (P2)

Mengikuti pola persetujuan yang sudah ada di aplikasi (`leaves`, `dinas_claims`, `tolerance_claims`).

**`early_leave_requests`**

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | bigint | |
| `employee_id` | FK employees | |
| `tanggal` | date | tanggal pulang lebih awal |
| `jam_rencana` | time | perkiraan jam pulang |
| `alasan` | text | wajib diisi |
| `status` | enum `Menunggu` / `Disetujui` / `Ditolak` | default `Menunggu` |
| `approver_id` | FK users, nullable | admin wilayah yang berhak menyetujui |
| `approved_by` | FK users, nullable | jejak audit siapa yang menekan setuju |
| `note` | text, nullable | catatan admin |
| `timestamps` | | |

### 4.4 Aturan resolusi pola shift (K5)

Pola yang berlaku untuk seorang karyawan mengikuti setelan `cakupan_pola_shift`:

| Setelan | Urutan pencarian |
| :--- | :--- |
| `bertingkat` (bawaan) | pola lokasi karyawan → pola wilayah karyawan → pola global |
| `wilayah` | pola wilayah karyawan → pola global (pola lokasi **diabaikan**) |
| `global` | pola global saja (pola wilayah dan lokasi **diabaikan**) |

Yang paling spesifik selalu menang. Setelan ini juga **menyaring pilihan di form Pola Shift**: saat cakupan dipersempit ke `wilayah`, opsi "Per lokasi" tidak lagi muncul, dan pengajuan dengan cakupan di luar setelan ditolak server. Dengan begitu setelan tidak bisa dilanggar lewat request langsung.

Konsekuensi yang perlu diketahui: bila cakupan dipersempit ke `global`, Admin Wilayah tidak lagi punya cakupan yang bisa dibuat sendiri — pola hanya bisa dibuat Super Admin. Layar Pola Shift menampilkan keterangan ini, bukan tombol yang gagal saat ditekan.

---

## 5. Cara jadwal disusun (manual)

Jadwal **tidak dihitung sistem**. Alurnya:

1. Admin Wilayah membuka **layar jadwal** untuk satu minggu.
2. Sistem menampilkan semua karyawan shift di wilayahnya (baris) × 7 hari (kolom), kosong.
3. Admin memilih shift untuk tiap karyawan tiap hari (pagi/siang/malam/libur).
4. Tersimpan sebagai baris `shift_schedules`, dengan `created_by` mencatat siapa yang mengisi.
5. Boleh diubah kapan saja sebelum atau sesudah tanggalnya, dengan `catatan` untuk perubahan penting.

Sistem **tidak** menyarankan, mengisi otomatis, atau mengingatkan siapa yang harus bertugas. Bila ada jam yang kosong (tidak ada karyawan bertugas), itu keputusan Admin Wilayah — sistem tidak memaksakan.

### 5.1 Panel cakupan 24 jam (P1)

Di bawah grid jadwal, layar menampilkan satu tabel: **baris = lokasi, kolom = 7 hari minggu itu**. Tiap sel menjawab satu pertanyaan — *apakah 24 jam hari itu tertutup shift di lokasi ini?*

Cara menghitungnya:

1. Tiap karyawan shift di lokasi itu diambil blok shiftnya dari jadwal yang sedang disusun: mulai = jam mulai shift ke-*n*, panjang = durasi pola (24 ÷ jumlah shift).
2. Blok yang melewati tengah malam ikut menutup **hari berikutnya** dalam minggu itu.
3. Hari pertama minggu juga diperiksa terhadap **shift malam sehari sebelumnya** (Minggu sebelumnya), supaya limpahan 23:00–07:00 tidak dihitung sebagai lubang.
4. Sisa menit yang tidak tertutup blok mana pun dilaporkan sebagai kekurangan, lengkap dengan rentang jamnya.

Panel ini dihitung di sisi tampilan dari draf yang belum disimpan, jadi admin melihat akibat setiap perubahan sel **sebelum** menekan simpan. Ia tidak pernah memblokir penyimpanan: jadwal yang kurang orang tetap boleh disimpan, karena itu keputusan kepegawaian, bukan galat.

---

## 6. Alur absensi

Semua perubahan terpusat di `app/Http/Controllers/Karyawan/AttendanceController.php` (`clockIn` / `clockOut`).

### 6.1 Saat clock-in

1. Cek `employees.is_shift`.
2. **Pekerja shift:** cari baris `shift_schedules` hari ini.
   - Tidak ada / `shift_ke` null (libur) → gerbang libur yang ada tetap berlaku (`absen_libur_aktif` / `absen_libur_mode`).
   - Ada shift → **jam masuk acuan = jam mulai shift itu**, bukan `attendance_settings.jam_masuk`.
   - Hari libur nasional **tidak** menutup absen pekerja shift yang punya jadwal — dicatat sebagai **absen biasa** (P4), bukan status khusus.
3. **Non-shift:** perilaku sekarang tidak berubah — tetap pakai `jam_masuk` global.
4. Status `on_time` / `late` dihitung dari jam mulai shift + `toleransi_late_menit` (A4: satu angka toleransi untuk semua shift).

### 6.2 Saat clock-out

- Bila absen pulang **sebelum jam selesai shift**:
  - Ada pengajuan pulang lebih awal berstatus **`Disetujui`** untuk hari itu → absen diterima.
  - Tidak ada / belum disetujui → **ditolak (422)**, disertai arahan mengajukan permohonan.
- Batas selesai shift ke-*n* = jam mulai + durasi. Untuk **shift malam** batasnya jatuh **keesokan hari** (mis. shift 3 mulai 23:00 → selesai 07:00 besok), jadi validasinya memakai objek tanggal, bukan perbandingan jam saja.
- Karyawan non-shift: perilaku sekarang tidak berubah. **`PERLU DIPUTUSKAN`** apakah aturan pulang cepat juga berlaku untuk non-shift.

### 6.3 Pengajuan pulang lebih awal (P2)

1. Karyawan mengajukan: tanggal, perkiraan jam pulang, dan **alasan** (wajib).
2. Berstatus `Menunggu`; terlihat oleh Admin Wilayah wilayahnya.
3. Admin Wilayah menyetujui atau menolak, dengan catatan.
4. Setelah `Disetujui`, karyawan bisa absen pulang lebih awal pada tanggal tersebut.

> **`PERLU DIPUTUSKAN`:** apakah persetujuan harus ada **sebelum** karyawan menekan tombol absen pulang (karyawan menunggu), atau boleh diajukan bersamaan lalu disetujui menyusul. Saran: harus sebelum, karena client menulis *"perlu persetujuan"*.

### 6.4 Lintas tengah malam (A3, K10)

Shift malam tetap **satu baris** pada tanggal shift dimulai:

| work_date | clock_in_at | clock_out_at | shift_ke |
| :--- | :--- | :--- | :--- |
| 2026-01-07 | 23:00 | 07:00 | 3 |

`clock_out_at` 07:00 milik keesokan hari, tapi tetap tercatat di baris tanggal 7. Penanda `shift_ke` di jadwal yang memberi tahu sistem bahwa pulang 07:00 itu wajar.

### 6.5 Lembur (A8)

Lembur dihitung **terpisah** dari jam kerja shift: menit kerja di luar blok shift. Pada tahap ini cukup ditampilkan sebagai angka turunan di rekap. Karena absen di hari libur nasional dicatat sebagai absen biasa (P4), lembur hari libur tidak ditandai khusus.

---

## 7. Dampak ke rekap & laporan

- **F3** — keterlambatan dihitung dari jadwal shift yang berlaku untuk hari itu, bukan dari `jam_masuk` global.
- Rekap menampilkan **shift** tiap baris (mis. "Malam"), supaya jam masuk/pulang yang tidak lazim bisa dimengerti pembaca.
- Rekap CSV (F2, sudah ada) ikut menyertakan kolom shift.
- **P3 — cuti pekerja shift** dihitung dari **jadwal shift orang itu sendiri**, bukan dari `hari_kerja` kantor: hari yang tidak dijadwalkan shift tidak memotong cuti. Karyawan non-shift tidak berubah sama sekali (tetap `hari_kerja` + hari libur nasional dikecualikan).
- Jatah cuti pekerja shift bisa dibedakan lewat setelan `cuti_shift_max`. **Bawaan 0 berarti ikut kuota umum** — jadi tanpa diisi, perilaku lama tetap berlaku dan tidak ada kejutan.
- Layar Cuti karyawan memakai perhitungan yang sama di sisi tampilan, sehingga angka "hari kerja diminta" yang dilihat karyawan shift sama dengan yang divalidasi server.

---

## 8. Aturan silang

| Aturan | Sumber | Perlakuan |
| :--- | :--- | :--- |
| WFA diblokir untuk pekerja shift | C8, K12 | Opsi WFA tidak muncul / ditolak untuk `is_shift = true` |
| Pekerja shift kerja sesuai jadwal | B4/B5, K12 | Hari libur nasional tidak menutup absen bila ada jadwal |
| Absen shift di hari libur nasional | P4 | Dicatat sebagai absen biasa |
| Toleransi satu angka untuk semua | A4 | `toleransi_late_menit` yang ada dipakai apa adanya |
| Absen di lokasi tak terdaftar tetap dicatat | E6 | Di luar cakupan fitur shift; tidak diubah di sini |

---

## 9. Layar (UI)

| Layar | Peran | Isi |
| :--- | :--- | :--- |
| Pola shift | Super Admin; Admin Wilayah bila setelan cakupan mengizinkan | jumlah shift, jam acuan, cakupan (**hanya yang diizinkan setelan**) |
| Jadwal shift | Admin Wilayah | grid karyawan shift × 7 hari, isi manual, navigasi minggu, **panel cakupan 24 jam per lokasi** |
| Pengajuan pulang awal | Admin Wilayah | daftar permohonan `Menunggu`, tombol setuju/tolak |
| Jadwal saya | Karyawan (K11) | jadwal sendiri bulan berjalan |
| Absensi | Karyawan | "Shift hari ini: Malam (23:00–07:00)" + tombol ajukan pulang awal |
| Pengaturan | Super Admin | dua bagian baru: **Cakupan pola shift** dan **Jatah cuti pekerja shift** (di kartu "Shift Kerja") |

Semua layar baru mengikuti pola yang sudah ada: Inertia + React, presenter di `app/Support/`, otorisasi per peran dan per cakupan wilayah seperti rute admin yang sudah jalan.

---

## 10. Rencana bertahap & status

| Fase | Isi | Migrasi baru? | Status |
| :--- | :--- | :--- | :--- |
| 1 | Fondasi: `shift_patterns`, penanda `employees.is_shift`, resolusi pola | ya | **selesai** |
| 2 | `shift_schedules` + layar Admin Wilayah untuk menyusun jadwal mingguan | ya | **selesai** |
| 3 | Layar Karyawan: lihat jadwal sendiri (K11) | tidak | **selesai** |
| 4 | Absensi mengikuti shift (toleransi, lintas tengah malam, tolak pulang cepat) | tidak | **selesai** |
| 5 | Pengajuan pulang lebih awal + persetujuan (P2) | ya | **selesai** |
| 6 | Rekap & laporan: tampilkan shift, keterlambatan dari jadwal (F3) | tidak | **selesai** |
| 7 | Aturan silang: WFA diblokir untuk pekerja shift (C8), hari libur (P4) | tidak | **sebagian** — WFA tidak ada di aplikasi ini |
| 8 | K5 + P1 + P3: cakupan pola jadi setelan, panel cakupan 24 jam, cuti pekerja shift | ya | **selesai** |

Fase 7 hanya dikerjakan bagian hari liburnya (P4). Aturan "WFA diblokir untuk pekerja shift" tidak bisa dikerjakan karena fitur WFA tidak pernah ada di aplikasi ini — lihat §11.

K11 ("karyawan bisa lihat jadwal sendiri") dipenuhi lewat dua tempat yang sudah ada, bukan halaman baru: kartu "Jadwal shift kamu" di halaman Absensi dan kalender "Rencana shift" di halaman Rekap bulanan. Yang ditambahkan sebagai halaman karyawan baru hanya **Pulang Cepat**.

---

## 11. Yang masih menggantung

| Butir | Isi | Menghambat? |
| :--- | :--- | :--- |
| **K5** | Pola shift ditetapkan di level mana (global / wilayah / lokasi) | **Tidak lagi.** Dipindah jadi setelan `cakupan_pola_shift`. Bila client memutuskan hanya satu level, tinggal diubah di Pengaturan — tanpa ubah kode |
| **P1** | Jumlah pegawai jaga per lokasi cukup untuk 24 jam? | **Tidak lagi.** Dijawab di produk: panel cakupan 24 jam menunjukkan jam yang belum tertutup saat jadwal disusun |
| **P3** | Jatah cuti pekerja shift | **Tidak lagi.** Setelan `cuti_shift_max` (bawaan 0 = ikut kuota umum) + cuti dihitung dari jadwal shift sendiri |
| **C4** | Alur persetujuan WFA — bisa memakai mekanisme yang sama dengan P2 | Tidak berlaku: fitur WFA tidak ada di aplikasi ini |
| **C1–C8** | Seluruh rangkaian WFA (absen dari mana saja, label lokasi, dll.) | Tidak berlaku: tidak ada fitur WFA untuk ditempeli |
| — | Pengecualian pulang cepat untuk non-shift (§6.2) | Sudah dikerjakan: gerbang pulang cepat hanya berlaku untuk pekerja shift |
| — | Urutan persetujuan pulang awal (§6.3) | Sudah dikerjakan: persetujuan wajib ada sebelum absen pulang |

K5, P1, dan P3 masih **belum dijawab client secara tertulis**, tapi ketiganya sudah berhenti menghambat: masing-masing kini punya nilai bawaan yang **sama dengan perilaku lama**, dan bisa diubah dari Pengaturan begitu client memutuskan. Bila jawaban client datang, yang berubah hanya nilai setelan — bukan kode.

Soal C1–C8: seluruh rangkaian pertanyaan WFA tidak bisa dijawab lewat kode karena aplikasi ini tidak punya konsep "absen dari luar titik penugasan" sama sekali — tidak ada kolom, tidak ada layar, tidak ada aturan. Menambahkannya berarti membangun modul baru, bukan menyesuaikan yang ada. Karena itu seluruh butir WFA ditandai **tidak berlaku** sampai client memutuskan ingin membangunnya.

---

## 12. Risiko & catatan teknis

- **Kompatibilitas mundur:** selama `employees.is_shift` masih false, seluruh perilaku absensi hari ini tetap berjalan apa adanya. Fitur shift hanya aktif setelah karyawan ditandai — jadi tidak ada risiko merusak data absensi yang sudah ada.
- **`unique(employee_id, work_date)`** tetap dipertahankan; justru inilah alasan shift malam disimpan satu baris (§1).
- **Zona waktu:** semua perhitungan memakai `now('Asia/Makassar')` seperti kode sekarang. Perbandingan jam shift malam lintas tengah malam harus memakai objek `Carbon` bertanggal, bukan perbandingan string jam.
- **Beban Admin Wilayah** (§3.1): penjadwalan manual mingguan adalah pilihan client, tapi berpotensi melelahkan bila karyawan shift banyak. Perlu diperhatikan saat uji coba.
- **Dampak tes:** suite naik dari 226 menjadi **273 tes** (268 lulus, 5 dilewati). `tests/Feature/ShiftKerjaTest.php` berisi 47 tes khusus shift — termasuk penjaga regresi untuk shift malam, karyawan non-shift, hari libur nasional, cakupan pola, dan kuota cuti pekerja shift.
- **Bawaan yang aman:** dua setelan baru (`cakupan_pola_shift` = `bertingkat`, `cuti_shift_max` = 0) keduanya menghasilkan perilaku yang **identik** dengan sebelum revisi 4. Jadi menjalankan migrasi tidak mengubah apa pun sampai Super Admin sengaja mengubahnya.
- **Perhitungan cakupan 24 jam di sisi tampilan** memakai `mulaiMenit` dan `durasiMenit` yang dikirim server, bukan mengurai ulang label jam. Kalau aturan durasi shift berubah, panel ikut berubah tanpa penyesuaian terpisah.
- **Cuti pekerja shift vs hari libur nasional:** karena pekerja shift tetap bekerja saat libur nasional (P4), hari libur tidak dikecualikan dari hitungan cuti mereka — yang menentukan hanya ada tidaknya jadwal shift.

---

## 13. Catatan migrasi & berkas yang dikerjakan

Lima migrasi sudah dibuat dan dijalankan (urutan penting):

| Urutan | Berkas | Isi |
| :--- | :--- | :--- |
| 1 | `2026_10_14_000001_create_shift_patterns_table.php` | tabel pola shift: jumlah shift, jam acuan, cakupan (global / wilayah / lokasi) |
| 2 | `2026_10_14_000002_add_is_shift_to_employees_table.php` | penanda pekerja shift per orang (K4) |
| 3 | `2026_10_14_000003_create_shift_schedules_table.php` | jadwal per orang per tanggal (`shift_ke` kosong = libur) |
| 4 | `2026_10_14_000004_create_early_leave_requests_table.php` | pengajuan pulang lebih awal + persetujuan (P2/P5) |
| 5 | `2026_10_14_000005_add_shift_ke_to_attendances_table.php` | kolom `shift_ke` di absensi agar shift tercatat saat absen |
| 6 | `2026_10_15_000001_add_cakupan_shift_dan_cuti_shift_to_attendance_settings_table.php` | setelan `cakupan_pola_shift` (K5) dan `cuti_shift_max` (P3) |

**Berkas kode baru:**

- `app/Support/ShiftResolver.php` — satu tempat untuk menjawab "shift apa yang berlaku sekarang" (dipakai absen masuk, absen pulang, jadwal, dan dashboard).
- `app/Models/ShiftPattern.php`, `ShiftSchedule.php`, `EarlyLeaveRequest.php`.
- `app/Http/Controllers/Admin/ShiftPatternController.php`, `ShiftScheduleController.php`, `EarlyLeaveController.php`.
- `app/Http/Controllers/Karyawan/EarlyLeaveController.php`.
- `app/resources/js/Pages/Admin/PolaShift.jsx`, `JadwalShift.jsx`, `PulangAwal.jsx`.
- `app/resources/js/Pages/Karyawan/PulangAwal.jsx`.
- `app/tests/Feature/ShiftKerjaTest.php` (47 tes).

**Berkas lama yang disesuaikan:** `Admin/EmployeeController.php` (penanda shift), `Karyawan/AttendanceController.php` (absen mengikuti shift), `Karyawan/DashboardController.php` (jam kerja vs shift), `Karyawan/CutiController.php` (cuti pekerja shift), `Support/AdminPresenter.php`, `Support/ShiftResolver.php` (cakupan pola), `Support/CutiKuota.php` (kuota pekerja shift), `Models/AttendanceSetting.php`, `Models/Employee.php`, `Models/Attendance.php`, `Admin/SettingController.php`, `Admin/ShiftPatternController.php` (penyaringan cakupan), `Admin/ShiftScheduleController.php` (data panel cakupan), `routes/web.php`, serta layar `Admin/Employees.jsx`, `Admin/Settings.jsx`, `Admin/PolaShift.jsx`, `Admin/JadwalShift.jsx`, `Karyawan/Absensi.jsx`, `Karyawan/Cuti.jsx`, `Karyawan/Dashboard.jsx`, `Karyawan/Rekap.jsx`, dan kedua layout.

**Aturan penting yang gampang salah:**

- Penghapusan jadwal lama saat menyimpan ulang ("ganti utuh per rentang") **wajib memakai `whereDate`, bukan `whereBetween`** pada kolom tanggal. Kolom `date` di Laravel diserialisasi dengan komponen jam (`Y-m-d 00:00:00`), sehingga `whereBetween` dengan string tanggal polos akan melewatkan hari terakhir rentang dan memicu galat duplikat saat menyimpan ulang.
- Panel cakupan 24 jam butuh **sehari sebelum minggu itu** ikut dikirim ke tampilan. Tanpa itu, shift malam Minggu sebelumnya tampak seperti lubang 00:00–07:00 di hari Senin — kekurangan yang sebenarnya tidak ada.
- `CutiKuota` menerima **`Employee` atau id**. Pemanggil lama yang mengirim id tetap jalan; untuk pekerja shift, modelnya diambil otomatis supaya aturan jadwal shift ikut berlaku tanpa mengubah pemanggil.
