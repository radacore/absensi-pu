# 16 — Pengumuman Karyawan (Baca dan Tandai)

Karyawan melihat daftar pengumuman Global + wilayahnya. Badge unread muncul di sidebar Info.

## Aktor
- **Karyawan** — pembaca.
- **Sistem** — `Karyawan\PengumumanController` + `AdminPresenter::announcementsForKaryawan` + `HandleInertiaRequests` (share `notifications.unreadAnnouncements`).

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Login dan lihat badge angka di menu Info]
        K2[Klik menu Info /karyawan/pengumuman]
        K3[/Lihat daftar Global + region sendiri<br/>Item unread ditandai visual/]
        K4{Aksi?}
        K5[Klik item untuk baca]
        K6[Klik Tandai Semua Dibaca]
        K7[ConfirmDialog default:<br/>Tandai semua pengumuman sebagai dibaca?]
        K8{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[HandleInertiaRequests::share<br/>hitung notifications.unreadAnnouncements<br/>= count Announcement Global+region - reads]
        S2[PengumumanController::index<br/>load Announcement scope karyawan + readIds]
        S3[POST /karyawan/pengumuman/id/read]
        S4[AnnouncementRead::firstOrCreate<br/>announcement_id + employee_id]
        S5[[Flash success: Ditandai dibaca]]
        S6[POST /karyawan/pengumuman/read-all]
        S7[Loop semua Announcement in scope<br/>firstOrCreate AnnouncementRead per id]
        S8[[Flash success: Semua ditandai dibaca]]
        S9[Rerender: shared notifications.unreadAnnouncements = 0]
    end

    K1 --> S1
    S1 --> K2
    K2 --> S2
    S2 --> K3
    K3 --> K4
    K4 -->|Baca 1| K5
    K5 --> S3
    S3 --> S4
    S4 --> S5
    S5 --> S9
    S9 --> End((Selesai))
    K4 -->|Tandai semua| K6
    K6 --> K7
    K7 --> K8
    K8 -->|Tidak| End
    K8 -->|Ya| S6
    S6 --> S7
    S7 --> S8
    S8 --> S9

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Badge angka di sidebar `KaryawanLayout` diambil dari `props.notifications.unreadAnnouncements` (shared via `HandleInertiaRequests`). Berkurang otomatis setelah tandai baca karena Inertia refresh shared props saat back().
- Idempoten: `firstOrCreate` mencegah duplicate row read.
- Scope karyawan: `where scope=Global OR region_id=user->region_id`.
