# Aplikasi Laravel — BBWS Pompengan Jeneberang

Folder ini adalah **akar aplikasi** (bukan akar repositori). Semua perintah `composer`, `npm`, dan `php artisan` dijalankan dari sini.

📖 **Dokumentasi lengkap — tech stack, arsitektur penyimpanan S3, dan panduan deploy native ke VPS — ada di [`../README.md`](../README.md).**

## Ringkas

| | |
| :--- | :--- |
| Backend | Laravel 13 · PHP 8.4 |
| Frontend | React 19 · Inertia v3 · Vite 8 · Tailwind v4 · PWA |
| Database | SQLite (sekarang) → MySQL 8.4 (target) |
| Penyimpanan | S3-compatible (Neva Objects) untuk semua unggahan |

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate --force
npm run build
php artisan serve
```

```bash
php artisan test                                  # uji hermetis
S3_INTEGRATION=1 php artisan test --filter=S3...  # uji sungguhan ke bucket S3
```

> ⚠️ Pada disk S3, **jangan** memakai `Storage::exists()`, `size()`, atau `mimeType()` — ketiganya memicu `HEAD` yang sesekali dibalas `403` oleh endpoint. Gunakan `readStream()`/`get()`. Rinciannya di `../README.md`.
