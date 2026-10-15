# Panduan Deploy — Checklist Keamanan

Disusun oleh: **Rahmat Dani S**

Dokumen ini berisi hal-hal yang **harus** dipenuhi saat aplikasi dipasang di server.
Bagian kode sudah mengunci dirinya sendiri; sisanya bergantung pada konfigurasi server
dan tidak bisa diperiksa dari dalam aplikasi.

---

## 1. Wajib sebelum go-live

| # | Item | Nilai yang benar | Kenapa |
|---|---|---|---|
| 1 | `APP_ENV` | `production` | Mengaktifkan CSP, HTTPS paksa, cookie secure |
| 2 | `APP_DEBUG` | `false` | Jika `true`, halaman galat menampilkan kredensial DB & isi `.env` |
| 3 | `APP_KEY` | terisi (`php artisan key:generate`) | Tanpa ini sesi & enkripsi tidak aman |
| 4 | `SESSION_SECURE_COOKIE` | `true` | Cookie sesi hanya lewat HTTPS |
| 5 | `SESSION_ENCRYPT` | `true` (disarankan) | Isi cookie sesi ikut terenkripsi |
| 6 | `APP_URL` | `https://domain-anda` | Link & aset memakai HTTPS |
| 7 | `APP_TIMEZONE` | `Asia/Makassar` | Jangan diubah — semua presensi mengikuti zona ini |

Setelah mengubah `.env` di server:

```bash
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> Aplikasi **sengaja menolak start** bila `APP_ENV=production` tetapi `APP_DEBUG=true`.
> Pesan galatnya menyebutkan baris `.env` yang perlu diperbaiki.

---

## 2. Sertifikat & HTTPS

- Pasang sertifikat TLS yang valid (Let's Encrypt cukup) dan **paksa** redirect
  `http://` → `https://` di Nginx/Apache.
- Jangan biarkan server bisa diakses langsung lewat IP tanpa TLS.
- Setelah HTTPS aktif, header `Strict-Transport-Security` akan terkirim otomatis.

---

## 3. Service worker (prompt "Install aplikasi")

Berkas service worker ada di `/build/sw.js`, tetapi cakupannya harus root (`/`) supaya
seluruh halaman ikut terkontrol. Karena berkas statis dilayani langsung oleh web server
(tidak lewat PHP), header berikut **wajib** dipasang di konfigurasi server:

**Nginx** — tambahkan di dalam blok `server`:

```nginx
location = /build/sw.js {
    add_header Service-Worker-Allowed "/";
    add_header Cache-Control "no-cache";
}
```

**Apache** — tambahkan di `.htaccess` dalam folder `public/`:

```apache
<Files "sw.js">
    Header set Service-Worker-Allowed "/"
</Files>
```

Uji setelah dipasang: buka aplikasi di peramban, buka konsol. Bila muncul pesan
`The path of the provided scope ('/') is not under the max scope allowed ('/build/')`,
header belum terpasang.

---

## 4. Akses berkas & basis data

- Kredensial database hanya boleh dipakai oleh aplikasi. **Jangan** buka port
  MySQL/MariaDB ke internet — cukup `127.0.0.1`.
- Folder `storage/` dan `.env` **tidak boleh** bisa diakses lewat URL. Pastikan
  document root mengarah ke folder `public/`, bukan ke akar proyek.
- Swafoto absensi tersimpan di penyimpanan **privat** dan hanya keluar lewat
  endpoint ber-otorisasi. Jangan ubah pengaturan disk menjadi publik.
- Backup harian untuk database dan bucket penyimpanan berkas. Uji restore minimal
  sekali sebelum go-live.

---

## 5. Akun & kata sandi

- Ganti seluruh kata sandi akun contoh (`super admin`, admin wilayah, karyawan)
  sebelum data asli masuk. Kata sandi awal karyawan baru sama dengan NIK dan
  **wajib** diganti saat login pertama — pastikan aturan itu tidak dilonggarkan.
- Hapus atau nonaktifkan akun yang tidak lagi dipakai. Akun nonaktif otomatis
  tidak bisa memakai sesi lama.
- Kata sandi akun database dan kredensial penyimpanan berkas jangan ditulis di
  dokumen, chat, atau repositori.

---

## 6. Yang sudah otomatis dari sisi aplikasi

Tidak perlu diatur lagi, tetapi berguna untuk diketahui saat pemeriksaan:

- Pembatasan percobaan login (10 kali / 5 menit per IP + akun), unggahan berkas
  (10 kali / menit per akun), dan ganti kata sandi (5 kali / 5 menit per akun).
- Admin wilayah hanya bisa melihat dan mengubah data wilayahnya sendiri —
  termasuk swafoto, rekap, jadwal shift, dan koreksi catatan.
- Header keamanan: `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`,
  `Permissions-Policy`, dan CSP ketat (aktif otomatis di produksi).
- Semua tindakan penting (tambah/ubah/hapus karyawan, reset kata sandi, approve
  cuti, unduh rekap, koreksi catatan) tercatat di log audit.

---

## 7. Belum tersedia (bila nanti dibutuhkan)

- **Autentikasi dua faktor (2FA)** untuk akun admin — belum ada. Bila kebijakan
  instansi mensyaratkan, ini pekerjaan tambahan tersendiri.
- **Pemantauan & notifikasi** log audit secara berkala — saat ini log tersimpan
  dan bisa dilihat super admin, tetapi belum ada peringatan otomatis.
