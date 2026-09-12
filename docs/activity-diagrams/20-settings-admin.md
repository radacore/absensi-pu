# 20 — Settings Admin

Pengaturan absensi global. Hanya Super Admin yang boleh menulis; Admin Wilayah read-only.

## Aktor
- **Super Admin** — full akses.
- **Admin Wilayah** — hanya lihat (badge "read-only" di sidebar).
- **Sistem** — `Admin\SettingController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/settings atau /admin/settings]
        A2{Role super_admin?}
        A3[Read-only view + badge]
        A4[/Ubah jam_masuk, jam_pulang, toleransi menit,<br/>love_max 1..10, hari_kerja, timezone/]
        A5[Klik Simpan pengaturan]
    end

    subgraph Sistem[Sistem]
        S1[SettingController::index<br/>load AttendanceSetting + readOnly flag]
        S2[PUT /super-admin/settings]
        S3{User role super_admin?}
        S4[403 Forbidden]
        S5[Validate jamMasuk HH:MM,<br/>jamPulang HH:MM after jamMasuk,<br/>toleransi 0..60, loveMax 1..10]
        S6{Valid?}
        S7[Kembalikan errors]
        S8[AttendanceSetting::update]
        S9[[Flash success: Pengaturan disimpan]]
    end

    A1 --> S1
    S1 --> A2
    A2 -->|Tidak| A3
    A3 --> End((Selesai))
    A2 -->|Ya| A4
    A4 --> A5
    A5 --> S2
    S2 --> S3
    S3 -->|Tidak| S4
    S3 -->|Ya| S5
    S5 --> S6
    S6 -->|Tidak| S7
    S7 --> End
    S6 -->|Ya| S8
    S8 --> S9
    S9 --> End
    S4 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- `AttendanceSetting` adalah singleton (single row, id=1). Update selalu mengubah baris ini.
- Perubahan `love_max` berlaku bulan berikutnya (reset di 1st 00:00 WITA). Sisa bulan berjalan tetap pakai kuota lama.
- `hari_kerja` dan `timezone` tidak diubah dari UI saat ini (default Sen–Jum Asia/Makassar).
