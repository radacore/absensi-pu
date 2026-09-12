# 24 — PWA Install & Offline

Aplikasi dibundle sebagai Progressive Web App via `vite-plugin-pwa` mode `generateSW` + `registerType: autoUpdate`. User bisa install ke home screen dan mengakses shell aplikasi walau offline.

## Aktor
- **User** — Karyawan / Admin di browser modern (Chrome, Edge, Safari).
- **Browser** — engine PWA yang meng-register service worker.
- **Service Worker** — file `/build/sw.js` hasil Workbox precache 19 entries (~894 KiB).

## Flowchart — Install & Auto-Update

```mermaid
flowchart TD
    START([MULAI]):::se

    U1["1. Kunjungan pertama:<br/>buka URL aplikasi"]:::user
    S1["2. Browser load index.html<br/>register /build/sw.js"]:::sys
    S2["3. SW install:<br/>precache 19 entries<br/>CSS + JS + fonts + icon"]:::sys

    U2["4. Lihat install prompt<br/>browser"]:::user
    D1{"Install ke<br/>home screen?"}:::dec
    U3["5a. Skip prompt<br/>lanjut di browser"]:::user
    U4["5b. Install PWA"]:::user
    S3["6b. Icon muncul di<br/>home screen / app drawer"]:::sys
    U5["7b. Buka sebagai standalone<br/>tanpa URL bar"]:::user

    S4["8. SW aktif intercept fetch"]:::sys
    D2{"Request<br/>ada di cache?"}:::dec
    S5["9a. Serve dari cache<br/>offline-first (static)"]:::sys
    S6["9b. Fetch dari network"]:::sys

    U6["10. Refresh / navigasi<br/>kunjungan berikutnya"]:::user
    S7["11. Deteksi sw.js baru<br/>hash bundle berubah"]:::sys
    S8["12. Download precache<br/>baru di background"]:::sys
    S9["13. Ganti SW aktif<br/>skipWaiting default"]:::sys

    OK["SUKSES<br/>Konten tampil<br/>SW ready + auto-update"]:::ok
    END([SELESAI]):::se

    START --> U1
    U1 --> S1
    S1 --> S2
    S2 --> U2
    U2 --> D1
    D1 -->|tidak| U3
    D1 -->|ya| U4
    U4 --> S3
    S3 --> U5
    U3 --> S4
    U5 --> S4
    S4 --> D2
    D2 -->|ya| S5
    D2 -->|tidak| S6
    S5 --> OK
    S6 --> OK
    OK --> U6
    U6 --> S7
    S7 --> S8
    S8 --> S9
    S9 --> END

    classDef se fill:#0F172A,stroke:#0F172A,color:#fff,stroke-width:2px
    classDef user fill:#EFF6FF,stroke:#1E3A8A,color:#1E3A8A,stroke-width:1.5px
    classDef sys fill:#F8FAFC,stroke:#334155,color:#0F172A,stroke-width:1.5px
    classDef dec fill:#FEF3C7,stroke:#F59E0B,color:#92400E,stroke-width:1.5px
    classDef err fill:#FEE2E2,stroke:#EF4444,color:#991B1B,stroke-width:1.5px
    classDef ok fill:#DCFCE7,stroke:#10B981,color:#065F46,stroke-width:1.5px

    linkStyle default stroke:#334155,stroke-width:1.5px
```

## Flowchart — Alur Offline

```mermaid
flowchart TD
    START([MULAI]):::se

    O1["1. User tanpa koneksi<br/>klik menu Absensi"]:::user
    S1["2. SW intercept:<br/>request GET halaman?"]:::sys
    D1{"Request<br/>tipe GET shell?"}:::dec
    S2["3. Serve cached shell<br/>HTML + JS + CSS"]:::sys

    O2["4. Halaman tampil<br/>dari cache"]:::user
    O3["5. Toast: Anda offline,<br/>fitur terbatas"]:::user
    O4["6. Klik Kirim<br/>absen masuk"]:::user

    S3["7. SW bypass ke network<br/>untuk POST API"]:::sys
    D2{"Network<br/>available?"}:::dec
    S4["8. Fetch response server"]:::sys
    E1["ERROR<br/>Butuh koneksi internet"]:::err

    O5["9. Tunggu online<br/>lalu retry"]:::user
    OK["SUKSES<br/>Absen tercatat"]:::ok
    END([SELESAI]):::se

    START --> O1
    O1 --> S1
    S1 --> D1
    D1 -->|ya| S2
    S2 --> O2
    O2 --> O3
    O3 --> O4
    O4 --> S3
    S3 --> D2
    D2 -->|tidak| E1
    E1 --> O5
    O5 -->|retry| O4
    D2 -->|ya| S4
    S4 --> OK
    OK --> END

    classDef se fill:#0F172A,stroke:#0F172A,color:#fff,stroke-width:2px
    classDef user fill:#EFF6FF,stroke:#1E3A8A,color:#1E3A8A,stroke-width:1.5px
    classDef sys fill:#F8FAFC,stroke:#334155,color:#0F172A,stroke-width:1.5px
    classDef dec fill:#FEF3C7,stroke:#F59E0B,color:#92400E,stroke-width:1.5px
    classDef err fill:#FEE2E2,stroke:#EF4444,color:#991B1B,stroke-width:1.5px
    classDef ok fill:#DCFCE7,stroke:#10B981,color:#065F46,stroke-width:1.5px

    linkStyle default stroke:#334155,stroke-width:1.5px
```

## Legenda Warna Node

| Warna | Jenis |
|---|---|
| Hitam pekat | Start / End |
| Biru muda | Aksi Aktor (User) |
| Abu-abu putih | Proses Sistem / Browser / Service Worker |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

### Install & Auto-Update
1. User membuka URL aplikasi untuk pertama kali dari browser mobile/desktop.
2. Browser load `index.html` dan meregister `/build/sw.js`.
3. SW jalankan `install` phase: precache 19 entries (CSS, JS bundle, font woff2, icon PWA 192/512).
4. Browser menawarkan install prompt (Add to Home Screen).
5. (a) User skip → tetap jalan di tab browser. (b) User install → PWA di-install.
6. Icon aplikasi muncul di home screen / app drawer.
7. User membuka aplikasi sebagai standalone (tanpa URL bar).
8. SW aktif intercept semua request `fetch`.
9. Cek cache: (a) hit → serve dari cache (offline-first untuk static). (b) miss → fetch dari network.
10. Pada kunjungan berikutnya (refresh atau navigasi), SW cek update.
11. Deteksi `sw.js` baru karena hash bundle berubah setelah build ulang.
12. Download precache baru di background tanpa mengganggu sesi user.
13. `skipWaiting` (default `registerType: autoUpdate`) → SW baru langsung aktif untuk kunjungan selanjutnya.

### Offline
1. User tanpa koneksi internet klik menu Absensi.
2. SW intercept request; cek apakah ini GET untuk halaman/shell.
3. Kalau ya → serve cached shell HTML + JS + CSS.
4. Halaman tampil dari cache, meskipun tanpa data terbaru.
5. Toast informasi: "Anda offline, fitur terbatas".
6. User tetap coba klik Kirim absen masuk.
7. SW bypass request POST ke network (tidak di-cache).
8. Cek network: kalau ada → fetch response server, absen tercatat. Kalau tidak ada → error network.
9. User diminta tunggu online lalu retry.

## Catatan implementasi
- Konfigurasi PWA di `vite.config.js`: `registerType: 'autoUpdate'`, precache 19 entries (~894 KiB).
- Icon manifest: `public/pwa-192x192.png`, `public/pwa-512x512.png`. Nama aplikasi "BBWS Pompengan Jeneberang".
- Tidak ada background sync atau IndexedDB untuk offline queue clock-in. Fitur POST butuh network aktif — keputusan sadar untuk mencegah absensi palsu dari cache.
- Auto-update: user tidak perlu manual re-install. Refresh halaman setelah SW baru terpasang → bundle terbaru aktif.
