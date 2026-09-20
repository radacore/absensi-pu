# BBWS Pompengan Jeneberang — Absensi & HR

Aplikasi kepegawaian **Balai Besar Wilayah Sungai Pompengan Jeneberang** — pusat di **Makassar** + kantor wilayah se-Sulawesi Selatan — dengan portal HR multi-tenant dan **PWA Karyawan** (login email, absensi GPS + selfie dengan radius kantor, *love system*, cuti berjenjang, rekap bulanan, pengumuman, dan pengajuan **perjalanan dinas** dengan unggah dokumen).

> **Status dokumen ini:** seluruh versi, jumlah tabel, jumlah route, dan perilaku penyimpanan di bawah **sudah diverifikasi langsung dari kode dan dari basis data yang berjalan** pada 20 Sep 2026.
>
> Basis data **sudah benar-benar pindah ke MySQL/MariaDB** — bukan lagi rencana. Bukti eksekusi ada di bagian [Basis Data](#basis-data-mysql--mariadb): 19 migrasi + 6 seeder berjalan bersih, 21 tabel terbentuk, 188 route, **183 tes lulus di MySQL**, dan simulasi HTTP nyata **65/65 lulus**. Bagian yang belum bisa diuji tanpa server produksi tetap ditandai **[BELUM DIVERIFIKASI]**.
>
> Laporan simulasi end-to-end lengkap (6 celah keamanan yang ditemukan & diperbaiki, plus 13 temuan tambahan): **[`docs/laporan-simulasi.md`](docs/laporan-simulasi.md)**.

---

## Daftar Isi

1. [Tech Stack (aktual)](#tech-stack-aktual)
2. [Arsitektur Penyimpanan Berkas (S3)](#arsitektur-penyimpanan-berkas-s3)
3. [Kebijakan Absensi & Persetujuan](#kebijakan-absensi--persetujuan)
4. [Prasyarat VPS](#prasyarat-vps)
5. [Instalasi Native di VPS](#instalasi-native-di-vps)
6. [Konfigurasi `.env`](#konfigurasi-env)
7. [Konfigurasi Nginx](#konfigurasi-nginx)
8. [Queue Worker & Scheduler](#queue-worker--scheduler)
9. [Basis Data: MySQL / MariaDB](#basis-data-mysql--mariadb)
10. [Testing](#testing)
11. [Jebakan & Catatan Penting](#jebakan--catatan-penting)
12. [Troubleshooting](#troubleshooting)
13. [Keamanan](#keamanan)
14. [Struktur Repositori](#struktur-repositori)

---

## Tech Stack (aktual)

| Layer | Teknologi | Versi terpasang | Catatan |
| :--- | :--- | :--- | :--- |
| Bahasa | PHP | **8.4.18** (`^8.3` di composer.json) | Butuh ekstensi `pdo_mysql`, `mbstring`, `gd`/`imagick`, `zip`, `intl`, `bcmath`, `opcache` |
| Framework | Laravel | **v13.26.1** | Skeleton default |
| Manajer paket PHP | Composer | **2.9.5** | |
| Bridge frontend | `inertiajs/inertia-laravel` | **v3.3.1** | Inertia **v3**, bukan v2 |
| UI | React + `@inertiajs/react` | **19.2.8** / **^3.7.0** | |
| Build tool | Vite | **8.2.2** (rolldown) | |
| Plugin Laravel | `laravel-vite-plugin` | **^3.1** | |
| Plugin React | `@vitejs/plugin-react` | **^6.1.0** | |
| CSS | Tailwind CSS | **v4** (`@tailwindcss/vite`) | Tema gold `#FCB833`, navy `#0F172A` |
| PWA | `vite-plugin-pwa` + `workbox-window` | **1.3.0** / **7.4.1** | `registerType: autoUpdate` |
| Peta | Leaflet | **1.9.4** | Tile OpenStreetMap |
| Font | Bunny Fonts | — | Diunduh **saat build**, jadi build butuh internet |
| Object storage | `league/flysystem-aws-s3-v3` | **3.35.3** | + `aws/aws-sdk-php` **3.395.6** |
| Database | **MySQL / MariaDB** (aktif) | MariaDB **12.2.2** di lokal | Sudah diverifikasi — lihat [Basis Data](#basis-data-mysql--mariadb) |
| Test | PHPUnit | **12.5.33** | |
| Formatter | Laravel Pint | **v1.30.5** | |
| Runtime JS | Node.js | **22.22.2** (npm 10.9.7) | Butuh `^20.19.0 \|\| >=22.12.0` |

**Yang TIDAK dipakai** (jangan dipasang di VPS): Redis, Memcached, Sanctum, Prisma, Sentry, Telescope, Debugbar, Horizon.
Session, cache, dan queue semuanya memakai driver **`database`** — jadi **MySQL wajib hidup** sebelum aplikasi bisa melayani request. Tanpa MySQL, bahkan halaman login akan gagal (`SQLSTATE[HY000] [2002]`).

**Skala aplikasi:** 188 route, 21 tabel (terverifikasi di MySQL).

---

## Arsitektur Penyimpanan Berkas (S3)

Seluruh berkas unggahan disimpan di **object storage S3-compatible (Neva Objects)**, bukan di disk server. Ini penting untuk VPS: **disk server tidak perlu besar** dan tidak perlu backup berkas terpisah.

Aplikasi hanya punya **dua jalur unggah**:

| Jenis | Disk | Visibility | Cara diakses |
| :--- | :--- | :--- | :--- |
| Foto profil karyawan | `UPLOAD_PUBLIC_DISK` | `public` | URL S3 langsung |
| Dokumen surat tugas (perjalanan dinas) | `UPLOAD_PRIVATE_DISK` | `private` | Lewat route ber-otorisasi saja |

Pemetaan disk diatur di `app/config/filesystems.php`:

```php
'uploads' => [
    'public_disk'  => env('UPLOAD_PUBLIC_DISK', 'public'),
    'private_disk' => env('UPLOAD_PRIVATE_DISK', 'local'),
],
```

Kode **tidak pernah** menulis nama disk secara hardcode — semuanya lewat `config('filesystems.uploads.*')`, jadi berpindah dari disk lokal ke S3 cukup dengan mengubah `.env` tanpa menyentuh kode.

### ⚠️ Jebakan penting: `HEAD` tidak stabil di endpoint S3 ini

Endpoint Neva Objects **sesekali membalas `403 Forbidden` pada permintaan `HEAD`**. Hasil pengukuran 30 permintaan `HEAD` berturut-turut atas objek yang sama:

```
HEAD x30 => 200: 29 kali, 403: 1 kali      ← ~3% gagal
GET  x30 => 200 + isi cocok: 30 kali       ← stabil
```

Dampaknya berbahaya karena AWS SDK (`doesObjectExistV2`) **sengaja melempar ulang** `403` — hanya `404` yang dianggap "tidak ada". Jadi berkas yang **benar-benar ada** bisa dianggap hilang dan halaman gagal dengan `500`.

**Aturan:** pada disk S3, **jangan** memakai `Storage::exists()`, `size()`, atau `mimeType()` — ketiganya memicu `HEAD`. Gunakan `readStream()` atau `get()` (`GET`).

Karena itu unduhan dokumen dinas memakai trait `app/app/Support/StreamsDokumen.php`, yang membaca langsung lewat `readStream()` dan menerjemahkan kegagalan menjadi `404`:

```php
$stream = $disk->readStream($dinas->dokumen_path);
abort_if(! is_resource($stream), 404);
```

Ukuran berkas untuk header `Content-Length` diambil dari kolom `dokumen_size` di basis data, bukan dari `HEAD` ke S3.

> `php artisan storage:link` **tidak lagi diperlukan** karena berkas tidak dilayani dari `public/storage`. Symlink lama boleh dibiarkan.

---

## Kebijakan Absensi & Persetujuan

Tiga aturan yang sebelumnya belum diputuskan kini sudah ditetapkan dan dikerjakan (20 Sep 2026). Detail lengkap + tesnya ada di [`docs/laporan-simulasi.md` §6b](docs/laporan-simulasi.md).

### 1. Absen di akhir pekan / hari libur — diatur Super Admin

Dua kolom di `attendance_settings`: `absen_libur_aktif` (default `0`) dan `absen_libur_mode` (`tolak` / `catat`, default `tolak`). Diatur dari **`/super-admin/settings`** (Admin Wilayah hanya baca).

| `absen_libur_aktif` | `absen_libur_mode` | Hari non-kerja | Hasil clock-in |
| :--- | :--- | :--- | :--- |
| `0` | — | ya | Dicatat seperti hari kerja biasa (perilaku lama) |
| `1` | `tolak` | ya | **422** dengan alasan (`Hari libur: …` / `Bukan hari kerja (Sabtu)`) |
| `1` | `catat` | ya | Tersimpan dengan `status = 'libur'` |
| `1` | `tolak` | tidak | Normal (`on_time` / `late`) |

- **Default nonaktif** supaya basis data yang sudah berjalan tidak berubah perilaku diam-diam.
- "Hari non-kerja" = bukan anggota `attendance_settings.hari_kerja` (JSON, ISO-8601: `1`=Senin … `7`=Minggu) **atau** terdaftar di tabel `holidays`. Kantor yang masuk Sabtu cukup menambahkan `"6"` ke `hari_kerja` — tanpa mengubah kode.
- Halaman absensi karyawan mematikan tombol absen lebih awal dan menampilkan banner alasan.

### 2. Klaim toleransi ("love") ditautkan ke baris absensi

Menyetujui klaim di `/admin/love` (atau `/super-admin/love`) kini benar-benar mengubah baris absensi tanggal klaim, dalam satu transaksi:

- `lupa_absen` → `clock_in_at` diisi dari jam klaim bila masih kosong, `status = 'excused_love'`. Jam masuk asli **tidak** ditimpa.
- `lupa_pulang` → `clock_out_at` diisi dari jam klaim; `status = 'early_leave'` bila jam klaim lebih awal dari `jam_pulang`.
- Bila baris absensi belum ada (karyawan benar-benar lupa tap), baris dibuat agar persetujuan punya jejak di rekap.
- **Tolak / hapus** klaim otomatis melepas tautan dan menghitung ulang status dari jam masuk asli. Baris yang lahir semata-mata dari approve (tanpa GPS) ikut dihapus supaya tidak jadi absensi palsu.
- `RekapPresenter` menghitung `excused_love` sebagai **toleransi**, bukan **terlambat**.

### 3. Approver cuti = akun admin saja

- Setiap level hanya boleh diputuskan akun **`super_admin`** atau **`admin_wilayah`** (`403` bila bukan), lewat gerbang terpusat `ensureApprover()` di `Admin/CutiController`.
- `admin_wilayah` hanya untuk cuti di wilayahnya sendiri.
- Karyawan boleh menunjuk approver level 1 (`leaves.approver_id`, **opsional**). Bila ditunjuk, hanya approver itu — atau Super Admin — yang boleh memutuskan level 1. Daftar pilihannya hanya berisi akun admin (`AdminPresenter::leaveApproversFor()`); akun non-admin ditolak `422`.
- `leaves.approved_by` mencatat admin yang menekan approve terakhir, sebagai jejak audit per level.

> **Catatan:** rantai 3 level masih dapat diselesaikan oleh satu admin yang sama (perilaku lama, dipertahankan agar kompatibel). Bila ingin setiap level wajib orang berbeda, tambahkan pemeriksaan `approved_by` level sebelumnya di `ensureApprover()`.

---

## Prasyarat VPS

- **OS:** Ubuntu 24.04 LTS (atau 22.04)
- **RAM:** minimal 2 GB (2 vCPU disarankan untuk build Vite)
- **Perangkat lunak:** Nginx, PHP-FPM 8.4, Composer 2, Node.js 22, MySQL 8.4 (atau MariaDB 10.6+), Git
- **Domain + sertifikat TLS** (Let's Encrypt)
- **Kredensial S3:** access key, secret key, nama bucket, endpoint

> **Penting:** build frontend mengunduh font dari Bunny Fonts, jadi **butuh akses internet saat `npm run build`**. Sertakan juga `--prefer-dist` untuk Composer.

---

## Instalasi Native di VPS

Semua perintah di bawah dijalankan sebagai `root` (atau dengan `sudo`).

### 1. Paket dasar

```bash
apt update && apt upgrade -y
apt install -y nginx git unzip curl ca-certificates ufw
```

### 2. PHP 8.4 + ekstensi

```bash
add-apt-repository ppa:ondrej/php -y
apt update
apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
    php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-opcache
php -v   # pastikan 8.4.x
```

### 3. Composer

```bash
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
composer --version   # 2.x
```

### 4. Node.js 22

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
node -v   # v22.x
```

### 5. MySQL 8.4 (atau MariaDB 10.6+)

Keduanya sudah diuji dan berjalan. Contoh di bawah memakai MySQL 8.4 seperti di Ubuntu/Debian.

```bash
apt install -y mysql-server
mysql_secure_installation

mysql -u root -p <<'SQL'
CREATE DATABASE absensi_pu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'absensi'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON absensi_pu.* TO 'absensi'@'localhost';
FLUSH PRIVILEGES;
SQL
```

### 6. Ambil kode & pasang dependensi

> **Perhatikan:** akar repositori adalah `sul proyek/`, sedangkan aplikasi Laravel ada di subfolder **`app/`**. Composer dan npm harus dijalankan **di dalam `app/`**.

```bash
mkdir -p /var/www && cd /var/www
git clone <URL_REPO> absensi
cd absensi/app

composer install --no-dev --optimize-autoloader --no-interaction
npm ci
```

### 7. Konfigurasi `.env`

```bash
cp .env.example .env
php artisan key:generate
nano .env          # isi sesuai tabel di bagian Konfigurasi .env
```

### 8. Migrasi basis data

```bash
php artisan migrate --force
# opsional, bila seeder menyediakan data awal:
php artisan db:seed --force
```

### 9. Build frontend

```bash
npm run build      # menghasilkan app/public/build/ + service worker PWA
```

### 10. Hak akses

```bash
chown -R www-data:www-data /var/www/absensi/app/storage /var/www/absensi/app/bootstrap/cache
chmod -R 775 /var/www/absensi/app/storage /var/www/absensi/app/bootstrap/cache
```

### 11. Cache produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Setiap kali `.env` berubah, jalankan `php artisan config:clear` lalu `config:cache` lagi.

---

## Konfigurasi `.env`

Nilai yang **wajib** disesuaikan untuk produksi:

```env
APP_NAME="BBWS Pompengan Jeneberang"
APP_ENV=production
APP_DEBUG=false
APP_KEY=                      # diisi oleh: php artisan key:generate
APP_URL=https://absensi.example.go.id

# --- Basis data (MySQL 8.4 / MariaDB) ---
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi_pu
DB_USERNAME=absensi
DB_PASSWORD=GANTI_PASSWORD_KUAT

# --- Driver (semua di basis data, bukan Redis) ---
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# --- Penyimpanan berkas: arahkan ke S3 ---
UPLOAD_PUBLIC_DISK=s3
UPLOAD_PRIVATE_DISK=s3

AWS_ACCESS_KEY_ID=xxxxxxxxxxxxxxxxxxxx
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=bbws-local
AWS_ENDPOINT=https://s3.nevaobjects.id
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Catatan:

- `AWS_USE_PATH_STYLE_ENDPOINT=true` **wajib** untuk Neva Objects. Bila `false`, URL akan berbentuk `https://<bucket>.<endpoint>/...` dan gagal.
- `FILESYSTEM_DISK` sengaja dibiarkan `local`. Disk default tidak dipakai kode aplikasi — semua jalur unggah menyebut disk secara eksplisit lewat `config('filesystems.uploads.*')`. Mengubahnya tidak berpengaruh, tetapi juga tidak diperlukan.
- `AWS_URL` boleh dikosongkan; URL publik dibentuk otomatis dari `AWS_ENDPOINT` + bucket.

---

## Konfigurasi Nginx

`/etc/nginx/sites-available/absensi`:

```nginx
server {
    listen 80;
    server_name absensi.example.go.id;
    root /var/www/absensi/app/public;   # <-- document root ada di dalam app/public

    index index.php;
    charset utf-8;

    client_max_body_size 20M;           # unggah dokumen maks 5 MB, beri ruang

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Aset hasil build Vite punya hash di namanya -> aman di-cache lama
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    location ~ /\.(?!well-known).* { deny all; }
}
```

Aktifkan dan pasang TLS:

```bash
ln -s /etc/nginx/sites-available/absensi /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
apt install -y certbot python3-certbot-nginx
certbot --nginx -d absensi.example.go.id
```

> **PHP-FPM:** pastikan `upload_max_filesize` dan `post_max_size` minimal `8M` (batas aplikasi 5 MB). Edit `/etc/php/8.4/fpm/php.ini` lalu `systemctl restart php8.4-fpm`.
>
> **`opcache`:** aktifkan `opcache.validate_timestamps=0` di produksi agar tidak mengecek perubahan berkas tiap request.

---

## Queue Worker & Scheduler

Karena `QUEUE_CONNECTION=database`, worker perlu dijalankan sebagai layanan. `/etc/systemd/system/absensi-worker.service`:

```ini
[Unit]
Description=Absensi PU — queue worker
After=network.target mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/absensi/app
ExecStart=/usr/bin/php /var/www/absensi/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

Scheduler (cron) — `/etc/cron.d/absensi`:

```cron
* * * * * www-data cd /var/www/absensi/app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Aktifkan:

```bash
systemctl daemon-reload
systemctl enable --now absensi-worker
systemctl status absensi-worker
```

---

## Basis Data: MySQL / MariaDB

Aplikasi **sudah berjalan penuh di MySQL/MariaDB**. Ini bukan lagi target — statusnya sudah diverifikasi langsung terhadap server yang hidup.

### Bukti verifikasi (20 Sep 2026)

| Pemeriksaan | Perintah | Hasil |
| :--- | :--- | :--- |
| Driver yang dipakai Laravel | `php artisan about --only=drivers` | `Database ............ mysql` |
| Info server & basis data | `php artisan db:show` | **MariaDB 12.2.2**, koneksi `mysql`, DB `absensi_pu`, host `127.0.0.1:3306` |
| Jumlah tabel | `SHOW TABLES` | **21 tabel** (`BASE TABLE`) |
| Storage engine | `php artisan db:table employees` | **InnoDB**, kolasi `utf8mb4_unicode_ci` |
| Skema dari nol | `php artisan migrate:fresh --seed` | **19 migrasi + 6 seeder sukses** |
| Data ikut masuk | query `JOIN` | `employees` × `regions` mengembalikan relasi yang benar |
| Tes di MySQL | `php artisan test` (DB `absensi_pu_test`) | **76 passed, 3 skipped, 767 assertions** |
| Smoke test HTTP | `php artisan serve` + `curl` | `/`, `/karyawan/login`, `/admin/login`, `/super-admin/login` → **200** |

Konfigurasi `.env` yang aktif:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=absensi_pu
DB_USERNAME=absensi_pu
DB_PASSWORD=absensi_dev_pass
```

### Kompatibilitas skema

Skema ditulis agar netral terhadap mesin basis data, sehingga aman untuk MySQL 8.4 (produksi) maupun MariaDB 12.x (lokal):

- Tidak ada `->change()` — menghindari ketergantungan Doctrine DBAL.
- Tidak ada indeks pada kolom `TEXT`.
- `enum` dan `json` sudah native.
- Semua indeks unik jauh di bawah batas 3072 byte (utf8mb4).

### Menyiapkan basis data baru

```bash
# 1. Buat basis data + user
mysql -u root -p <<'SQL'
CREATE DATABASE absensi_pu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'absensi'@'localhost' IDENTIFIED BY 'GANTI_PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON absensi_pu.* TO 'absensi'@'localhost';
FLUSH PRIVILEGES;
SQL

# 2. Set DB_CONNECTION=mysql + kredensial di .env

# 3. Bangun skema + data awal
php artisan migrate:fresh --seed --force

# 4. Bila ada data yang harus dibawa dari SQLite, ekspor lalu impor
#    (sesuaikan urutan tabel dengan foreign key)
```

Setelah mengubah `.env`, **selalu**:

```bash
php artisan config:clear && php artisan config:cache
```

### ⚠️ `AUTO_INCREMENT` tidak di-reset oleh `ROLLBACK`

Ini jebakan paling mahal saat pindah dari SQLite ke MySQL, dan sudah diperbaiki di `RegionSeeder`:

> SQLite mengembalikan counter `AUTO_INCREMENT` saat transaksi di-`ROLLBACK`; **MySQL tidak**. Akibatnya, bila seeder dijalankan dua kali dalam satu proses (pola `RefreshDatabase`), penyemaian kedua menghasilkan ID wilayah 25–48, sementara `EmployeeSeeder`/`AdminUserSeeder` serta beberapa tes memakai `region_id` **hardcoded** (2 dan 4). Hasilnya: `SQLSTATE[23000] foreign key constraint fails (employees.region_id)`.

Solusi yang dipakai — ID wilayah dibuat **deterministik**:

```php
foreach ($regions as $i => $r) {
    $region = Region::firstOrNew(['slug' => $r['slug']]);
    $region->fill($r);
    if (! $region->exists) {
        $region->id = $i + 1;   // 1..24, sama di setiap penyemaian
    }
    $region->save();
}
```

**Bila Anda menambah region baru, jangan menyisipkannya di tengah daftar** — tambahkan di akhir agar ID lama tidak bergeser.

### Basis data terpisah untuk pengujian

Tes dijalankan pada basis data **`absensi_pu_test`**, bukan `absensi_pu`, supaya data pengembangan tidak terhapus:

```bash
cd app
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=absensi_pu_test DB_USERNAME=absensi_pu DB_PASSWORD=absensi_dev_pass \
  php artisan test
```

Bila MySQL tidak tersedia, tes tetap bisa dijalankan hermetis lewat SQLite in-memory (perilaku bawaan `phpunit.xml`) — hasilnya identik: 76 lulus.


---

## Testing

```bash
cd app

# Uji unit/feature — hermetis, tidak menyentuh S3, cepat (~8 detik)
# Bawaan: SQLite in-memory (lihat phpunit.xml)
php artisan test

# Uji yang sama, tetapi benar-benar di atas MySQL/MariaDB
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3306 \
DB_DATABASE=absensi_pu_test DB_USERNAME=absensi_pu DB_PASSWORD=absensi_dev_pass \
  php artisan test

# Uji integrasi S3 sungguhan (menulis & menghapus objek di bucket)
S3_INTEGRATION=1 php artisan test --filter=S3DinasIntegrationTest

# Hanya tiga kebijakan yang diputuskan 20 Sep 2026
php artisan test --filter=KebijakanBaruTest
```

Hasil terakhir yang terverifikasi (20 Sep 2026):

```
php artisan test                                    => 181 passed, 5 skipped (1216 assertions)  [SQLite in-memory, 16 s]
php artisan test (DB absensi_pu_test)               => 183 passed, 3 skipped (1219 assertions)  [MySQL/MariaDB, 24 s]
php artisan test --filter=KebijakanBaruTest         => 29 passed (132 assertions)
S3_INTEGRATION=1 php artisan test --filter=S3...    => 3 passed (17 assertions)
bash scripts/simulasi-http.sh                       => SELESAI — 65 lulus, 0 gagal
npm run build                                       => sukses (PWA precache 19 entri)
```

**Suite yang sama lulus di kedua mesin basis data.** Jumlah yang di-skip berbeda karena dua tes memang menguji perilaku khusus MySQL dan **melewati diri sendiri** (bukan gagal) bila driver bukan MySQL:

- `test_prasyarat_simulasi_berjalan_di_mysql` — membaca `information_schema` untuk menghitung tabel.
- `test_super_admin_mengelola_hari_libur` — mengandalkan `Rule::unique` pada kolom `date`. SQLite menyimpan `date` sebagai `"Y-m-d 00:00:00"`, sehingga perbandingan `Rule::unique` tidak cocok dan duplikat baru ditolak oleh *unique index* di level basis data. Di MySQL (kolom `DATE` asli) aturan validasi bekerja seperti seharusnya.

Uji unit **memalsukan disk** yang sedang dikonfigurasi, jadi hasilnya tidak bergantung pada apakah `UPLOAD_PRIVATE_DISK` diisi `local` atau `s3`. Uji integrasi S3 **dilewati secara bawaan** agar CI tidak butuh jaringan dan tidak menulis ke bucket sungguhan.

Berkas uji yang relevan:

| Berkas | Cakupan |
| :--- | :--- |
| `tests/Feature/DinasDokumenTest.php` | Unggah, validasi tipe/ukuran, otorisasi unduh (IDOR), anti path traversal |
| `tests/Feature/StorageCleanupTest.php` | Pembersihan berkas yatim, pembatasan laju unggah, audit akses berkas |
| `tests/Feature/S3DinasIntegrationTest.php` | Alur sungguhan ke bucket S3 (opt-in) |
| `tests/Feature/FullSimulationTest.php` | Seluruh alur + penjaga regresi 6 celah keamanan (`--group=regresi-keamanan`) |
| `tests/Feature/KebijakanBaruTest.php` | Gerbang absen hari libur, penautan klaim toleransi ke absensi, approver cuti = admin |

**Simulasi HTTP sungguhan** — `app/scripts/simulasi-http.sh` (65 skenario) menembak `php artisan serve` lewat HTTP, jadi middleware, CSRF, sesi, Inertia, MySQL, dan **bucket S3 sungguhan** semuanya ikut teruji. Cara menjalankan ada di komentar kepala skrip. Lihat [`docs/laporan-simulasi.md`](docs/laporan-simulasi.md).

---

## Jebakan & Catatan Penting

1. **Jangan pakai `Storage::exists()`/`size()`/`mimeType()` pada disk S3.** Lihat [bagian S3](#-jebakan-penting-head-tidak-stabil-di-endpoint-s3-ini). Gunakan `readStream()`/`get()`.
2. **Composer bisa "menggantung" tanpa keluaran** saat mengunduh paket besar (`aws/aws-sdk-php`, ~7,8 MB) meski jaringan normal. Bila macet, unduh zipball-nya manual ke cache Composer (`~/.cache/composer/files/<vendor>/<paket>/<sha1(dist-url)>.zip`), lalu jalankan `COMPOSER_DISABLE_NETWORK=1 composer install --no-scripts`.
3. **`composer dump-autoload` bisa gagal** di `package:discover` dengan `rename(...): Operation not permitted`. Aman dilewati: `composer install --no-scripts`. `league/flysystem-aws-s3-v3` dan `aws/aws-sdk-php` **tidak** mendaftarkan service provider Laravel, jadi `bootstrap/cache/packages.php` tidak perlu diperbarui.
4. **`php artisan storage:link` tidak diperlukan lagi** bila unggahan ke S3.
5. **Akar aplikasi adalah `app/`**, bukan akar repo. `composer`, `npm`, dan `php artisan` harus dijalankan dari sana. Document root Nginx = `app/public`.
6. **Build butuh internet** (font Bunny diunduh saat build).
7. **Rotasi kredensial S3** bila pernah dibagikan lewat chat/issue.
8. **Jangan andalkan `AUTO_INCREMENT` untuk ID yang direferensikan kode.** MySQL tidak mengembalikan counter saat `ROLLBACK`, berbeda dari SQLite. Bila seeder perlu ID tetap, tetapkan eksplisit. Lihat [`RegionSeeder`](#-auto_increment-tidak-di-reset-oleh-rollback).
9. **`prd-profilkorp/` dan `README.md` lama menggambarkan aplikasi yang berbeda** — dokumen tersebut menyebut MySQL 8.4, Prisma, Sanctum 4.x, Redis 7, `/api/karyawan/*`, dan tabel `office_locations` yang **tidak ada** di kode ini (tabel sebenarnya `sites`, dan tidak ada `routes/api.php`). Jangan dijadikan acuan. Lihat [Keamanan](#keamanan) untuk variabel `.env` yatim.

---

## Troubleshooting

| Gejala | Penyebab & solusi |
| :--- | :--- |
| Halaman `500`, log: `Unable to check existence for: ...` | Kode memakai `exists()` (`HEAD`) pada disk S3. Ganti ke `readStream()`/`get()` — lihat trait `StreamsDokumen`. |
| `403 Forbidden` acak saat mengunduh dokumen | Sama seperti di atas; endpoint membalas `403` sesaat pada `HEAD`. |
| Unggahan foto/dokumen hilang setelah deploy | `AWS_USE_PATH_STYLE_ENDPOINT` belum `true`, atau kredensial salah. Uji dengan `S3_INTEGRATION=1 php artisan test --filter=S3DinasIntegrationTest`. |
| Foto profil `403` saat dibuka | Objek tersimpan `private`. Pastikan unggahan memakai `visibility => 'public'` (disk `UPLOAD_PUBLIC_DISK`). |
| `Class "Aws\S3\S3Client" not found` | `composer install` belum tuntas. Cek `grep Aws vendor/composer/autoload_psr4.php`. |
| Perubahan `.env` tidak berefek | `php artisan config:clear && php artisan config:cache`. |
| Aset lama masih tampil setelah deploy | `npm run build` ulang + bersihkan cache service worker (PWA `autoUpdate`). |
| `SQLSTATE[HY000] [2002]` | MySQL mati atau kredensial salah. Session/cache/queue bergantung pada DB. |
| `SQLSTATE[23000] ... foreign key constraint fails (employees.region_id)` | Seeder dijalankan ulang di MySQL: `AUTO_INCREMENT` tidak di-reset oleh `ROLLBACK`. Lihat [`RegionSeeder`](#-auto_increment-tidak-di-reset-oleh-rollback). |
| `Bad magic header in tc log` / `Crash recovery failed` (MariaDB lokal) | `tc.log` korup setelah mati mendadak. Hentikan **watchdog `mariadbd-safe` lebih dulu**, baru daemon-nya, lalu pindahkan `tc.log` yang korup ke samping dan jalankan ulang. |
| Unggah > 5 MB gagal `413` | Naikkan `client_max_body_size` (Nginx) dan `upload_max_filesize`/`post_max_size` (PHP-FPM). |

---

## Keamanan

> **Hasil simulasi end-to-end (20 Sep 2026):** seluruh fitur berjalan. Simulasi menemukan **6 celah keamanan** (termasuk 1 risiko kehilangan data) — **keenamnya sudah diperbaiki** dan diverifikasi ulang. Bukti, rincian, dan saran ada di **[`docs/laporan-simulasi.md`](docs/laporan-simulasi.md)**.
>
> Reproduksi:
> ```bash
> cd app
> # 1) Simulasi HTTP nyata (65 skenario, termasuk unggah ke S3 sungguhan)
> bash scripts/simulasi-http.sh
> # 2) Simulasi otomatis (78 tes)
> php artisan test --filter=FullSimulationTest
> # 3) Penjaga regresi 6 celah keamanan yang pernah ditemukan
> php artisan test --filter=FullSimulationTest --group=regresi-keamanan
> # 4) Tiga kebijakan absensi & persetujuan (29 tes)
> php artisan test --filter=KebijakanBaruTest
> ```
>
> Hasil terakhir: **183 lulus / 0 gagal** (suite lengkap di MySQL) dan **65/65** pada simulasi HTTP.

Sudah aman dan terverifikasi: nama berkas dibuat server (anti path traversal), tidak ada header yang bisa disuntik (anti CRLF), berkas PHP yang disamarkan `.pdf` ditolak (pemeriksaan isi, bukan ekstensi), objek privat tidak bisa diakses anonim (`403`), dan `X-Content-Type-Options: nosniff`.

Otorisasi **per pemilik** sudah benar: dokumen dinas hanya bisa diunduh pemiliknya atau admin wilayahnya (karyawan lain → `403`), cuti hanya bisa dibatalkan pemiliknya, dan klaim toleransi lintas wilayah ditolak.

Otorisasi **per wilayah** juga sudah ditutup pada 20 Sep 2026 setelah simulasi end-to-end menemukan 4 kebocoran. Yang berlaku sekarang:

- `admin_wilayah` **wajib** punya `region_id`; tanpa itu request ditolak `403` (dicek terpusat di `EnsureAdminRole`).
- Halaman detail titik menolak wilayah lain (`403`) dan hanya mengirim data wilayah aktor.
- Karyawan tidak bisa dipindah ke titik milik wilayah lain, baik lewat `store` maupun `update`.
- Menghapus wilayah yang masih berisi karyawan **ditolak** — mencegah cascade `employees.region_id` menghapus data massal dan meninggalkan berkas yatim di object storage.
- Akun admin yang dinonaktifkan **langsung kehilangan sesinya**, tidak menunggu logout.
- Karyawan hanya bisa menandai pengumuman yang terlihat olehnya.
- **Approver cuti wajib akun admin** (`super_admin` / `admin_wilayah`); akun non-admin ditolak, dan `admin_wilayah` hanya untuk cuti di wilayahnya. Bila karyawan menunjuk approver level 1, hanya approver itu (atau Super Admin) yang boleh memutuskan level 1.
- Menyetujui klaim toleransi hanya mengubah absensi di **wilayah admin yang menyetujui** — penautan ke baris absensi berada di dalam transaksi yang sama dengan perubahan status klaim.

### Sudah ditutup

1. **Pembatasan laju unggah.** Limiter `uploads` di `AppServiceProvider` — **10 unggahan per menit per akun** (di-key ke ID karyawan/admin, jatuh ke IP bila belum login). Diterapkan pada `POST /karyawan/dinas` dan `PUT /karyawan/profil`. Pengajuan ke-11 ditolak dan tidak tersimpan.
2. **Berkas yatim.** Dihapus otomatis lewat event model, bukan lagi bergantung pada controller:
   - `DinasClaim::deleting` → membuang dokumen di object storage. Berlaku untuk **semua** jalur penghapusan.
   - `Employee::deleting` → menghapus baris dinas lewat Eloquent lebih dulu (supaya event di atas ikut jalan — cascade foreign key di level basis data **tidak** memicu event model), lalu membuang foto profil.
   - `ProfilController` → foto lama dibuang saat diganti, dan saat `destroyFoto`.
   - Pemetaan URL → kunci objek diturunkan dari disk itu sendiri (`app/Support/MediaCleanup.php`), sehingga tetap benar untuk disk lokal maupun S3. `data:` URL dari pratinjau sisi klien dilewati dengan aman.
3. **Audit akses berkas.** Unduhan dokumen dinas kini dicatat ke `audit_logs` dengan aksi `dinas.dokumen` (pelaku, ID pengajuan, nama berkas) — sebelumnya hanya aksi hapus/ubah yang teraudit.

### Belum ditangani

1. **Tidak ada pemindaian antivirus.** Berkas hanya divalidasi tipe dan ukuran, bukan isi berbahaya. Bila perlu, sisipkan pemindai (mis. ClamAV) sebelum `store()`.
2. **Kuota penyimpanan per akun belum ada.** Limiter membatasi *laju*, bukan *total*. Akun yang sabar tetap bisa menumpuk berkas.
3. **Variabel `.env` yatim** — dipakai di 0 tempat: `SUPER_ADMIN_PATH`, `WILAYAH_PATH`, `KARYAWAN_PATH`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `ATTENDANCE_STRICT_GEOFENCE`, `LOVE_MAX_DEFAULT`, `LOVE_RESET_CRON`, `SANCTUM_STATEFUL_DOMAINS`. Sebaiknya dihapus agar tidak menyesatkan.

---

## Struktur Repositori

```
sul proyek/                      # akar repo (git root)
├── README.md                    # dokumen ini
├── app/                         # aplikasi Laravel 13 + React 19  <-- jalankan composer/npm di sini
│   ├── app/
│   │   ├── Http/Controllers/    # Karyawan/ dan Admin/
│   │   ├── Models/
│   │   └── Support/             # AdminPresenter, Audit, StreamsDokumen (trait unduh S3)
│   ├── config/filesystems.php   # blok 'uploads' + disk s3
│   ├── database/migrations/     # 21 tabel
│   ├── resources/js/Pages/      # Karyawan/ dan Admin/
│   ├── routes/web.php           # 188 route (tidak ada routes/api.php)
│   ├── tests/Feature/           # termasuk S3DinasIntegrationTest (opt-in)
│   ├── vite.config.js           # Tailwind v4 + React + PWA
│   └── .env.example
├── docs/activity-diagrams/
└── prd-profilkorp/              # ⚠️ dokumen PRD aplikasi LAIN — jangan dijadikan acuan
```

---

## License

MIT
