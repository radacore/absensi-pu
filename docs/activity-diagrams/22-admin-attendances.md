# 22 — Admin Attendances (Kelola Kehadiran)

Halaman `/admin/attendances` untuk Admin lihat, filter, ekspor CSV, dan hapus catatan kehadiran. Super Admin lihat semua wilayah; Admin Wilayah hanya wilayahnya.

## Aktor
- **Super Admin** — akses semua data attendance.
- **Admin Wilayah** — hanya `region_id` sendiri.
- **Sistem** — `Admin\AttendanceController::index`, `destroy`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/attendances atau /admin/attendances]
        A2[/Lihat daftar kehadiran dengan filter:<br/>tanggal, wilayah, titik proyek, status,<br/>cari nama/email/]
        A3[/Lihat ringkasan: hadir, terlambat, pakai toleransi/]
        A4{Aksi?}
        A5[Klik baris untuk lihat detail]
        A6[/Lihat foto selfie + lokasi lat lng + jarak/]
        A7[Klik Export CSV]
        A8[/Download file CSV filter aktif/]
        A9[Klik Hapus baris]
        A10[ConfirmDialog danger:<br/>Hapus catatan absensi nama + tanggal?]
        A11{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[AttendanceController::index<br/>AdminPresenter::attendancesFor scope]
        S2[Load regions untuk filter dropdown]
        S3[Generate CSV client-side dari data terfilter]
        S4[DELETE /attendances/id]
        S5{Region_id attendance = scope user?}
        S6[403 Forbidden]
        S7[Attendance::delete]
        S8[[Flash success: Absensi dihapus]]
    end

    A1 --> S1
    S1 --> S2
    S2 --> A2
    A2 --> A3
    A3 --> A4
    A4 -->|Detail| A5
    A5 --> A6
    A6 --> End((Selesai))
    A4 -->|Export| A7
    A7 --> S3
    S3 --> A8
    A8 --> End
    A4 -->|Hapus| A9
    A9 --> A10
    A10 --> A11
    A11 -->|Tidak| End
    A11 -->|Ya| S4
    S4 --> S5
    S5 -->|Tidak| S6
    S5 -->|Ya| S7
    S7 --> S8
    S8 --> End
    S6 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Data attendance dibatasi 500 baris terakhir untuk performa; filter dilakukan client-side.
- Export CSV pakai `Blob` + `URL.createObjectURL` di FE, tidak melalui server.
- Hanya Admin yang punya akses hapus. Kalau Admin Wilayah coba hapus baris di luar region → 403.
- Foto selfie ditampilkan dari `attendance.selfie_url` (fallback avatar default).
