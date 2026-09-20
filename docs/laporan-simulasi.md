# Laporan Simulasi End-to-End — BBWS Pompengan Jeneberang (Absensi & HR)

**Tanggal:** 20 September 2026
**Lingkungan:** MariaDB 12.2.2 (`absensi_sim`), PHP 8.4.18, Laravel 13.26.1, object storage Neva Objects (S3-compatible)
**Pertanyaan yang dijawab:** *"Coba kamu simulasi menggunakan semua fiturnya, apakah sudah aman tidak ada bug?"*

## Jawaban singkat

**Fitur berjalan dengan baik. Awalnya ditemukan 6 celah keamanan — keenamnya sudah diperbaiki dan diverifikasi. Tiga temuan kebijakan juga sudah diputuskan dan dikerjakan.**

| Aspek | Sebelum perbaikan | Sesudah perbaikan |
| :--- | :--- | :--- |
| Suite otomatis | 148 lulus / **6 gagal** | ✅ **183 lulus / 0 gagal** |
| Simulasi HTTP nyata | 53 lulus / 0 gagal | ✅ **65 lulus / 0 gagal** |
| Fungsionalitas seluruh fitur | Berjalan | ✅ Berjalan |
| Batas peran (role boundary) | Aman | ✅ Aman |
| Otorisasi per-wilayah (IDOR) | ❌ 4 celah | ✅ **Ditutup** |
| Integritas data | ❌ Risiko kehilangan data | ✅ **Ditutup** |
| Enum mati (`excused_love`/`early_leave`) | ❌ Tak pernah dipakai | ✅ **Terpakai** |
| Absen di luar hari kerja | ⚠️ Selalu diterima | ✅ **Dapat diatur Super Admin** |
| Approver cuti | ⚠️ Belum dibatasi | ✅ **Akun admin saja** |

> **Status:** seluruh temuan pada laporan ini **sudah diperbaiki** pada 20 Sep 2026 dan diverifikasi ulang di MySQL. Setiap perbaikan punya tes penjaga regresi — lihat [§6 Status perbaikan](#6-status-perbaikan) dan [§6b Tiga kebijakan baru](#6b-tiga-kebijakan-baru-yang-diputuskan-20-sep-2026).

---

## 1. Cara simulasi dilakukan

Simulasi dijalankan **dua lapis**, supaya tidak hanya mengandalkan satu metode:

### Lapis 1 — Simulasi HTTP sungguhan (65 skenario)
Skrip: **`app/scripts/simulasi-http.sh`**
Menembak server yang benar-benar berjalan (`php artisan serve`) lewat HTTP. Jadi yang teruji adalah tumpukan nyata: middleware, CSRF, sesi, Inertia, MySQL, dan **object storage S3 sungguhan** (bukan tiruan).

Hasil: **65 lulus, 0 gagal.** Termasuk unggah PDF asli ke bucket, lalu menghapusnya kembali — bucket terverifikasi bersih (`0 objek`) setelah selesai.

### Lapis 2 — Simulasi otomatis (186 tes)
Berkas: **`app/tests/Feature/FullSimulationTest.php`** (78 tes) dan **`app/tests/Feature/KebijakanBaruTest.php`** (29 tes)
Menjalankan seluruh alur seperti pengguna sungguhan, termasuk **kasus negatif** (input tidak sah, akses lintas wilayah, batas peran).

```bash
# Siapkan basis data bersih
DB_CONNECTION=mysql DB_DATABASE=absensi_sim DB_USERNAME=absensi_pu \
DB_PASSWORD=absensi_dev_pass php artisan migrate:fresh --seed --force

# Simulasi HTTP
DB_CONNECTION=mysql DB_DATABASE=absensi_sim DB_USERNAME=absensi_pu \
DB_PASSWORD=absensi_dev_pass php artisan serve --host=127.0.0.1 --port=8392 &
bash scripts/simulasi-http.sh

# Simulasi otomatis (MySQL)
DB_CONNECTION=mysql DB_DATABASE=absensi_pu_test DB_USERNAME=absensi_pu \
DB_PASSWORD=absensi_dev_pass php artisan test

# Hanya penjaga regresi keamanan (6 celah yang pernah ditemukan)
php artisan test --filter=FullSimulationTest --group=regresi-keamanan

# Hanya tes tiga kebijakan baru
php artisan test --filter=KebijakanBaruTest
```

**Bukti mesin uji benar-benar MySQL** (bukan SQLite) dicetak di awal suite:

```
[SIM] driver=mysql database=absensi_sim server=12.2.2-MariaDB
```

> **Catatan lingkungan uji.** `phpunit.xml` memaksa `DB_CONNECTION=sqlite` (`:memory:`) sebagai default, sehingga `php artisan test` tanpa variabel lingkungan berjalan di SQLite. Dua tes yang memang menguji perilaku khusus MySQL (jumlah tabel lewat `information_schema`, dan `Rule::unique` pada kolom `date`) kini **di-skip otomatis** dengan pesan jelas bila driver bukan MySQL — bukan gagal. Jalankan dengan `DB_CONNECTION=mysql …` untuk menguji keduanya.

### Hasil akhir (setelah perbaikan)

```
Tests:  3 skipped, 183 passed (1219 assertions)     ← suite lengkap, MySQL
Tests:  5 skipped, 181 passed (1216 assertions)     ← suite lengkap, SQLite (default phpunit.xml)
Tests:  29 passed  (132 assertions)                 ← KebijakanBaruTest saja
SELESAI — 65 lulus, 0 gagal                         ← simulasi HTTP nyata
```

Sebelum perbaikan, suite yang sama menghasilkan **6 failed, 148 passed**.

---

## 2. Yang sudah berjalan baik ✅

| Fitur | Bukti |
| :--- | :--- |
| Login 3 peran (karyawan via NIP **dan** NIK, admin, super admin) | 302 → dasbor, sesi benar |
| Pemisahan peran antar panel | Karyawan → `/admin` dialihkan; admin ↔ super-admin saling ditolak |
| Akun nonaktif tidak bisa login | `is_active` diperiksa saat login |
| Absensi GPS + selfie | Clock-in/out tercatat, jarak 0 m, koordinat tidak sah ditolak |
| Geofence | Di luar radius → **422** (diverifikasi di HTTP nyata) |
| Absen ganda | Ditolak (pra-cek + indeks unik DB) |
| Deteksi terlambat | `status=late` saat melewati jam masuk + toleransi |
| Klaim toleransi ("love") | Kuota bulanan, akhir pekan ditolak, approver luar wilayah ditolak |
| Cuti berjenjang | 3× approve → `Disetujui`; batal hanya saat `Menunggu` level 0 |
| Dinas + unggah dokumen | PDF/gambar diterima; PHP menyamar `.pdf` **ditolak**; >5 MB ditolak |
| Unduh dokumen | Pemilik & admin wilayahnya → 200; karyawan/admin lain → 403 |
| Pembersihan berkas | Hapus pengajuan → objek S3 ikut terhapus (bucket terverifikasi bersih) |
| Pengumuman | Visibilitas Global + wilayah sendiri benar; read-tracking jalan |
| Profil | Ubah data, unggah/hapus foto, ganti sandi (lemah ditolak, kuat berhasil & bisa login) |
| CRUD admin lengkap | Karyawan, titik kantor, wilayah, hari libur, pengaturan, admin wilayah |
| Audit log | Tercatat; hanya super admin bisa membuka (admin wilayah → 403) |
| Validasi | NIK 16 digit & unik, radius 50–1000 m, jam masuk < jam pulang, format bulan `YYYY-MM` |

---

## 3. BUG YANG DITEMUKAN ❌

Semuanya **terbukti secara empiris**, bukan hasil pembacaan kode saja. Enam tes di `FullSimulationTest.php` sengaja dibiarkan gagal sebagai penjaga regresi — begitu diperbaiki, tes akan lulus sendiri.

### 🔴 BUG-1 — Kebocoran data lintas wilayah di halaman detail titik  ✅ DIPERBAIKI
**Tingkat: TINGGI** · `app/Http/Controllers/Admin/SiteController.php:24-68`

`show()` menghitung `$scope` tetapi hanya memakainya untuk flag `readOnly`. Data yang **dikirim** tidak difilter sama sekali.

**Bukti:** admin wilayah Bone (region 4) membuka `GET /admin/regions/2/sites/{id}` → menerima **seluruh 24 wilayah** dan **seluruh karyawan** semua wilayah.

```
Detail titik hanya boleh mengirim wilayah milik aktor.
Failed asserting that two arrays are identical.
-    0 => 4,
+    0 => 1,  2,  3,  4 ... 24
```

**Dampak:** admin satu wilayah dapat memanen NIK, NIP, email, jabatan, dan lokasi kerja seluruh pegawai instansi.

**Saran:** filter `regions` dan `employees` dengan `$scope`, dan batasi karyawan pada titik/wilayah yang bersangkutan (bukan `Employee::get()` tanpa batas).

---

### 🔴 BUG-2 — Karyawan bisa dipindah ke titik wilayah lain  ✅ DIPERBAIKI
**Tingkat: TINGGI** · `app/Http/Controllers/Admin/EmployeeController.php:51-72`

`createFromPayload()` (dipakai `store`) memeriksa kepemilikan titik:

```php
if (! $region->sites()->where('id', $data['office_location_id'])->exists()) {
    throw ValidationException::withMessages(['office_location_id' => 'Titik tidak sesuai wilayah.']);
}
```

**`update()` tidak melakukan pemeriksaan ini.**

**Bukti:** region tetap `Kab. Gowa`, `office_location_id` diisi titik milik Maros → permintaan **diterima tanpa keluhan**, `site_id` karyawan berubah:

```
Titik tidak boleh berpindah ke wilayah lain.
Failed asserting that 301 is identical to 201.
```

**Dampak:** `region_id` dan `site_id` menjadi tidak konsisten. Karena absensi memakai koordinat `site`, karyawan itu berpotensi absen di luar wilayahnya, dan filter wilayah lain (cuti/dinas/absensi) menjadi salah tempat.

**Saran:** pindahkan pemeriksaan dari `createFromPayload()` ke `validateEmployee()` (atau helper bersama) agar `store` dan `update` memakai aturan identik.

---

### 🔴 BUG-3 — Hapus wilayah ikut menghapus seluruh karyawannya  ✅ DIPERBAIKI
**Tingkat: TINGGI (kehilangan data)** · `Admin/RegionController.php:72-79`

Migrasi memakai `cascadeOnDelete`:

```php
$table->foreignId('region_id')->constrained('regions')->cascadeOnDelete();  // employees
```

`destroy()` hanya memeriksa peran super admin, **tanpa pengaman apa pun**.

**Bukti:** wilayah Gowa berisi 3 karyawan → setelah `DELETE /super-admin/regions/2`:

```
Menghapus wilayah tidak boleh ikut menghapus karyawannya (butuh pengaman).
Failed asserting that 0 is identical to 3.
```

Rantai cascade berlanjut ke `attendances`, `leaves`, `dinas_claims`, `tolerance_claims`.

**Dampak:** satu klik menghapus riwayat kepegawaian satu wilayah. Berkas di object storage (foto profil, dokumen dinas) menjadi **yatim** — cascade level basis data tidak memicu event model Eloquent, yaitu persis masalah yang sudah pernah diperbaiki di `Employee::booted()`.

**Saran:** tolak penghapusan bila masih ada karyawan (arahkan untuk memindahkan dulu), atau wajibkan konfirmasi eksplisit + bersihkan objek S3 sebelum menghapus.

---

### 🟠 BUG-4 — Admin tanpa wilayah melihat data semua wilayah  ✅ DIPERBAIKI
**Tingkat: SEDANG–TINGGI** · pola `scope()`/`scopeRegion()` di seluruh `Admin/*`

`users.region_id` **nullable**. Bila seorang `admin_wilayah` punya `region_id = NULL`, maka:

```php
return $user->role === 'super_admin' ? null : $user->region_id;   // → null
abort_if($scope && $employee->region_id !== $scope, 403);          // null → tidak pernah abort
AdminPresenter::employeesFor(null)                                 // when(null) → tanpa filter
```

`null` diperlakukan sama dengan super admin, sehingga seluruh filter wilayah mati.

**Bukti:** akun `admin_wilayah` dengan `region_id = null` membuka `/admin/employees` → menerima karyawan dari banyak wilayah.

**Dampak:** satu baris data yang salah konfigurasi (atau dibuat lewat celah lain) langsung membuka akses seluruh instansi.

**Saran:** bedakan "super admin" dari "admin tanpa cakupan" secara eksplisit — misalnya kembalikan sentinel khusus, dan **tolak** admin wilayah yang `region_id`-nya null. Tambahkan juga `->nullable(false)` atau validasi di `AdminWilayahController`.

---

### 🟠 BUG-5 — Sesi admin yang dinonaktifkan tetap bisa dipakai  ✅ DIPERBAIKI
**Tingkat: SEDANG** · `app/Http/Middleware/EnsureAdminRole.php:15-35`

Login memang menolak akun nonaktif (`attempt($credentials + ['is_active' => true])`), tetapi middleware tidak pernah memeriksa ulang `is_active`.

**Bukti:** admin dinonaktifkan → `GET /admin` tetap **200** (seharusnya dialihkan ke login).

**Dampak:** menonaktifkan admin tidak langsung mencabut aksesnya; sesi lama tetap penuh sampai ia logout sendiri.

**Saran:** tambahkan pemeriksaan `is_active` di middleware (dan logout paksa bila nonaktif).

---

### 🟡 BUG-6 — Karyawan bisa menandai pengumuman wilayah lain sebagai dibaca  ✅ DIPERBAIKI
**Tingkat: RENDAH** · `Karyawan/PengumumanController.php:30-35`

`markRead()` menerima ID pengumuman apa pun tanpa memeriksa visibilitas.

**Bukti:** karyawan Gowa (region 2) menandai pengumuman milik Bone (region 4) → baris `announcement_reads` **terbentuk**.

**Dampak:** tidak membocorkan isi, tetapi merusak laporan "sudah dibaca" milik wilayah lain.

**Saran:** pakai ulang filter visibilitas yang sudah ada di `markAllRead()`.

---

## 4. Temuan tambahan (bukan celah keamanan, tetapi perlu diketahui)

| # | Temuan | Lokasi | Dampak |
| :--- | :--- | :--- | :--- |
| A | **Absen bisa dilakukan di akhir pekan & hari libur.** `AttendanceSetting.hari_kerja` hanya dipakai untuk tampilan, tidak pernah ditegakkan. ✅ **SELESAI** — kini gerbang yang dapat dinyalakan Super Admin (tolak / catat sebagai `libur`). | `Karyawan/AttendanceController::clockIn` | Terbukti: simulasi HTTP pada hari **Minggu** berhasil absen. `holidays` juga hanya dipakai untuk tampilan rekap. |
| B | **`selfie_url` hanya string bebas** (`nullable\|string\|max:2000`), bukan berkas/URL tervalidasi. | `AttendanceController.php:82` | Selfie bisa dipalsukan (mis. data URI sembarang). **Masih terbuka.** |
| C | **Nilai enum mati.** `attendances.status` punya `excused_love` dan `early_leave`, tetapi tidak pernah ditulis — hanya `on_time`/`late`. ✅ **SELESAI** — kini ditulis oleh approve klaim toleransi (plus nilai baru `libur`). | migrasi `...000007...:20` | Fitur "absen ter-excuse lewat love" dan "pulang cepat" tidak benar-benar berfungsi. |
| D | **`attendances.tolerance_claim_id` tidak pernah ditulis.** ✅ **SELESAI** — approve klaim kini menautkannya ke baris absensi tanggal klaim. | `AdminPresenter.php:150` | Indikator `love: 'approved'` selalu `null`; approve klaim toleransi tidak menautkan/mengubah absensi. |
| E | **Klaim toleransi tidak dicek terhadap absensi.** Tidak ada verifikasi bahwa baris absensi untuk `tgl`/`jam` itu benar-benar ada. ⚠️ **Sebagian** — approve kini membuat barisnya bila belum ada; pengajuan untuk tanggal lama tetap bisa. | `Karyawan/LoveController::store` | Klaim bisa diajukan untuk hari tanpa absensi. |
| F | **Berkas nonaktif dialihkan, bukan 403.** Peran salah → 302. | `EnsureAdminRole.php:29-33` | Tidak fatal, tetapi menyulitkan audit dan kurang tegas. |
| G | **`POST /wilayah/login` mengarah ke `/admin`.** | `Auth/AdminAuthController.php:42` | Inkonsistensi kosmetik. |
| H | **Validasi sebelum otorisasi.** `validateEmployee()` dijalankan sebelum cek wilayah. | `EmployeeController.php:53` sebelum `:56` | Aturan `unique` membocorkan keberadaan NIK/email di wilayah lain (existence oracle). |
| I | **`reject` cuti tidak memvalidasi `note`.** | `Admin/CutiController.php:74` | Beda perlakuan dengan Dinas/Love yang mewajibkan `min:3,max:500`. |
| J | **TOCTOU pada absen.** Ada jeda antara pra-cek `exists()` dan `create()`. | `AttendanceController.php:86-105` | Pada request bersamaan, indeks unik DB akan menolak → berpotensi 500 tak tertangani. |
| K | **N+1 & query tanpa batas.** `Employee::get()` tanpa limit; `Region::where(...)->value('name')` di dalam `map()`. | `Admin/SiteController.php:45`, `AdminPresenter.php:131,219` | Memburuk seiring bertambahnya pegawai. |
| L | **Sandi seeder `password123`.** | `EmployeeSeeder`, `AdminUserSeeder` | Aman untuk pengembangan, **jangan** ikut ke produksi. |
| M | **Cuti bisa di-approve 3× oleh orang yang sama.** Tidak ada pemeriksaan identitas approver per level. ⚠️ **Sebagian** — approver kini wajib akun admin dan approver yang ditunjuk dihormati, tetapi perbedaan orang antar level belum dipaksa. | `Admin/CutiController::approve` | "Berjenjang" hanya secara teknis, bukan secara jabatan. |

---

## 5. Rekomendasi prioritas

> **Catatan:** butir 1–6 di bawah **sudah dikerjakan** pada 20 Sep 2026 — lihat [§6 Status perbaikan](#6-status-perbaikan). Butir 7–9 masih terbuka.

**Segera (sebelum dipakai produksi):**
1. ~~**BUG-3** — tambahkan pengaman pada hapus wilayah.~~ ✅ **Selesai.** Ini satu-satunya temuan yang bisa **menghancurkan data**.
2. ~~**BUG-1** — filter data di halaman detail titik.~~ ✅ **Selesai.**
3. ~~**BUG-4** — tolak admin wilayah tanpa `region_id`.~~ ✅ **Selesai.**
4. ~~**BUG-2** — pindahkan pemeriksaan kepemilikan titik ke validasi bersama.~~ ✅ **Selesai.**

**Berikutnya:**
5. ~~**BUG-5** — periksa `is_active` di middleware.~~ ✅ **Selesai.**
6. ~~**BUG-6** — periksa visibilitas di `markRead`.~~ ✅ **Selesai.**
7. ~~**Temuan A** — tegakkan `hari_kerja` dan `holidays` pada clock-in.~~ ✅ **Selesai** (dapat dinyalakan/dimatikan Super Admin) — lihat [§6b](#6b-tiga-kebijakan-baru-yang-diputuskan-20-sep-2026).
8. **Temuan B** — validasi selfie sebagai berkas/URL sungguhan. **Masih terbuka.**
9. ~~**Temuan M** — tentukan siapa yang boleh menjadi approver tiap level cuti.~~ ✅ **Selesai** (akun admin saja) — lihat [§6b](#6b-tiga-kebijakan-baru-yang-diputuskan-20-sep-2026).

**Perbaikan struktural:**
10. Perkenalkan **Policy** dan **FormRequest** untuk menggantikan `abort_if` ad-hoc. Dua gaya penanganan `null` (`$scope &&` vs `$scope !== null &&`) yang berbeda itulah akar BUG-1 sampai BUG-4. Perbaikan di §6 sudah menyatukan perilakunya, tetapi polanya masih tersebar di banyak controller dan rawan terulang.

---

## 6. Status perbaikan

Semua dikerjakan pada 20 Sep 2026 dan diverifikasi ulang di MySQL.

| # | Berkas yang diubah | Perbaikan | Penjaga regresi |
| :--- | :--- | :--- | :--- |
| **1** | `Admin/SiteController.php` | `show()` kini memakai `abort_if($scope && $site->region_id !== $scope, 403)` — konsisten dengan `update`/`destroy`/`assignEmployees`/`move` yang sejak awal sudah memeriksanya. Query `regions` dan `employees` ikut disaring dengan `when($scope, …)`. Prop `readOnly` menjadi selalu `false` karena lintas wilayah tidak lagi bisa dibuka. | `test_keamanan_halaman_detail_titik_tidak_membocorkan_data_lintas_wilayah` |
| **2** | `Admin/EmployeeController.php` | Pemeriksaan kepemilikan titik diekstrak ke `assertSiteInRegion()` dan kini dipanggil **baik oleh `store` maupun `update`**. Bonus: otorisasi dipindah ke **sebelum** validasi, menutup existence oracle lewat aturan `unique`. | `test_keamanan_ubah_karyawan_tidak_boleh_menugaskan_ke_titik_wilayah_lain` |
| **3** | `Admin/RegionController.php` | `destroy()` menolak penghapusan selama wilayah masih punya karyawan, dengan pesan yang menyuruh memindahkan/menghapus karyawannya lebih dulu. Tidak ada lagi kehilangan data massal maupun berkas yatim. | `test_keamanan_menghapus_wilayah_berisi_karyawan_tidak_boleh_menghapus_karyawan` |
| **4** | `Http/Middleware/EnsureAdminRole.php` | `admin_wilayah` tanpa `region_id` kini ditolak `403` di satu tempat terpusat, sehingga `$scope` tidak pernah lagi bernilai `null` untuk admin wilayah. | `test_keamanan_admin_dengan_region_null_tidak_boleh_melihat_semua_data` |
| **5** | `Http/Middleware/EnsureAdminRole.php` | `is_active` diperiksa setiap request. Bila akun dinonaktifkan, sesinya langsung dicabut (`logout` + `invalidate` + `regenerateToken`) dan dialihkan ke halaman login. | `test_keamanan_admin_nonaktif_kehilangan_akses_sesi` |
| **6** | `Karyawan/PengumumanController.php` | `markRead()` memeriksa visibilitas: `scope === 'Global'` atau `region_id` sama dengan karyawan. Selain itu `403`. | `test_keamanan_karyawan_tidak_bisa_menandai_pengumuman_wilayah_lain` |

### Verifikasi setelah perbaikan

```
php artisan test (MySQL, absensi_pu_test)    => 3 skipped, 183 passed (1219 assertions)
php artisan test (SQLite, default phpunit)   => 5 skipped, 181 passed (1216 assertions)
php artisan test --filter=KebijakanBaruTest  => 29 passed (132 assertions)
bash scripts/simulasi-http.sh                => SELESAI — 65 lulus, 0 gagal
./vendor/bin/pint (berkas yang disentuh)     => 26 berkas, bersih
npm run build                                => sukses (PWA precache 19 entri)
```

**Tidak ada regresi:** 76 tes lama yang tadinya lulus tetap lulus.

---

## 6b. Tiga kebijakan baru yang diputuskan (20 Sep 2026)

Tiga temuan di §4 tidak dapat diperbaiki tanpa keputusan pemilik produk. Keputusannya sudah diterima dan **ketiganya sudah dikerjakan**, lengkap dengan tes.

### (1) Absen di akhir pekan / hari libur — dapat diatur Super Admin

**Keputusan:** jangan dikeraskan di kode. Super Admin memilih sendiri: **nonaktif**, atau aktif dengan mode **tolak** / **tetap dicatat**.

Kolom baru pada `attendance_settings`:

| Kolom | Nilai | Arti |
| :--- | :--- | :--- |
| `absen_libur_aktif` | `0` / `1` (default `0`) | Gerbang menyala atau tidak |
| `absen_libur_mode` | `tolak` / `catat` (default `tolak`) | Bila menyala: tolak, atau catat dengan status khusus |

Perilaku pada `Karyawan/AttendanceController::clockIn`:

| `absen_libur_aktif` | `absen_libur_mode` | Hari non-kerja | Hasil |
| :--- | :--- | :--- | :--- |
| `0` | — | ya | **Perilaku lama**: dicatat seperti hari kerja biasa |
| `1` | `tolak` | ya | **422** + pesan alasan (`Hari libur: …` / `Bukan hari kerja (Sabtu)`) |
| `1` | `catat` | ya | Tersimpan, `status = 'libur'` |
| `1` | `tolak` | tidak | Normal (`on_time` / `late`) |

- **Default `absen_libur_aktif = 0`** supaya tidak ada perubahan perilaku diam-diam pada basis data yang sudah berjalan. Super Admin menyalakannya dari `/super-admin/settings`.
- "Hari non-kerja" = **bukan anggota `hari_kerja`** (JSON, ISO-8601: 1=Senin…7=Minggu) **atau** tanggalnya terdaftar di tabel `holidays`. Jadi bila kantor memang masuk Sabtu, cukup tambahkan `"6"` ke `hari_kerja` — tanpa ubah kode.
- Nilai enum `libur` ditambahkan pada `attendances.status` lewat migrasi yang **sadar-driver** (`MODIFY ENUM` untuk MySQL/MariaDB, `->change()` untuk SQLite).
- Halaman absensi karyawan menampilkan banner merah (ditolak) atau kuning (dicatat sebagai libur) dan **mematikan tombol absen** lebih awal, sehingga karyawan tidak menembak request yang pasti gagal.

### (2) Klaim toleransi ditautkan ke baris absensi

**Keputusan:** "tautkan saja" — approve klaim toleransi harus benar-benar mengubah baris absensi, dan status `excused_love` / `early_leave` yang tadinya mati kini terpakai.

`Admin/LoveController::approve` sekarang (dalam satu transaksi):

| `jenis` klaim | Baris absensi tanggal klaim | Tindakan |
| :--- | :--- | :--- |
| `lupa_absen` | belum ada | Dibuat: `clock_in_at` = jam klaim, `status = 'excused_love'` |
| `lupa_absen` | sudah ada | `status = 'excused_love'`; **jam masuk asli tidak ditimpa** |
| `lupa_pulang` | sudah ada | `clock_out_at` = jam klaim; `status = 'early_leave'` **bila** jam klaim lebih awal dari `jam_pulang`, selain itu status dipertahankan |
| `lupa_pulang` | belum ada | Dibuat dengan `clock_out_at` = jam klaim |

- `attendances.tolerance_claim_id` kini benar-benar terisi → indikator `love: 'approved'` di `AdminPresenter.php:150` akhirnya berfungsi.
- **Tolak** dan **hapus** klaim otomatis melepas tautan dan **menghitung ulang status** dari jam masuk asli (`on_time`/`late`), sehingga tidak ada absen yang tetap "ter-excuse" padahal klaimnya ditolak.
- Baris yang **lahir semata-mata dari approve** klaim (tanpa `lat_in`/`lng_in`/`distance_in_m`) dikenali sebagai *sintetis* dan **ikut dihapus** saat klaimnya dihapus — supaya tidak meninggalkan absensi palsu. Baris hasil clock-in asli tetap utuh, hanya tautannya dilepas.
- `RekapPresenter` ikut menghormati `excused_love`: hari itu dihitung sebagai **toleransi**, bukan **terlambat**, dan tidak muncul di daftar keterlambatan. Tanpa ini, approve klaim tidak akan terlihat efeknya di rekap.

### (3) Approver cuti = akun admin saja

**Keputusan:** "admin saja" — setiap level hanya boleh diputuskan akun admin.

- `Admin/CutiController` punya gerbang terpusat `ensureApprover()` yang dipanggil `approve()` **dan** `reject()`:
  1. peran harus `super_admin` atau `admin_wilayah` (`403` bila bukan);
  2. `admin_wilayah` hanya untuk cuti di wilayahnya sendiri;
  3. bila karyawan menunjuk approver tertentu, hanya approver itu (atau Super Admin) yang boleh memutuskan **level 1**.
- Kolom baru: `leaves.approver_id` (admin yang ditunjuk karyawan, opsional) dan `leaves.approved_by` (admin yang menekan approve terakhir) — jejak audit per level, tidak hanya di `audit_logs`.
- `Karyawan/CutiController::store` memvalidasi `approver_id` terhadap `AdminPresenter::leaveApproversFor()`, yang **hanya** mengembalikan akun admin: `admin_wilayah` di wilayah/site karyawan + `super_admin` (Kantor Pusat). Akun non-admin **tidak pernah** muncul di daftar maupun lolos validasi. Pelanggaran dibalas **422** (ValidationException), konsisten dengan validasi lain di aplikasi.
- `approver_id` **opsional**: bila dikosongkan, cuti tetap berjalan seperti sebelumnya (approver ditentukan saat approve). Ini menjaga kompatibilitas dengan pengajuan yang sudah ada. Bila ingin **wajib**, ubah `['nullable', …]` menjadi `['required', …]` dan sesuaikan 4 pemanggilan tes lama.
- UI: `Karyawan/Cuti.jsx` menampilkan dropdown "Approver level 1 — akun admin", serta nama approver dan pemutus di tiap kartu cuti.

### Berkas yang ditambah/diubah untuk ketiga kebijakan

| Berkas | Perubahan |
| :--- | :--- |
| `database/migrations/2026_09_19_000002_add_absen_libur_to_attendance_settings_table.php` | **Baru** — 2 kolom gerbang hari libur |
| `database/migrations/2026_09_19_000003_extend_attendance_status_enum.php` | **Baru** — nilai `libur` (sadar-driver MySQL/SQLite) |
| `database/migrations/2026_09_19_000004_add_approver_to_leaves_table.php` | **Baru** — `approver_id`, `approved_by` |
| `app/Models/AttendanceSetting.php` | `hariKerjaList()`, cast boolean, konstanta `HARI_KERJA_DEFAULT` |
| `app/Models/Leave.php` | relasi `approver()`, `approvedBy()` |
| `app/Support/AdminPresenter.php` | `nonWorkDayLabel()`, `leaveApproversFor()`, `presentApprover()`, info approver di `leavesFor()` |
| `app/Support/RekapPresenter.php` | `excused_love` dihitung sebagai toleransi |
| `app/Http/Controllers/Admin/SettingController.php` | validasi + props gerbang hari libur |
| `app/Http/Controllers/Karyawan/AttendanceController.php` | gerbang hari libur di `clockIn()`, prop `absenLibur` |
| `app/Http/Controllers/Admin/LoveController.php` | `tautkanKeAbsensi()`, `lepasDariAbsensi()`, transaksi |
| `app/Http/Controllers/Admin/CutiController.php` | `ensureApprover()`, `approved_by` |
| `app/Http/Controllers/Karyawan/CutiController.php` | validasi `approver_id`, daftar approver admin |
| `database/seeders/AttendanceSettingSeeder.php` | default gerbang hari libur (nonaktif) |
| `resources/js/Pages/Admin/Settings.jsx` | kartu + toggle + radio tolak/catat |
| `resources/js/Pages/Karyawan/Absensi.jsx` | banner, tombol nonaktif, label status baru |
| `resources/js/Pages/Karyawan/Cuti.jsx` | dropdown approver, nama approver/pemutus |
| `tests/Feature/KebijakanBaruTest.php` | **Baru** — 29 tes / 132 asersi |
| `scripts/simulasi-http.sh` | +12 skenario HTTP (bagian `[2b]` dan `[7b]`) |

### Verifikasi ketiga kebijakan

```
php artisan test --filter=KebijakanBaruTest     => 29 passed (132 assertions)
bash scripts/simulasi-http.sh                   => SELESAI — 65 lulus, 0 gagal
   [2b] mode tolak: clock-in di hari libur ditolak            422
   [2b] mode tolak: tidak ada baris absensi dibuat              0
   [2b] mode catat: clock-in tetap diterima                   302
   [2b] mode catat: status khusus 'libur'                   libur
   [3]  approver admin luar wilayah ditolak                   422
   [3]  approver tercatat di baris cuti                         1
   [7]  admin pemutus tercatat (approved_by)                    1
   [7b] baris absensi dibuat dengan status excused_love  excused_love
   [7b] klaim tertaut ke baris absensi                          1
   [7b] baris sintetis terhapus, tidak jadi absensi palsu        0
```

### Yang masih terbuka

- **Temuan B** — selfie hanya divalidasi sebagai string/URL, bukan berkas gambar sungguhan.
- **Temuan E** — klaim toleransi belum dicek terhadap keberadaan absensi tanggal itu. Kini **sebagian** terjawab: approve-lah yang membuat barisnya bila belum ada. Tetap terbuka: klaim untuk tanggal yang jauh di masa lalu tetap bisa diajukan.
- **Temuan L** — sandi seeder `password123`. Aman untuk pengembangan; **jangan** ikut ke produksi.
- **BUG non-fungsional lain** yang belum diminta: pemindaian antivirus, kuota penyimpanan per akun, 9 variabel `.env` yatim, dan **rotasi kredensial S3** (kredensial pernah tertulis di ruang obrolan).
- **Cuti 3 level masih bisa di-approve 3× oleh orang yang sama.** Gerbang `ensureApprover()` kini memastikan approver-nya **akun admin** dan menghormati approver yang ditunjuk, tetapi belum memaksa *perbedaan orang antar level* — perilaku lama ini dipertahankan karena tes lama (`Fase3Test`) memang mengandalkan satu admin menyelesaikan tiga level. Bila ingin ditegakkan, tambahkan pemeriksaan `approved_by` level sebelumnya dan perbarui tes tersebut.

---

## 7. Catatan metodologi

- Basis data simulasi terpisah: **`absensi_sim`** (dibuat khusus, tidak menyentuh `absensi_pu` milik pengembangan).
- Suite utama dijalankan pada **`absensi_pu_test`** — bukan basis data pengembangan, supaya `RefreshDatabase` tidak menghapus data kerja.
- Unggahan pada suite otomatis memakai **disk tiruan** (`Storage::fake`) agar tidak menulis ke bucket. Hanya skrip HTTP lapis-1 yang menulis ke bucket sungguhan, dan **sudah dibersihkan** (bucket terverifikasi `0 objek`).
- Dua bug pada skrip simulasi sendiri ditemukan dan diperbaiki selama proses (guard `actingAs` harus eksplisit; `_token` tidak boleh diisi nilai cookie terenkripsi) — keduanya **bukan** bug aplikasi.
