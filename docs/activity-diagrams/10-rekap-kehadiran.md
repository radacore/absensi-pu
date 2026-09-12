# 10 — Rekap Kehadiran Karyawan

Halaman `/karyawan/rekap` menampilkan kalender bulan berjalan dan ringkasan hadir/terlambat/cuti/toleransi.

## Aktor
- **Karyawan** — melihat rekap sendiri.
- **Sistem** — `Karyawan\AttendanceController::rekap`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Klik menu Rekap dari dashboard]
        K2[/Lihat kalender bulan + ringkasan/]
        K3[Klik tanggal untuk detail]
        K4[/Lihat status: hadir, terlambat, cuti, libur/]
    end

    subgraph Sistem[Sistem]
        S1[AttendanceController::rekap]
        S2[Ambil AttendanceSetting jamMasuk, jamPulang, toleransi, loveMax]
        S3[Query Attendance bulan berjalan<br/>where employee_id + year + month]
        S4[Query Leave status Disetujui<br/>yang overlap bulan berjalan]
        S5[Bentuk cutiDates array<br/>expand mulai..selesai per hari]
        S6[Hitung loveQuota via AdminPresenter::loveQuota]
        S7[Kirim ke view: monthRows, cutiDates, cutiDays,<br/>hadir, terlambat, settings.loveQuota]
        S8[Render kalender:<br/>weekend=libur, cuti=teal, late=amber, on_time=gold]
    end

    K1 --> S1
    S1 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> S5
    S5 --> S6
    S6 --> S7
    S7 --> S8
    S8 --> K2
    K2 --> K3
    K3 --> K4
    K4 --> End((Selesai))

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Cuti dihitung dengan expand rentang `mulai..selesai` yang berpotongan dengan bulan berjalan.
- Chip "Toleransi X/4" di header memakai sisa kuota real dari `AdminPresenter::loveQuota` (bukan hardcode).
- Tombol "Unduh PDF" sudah dihilangkan sampai backend PDF diimplementasikan.
