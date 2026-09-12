# 23 — Karyawan Dashboard (Home)

Halaman `/karyawan` yang jadi home setelah login. Menampilkan sapaan, ringkasan status, quick action, dan sisa kuota toleransi.

## Aktor
- **Karyawan** — user aktif.
- **Sistem** — `Karyawan\DashboardController::index` + shared props Inertia.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Login sukses redirect /karyawan]
        K2[/Lihat sapaan: Selamat pagi/siang/malam, nama<br/>Tanggal + jam WITA<br/>Assigned: titik + wilayah + jam kerja/]
        K3[/Lihat sisa kuota Toleransi X/4/]
        K4[/Lihat quick action cards: Absensi, Cuti/]
        K5[/Lihat stat cards: hadir bulan ini + info baru pengumuman/]
        K6[/Lihat kartu Rekap bulanan link ke /rekap/]
        K7{Ada banner ganti password?}
        K8[Ikuti banner ke /karyawan/profil]
        K9[Klik salah satu link]
    end

    subgraph Sistem[Sistem]
        S1[DashboardController::index]
        S2[Load employee->load region + site]
        S3[AdminPresenter::settingsFor untuk loveMax + jam kerja]
        S4[AdminPresenter::loveQuota untuk sisa kuota]
        S5[AnnouncementRead::pluck readIds]
        S6[AdminPresenter::announcementsForKaryawan untuk unread count]
        S7[Count Attendance bulan berjalan untuk hadir]
        S8[Count Leave employee untuk cutiCount]
        S9[Kirim ke view: me, assigned, settings,<br/>quota, hadir, unread, cutiCount, myCuti recent 3]
        S10[HandleInertiaRequests::share<br/>notifications.unreadAnnouncements +<br/>auth.employee.must_change_password]
    end

    K1 --> S1
    S1 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> S5
    S5 --> S6
    S6 --> S7
    S7 --> S8
    S8 --> S9
    S9 --> S10
    S10 --> K2
    K2 --> K3
    K3 --> K4
    K4 --> K5
    K5 --> K6
    K6 --> K7
    K7 -->|Ya| K8
    K7 -->|Tidak| K9
    K8 --> End((Navigate))
    K9 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Sapaan berdasarkan waktu WITA: `< 11 = pagi`, `< 15 = siang`, `< 18 = sore`, `else = malam`.
- Banner "Kata sandi masih NIK" muncul di layout, bukan di dashboard, jadi sticky di semua halaman karyawan (lihat [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md)).
- Badge Info di navigation bar menampilkan `unreadAnnouncements` real-time.
- Data hadir bulan ini di-count dari `Attendance` table dengan filter `whereYear` + `whereMonth` sesuai Asia/Makassar.
