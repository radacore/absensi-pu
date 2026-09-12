# 24 — PWA Install & Offline

Aplikasi dibundle sebagai Progressive Web App via `vite-plugin-pwa` mode `generateSW` + `registerType: autoUpdate`. Karyawan bisa install ke home screen dan akses beberapa fitur offline.

## Aktor
- **Karyawan / Admin** — pengguna browser modern (Chrome, Edge, Safari).
- **Browser** — engine PWA + service worker.
- **Service Worker** — file `/build/sw.js` hasil Workbox precache 19 entries.

## Alur Install & Update

```mermaid
flowchart TD
    Start((Kunjungan pertama)) --> B1

    subgraph User[User]
        B1[Buka aplikasi di browser mobile atau desktop]
        B4[/Lihat install prompt browser/]
        B5{Install ke home screen?}
        B6[Skip prompt]
        B7[Install PWA]
        B8[Icon aplikasi muncul di home screen / app drawer]
        B9[Buka aplikasi sebagai standalone tanpa URL bar]
        U1[Refresh atau navigasi]
        U2[/SW notifikasi update tersedia/]
    end

    subgraph Browser[Browser]
        B2[Load index.html + register /build/sw.js]
        B3[SW install → precache 19 entries CSS + JS + fonts + icon]
        BR1[SW aktif intercept fetch]
        BR2{Request di cache?}
        BR3[Serve dari cache offline-first untuk static]
        BR4[Fetch dari network]
    end

    subgraph SW[Service Worker autoUpdate]
        SW1[Waktu build baru: hash bundle berubah]
        SW2[Deteksi sw.js baru saat visit berikut]
        SW3[Download precache baru di background]
        SW4[Ganti SW aktif skipWaiting default]
        SW5[Trigger reload otomatis atau tunggu user reload]
    end

    B1 --> B2
    B2 --> B3
    B3 --> B4
    B4 --> B5
    B5 -->|Tidak| B6
    B5 -->|Ya| B7
    B7 --> B8
    B8 --> B9
    B9 --> BR1
    B6 --> BR1
    BR1 --> BR2
    BR2 -->|Ya| BR3
    BR2 -->|Tidak| BR4
    BR3 --> End((Konten tampil))
    BR4 --> End
    U1 --> SW1
    SW1 --> SW2
    SW2 --> SW3
    SW3 --> SW4
    SW4 --> SW5
    SW5 --> U2

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef browser fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    classDef sw fill:#FEF3C7,stroke:#F59E0B,stroke-width:1px,color:#92400E
    class User actor
    class Browser browser
    class SW sw
```

## Alur Offline (Attempt)

```mermaid
flowchart TD
    Start((User tanpa koneksi)) --> O1

    subgraph User[User]
        O1[Klik menu Absensi]
        O5[/Halaman tampil dari cache tanpa data terbaru/]
        O6[/Toast: Anda offline, fitur terbatas/]
        O7[Klik Kirim absen masuk]
        O8[/Toast error: Butuh koneksi internet/]
        O9[Tunggu online lalu klik ulang]
    end

    subgraph SW[Service Worker]
        SW1{Request GET halaman?}
        SW2[Serve cached shell HTML + JS + CSS]
        SW3{Request POST API?}
        SW4[Bypass ke network]
        SW5{Network available?}
        SW6[Fetch response server]
        SW7[Fail dengan error network]
    end

    O1 --> SW1
    SW1 -->|Ya| SW2
    SW2 --> O5
    O5 --> O6
    O6 --> O7
    O7 --> SW3
    SW3 -->|Ya| SW4
    SW4 --> SW5
    SW5 -->|Tidak| SW7
    SW7 --> O8
    O8 --> O9
    O9 --> End((Retry))
    SW5 -->|Ya| SW6
    SW6 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef sw fill:#FEF3C7,stroke:#F59E0B,stroke-width:1px,color:#92400E
    class User actor
    class SW sw
```

## Catatan implementasi
- Konfigurasi PWA di `vite.config.js`: `registerType: 'autoUpdate'`, precache 19 entries (~894 KiB) meliputi CSS, JS bundle, font woff2, dan icon PWA 192/512.
- Icon manifest: `public/pwa-192x192.png`, `public/pwa-512x512.png`. Nama aplikasi "BBWS Pompengan Jeneberang".
- Tidak ada background sync atau IndexedDB untuk offline queue clock-in. Fitur POST butuh network aktif; ini keputusan sadar untuk mencegah absensi palsu dari cache.
- Auto-update: user tidak perlu manual re-install. Refresh halaman setelah SW update selesai memasang bundle baru.
