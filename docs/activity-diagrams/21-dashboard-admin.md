# 21 — Dashboard Admin

Ringkasan wilayah dan aktivitas terbaru. Menampilkan statistik hadir hari ini, cuti/toleransi pending, dan feed aktivitas (attendance + cuti + toleransi terbaru).

## Aktor
- **Super Admin** — lihat semua wilayah.
- **Admin Wilayah** — lihat wilayahnya saja.
- **Sistem** — `Admin\DashboardController::index` + `AdminPresenter`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Login lalu redirect ke /super-admin atau /admin]
        A2[/Lihat 4 kartu ringkasan:<br/>Total karyawan, Hadir hari ini,<br/>Cuti pending, Toleransi pending/]
        A3[/Lihat breakdown per titik dalam wilayah/]
        A4[/Lihat feed 10 aktivitas terbaru<br/>attendance + cuti + toleransi/]
        A5[Klik kartu untuk drilldown]
    end

    subgraph Sistem[Sistem]
        S1[DashboardController::index]
        S2[Tentukan scope = null jika super_admin, else region_id user]
        S3[AdminPresenter::regionsFor scope]
        S4[AdminPresenter::employeesFor scope]
        S5[AdminPresenter::attendancesFor scope]
        S6[AdminPresenter::settingsFor]
        S7[AdminPresenter::leavesFor scope]
        S8[AdminPresenter::lovesFor scope]
        S9[activities: gabungan 10 aktivitas terbaru<br/>attendance today + cuti + toleransi<br/>urut ts desc]
        S10[Render props ke Admin/Dashboard.jsx]
    end

    A1 --> S1
    S1 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> S5
    S5 --> S6
    S6 --> S7
    S7 --> S8
    S8 --> S9
    S9 --> S10
    S10 --> A2
    A2 --> A3
    A3 --> A4
    A4 --> A5
    A5 --> End((Navigate ke halaman detail))

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Aktivitas terbaru dihitung real-time dari model `Attendance`, `Leave`, `ToleranceClaim`, bukan dari daftar hardcode. Setiap aktivitas punya field `t` (label), `tag` (kategori: on_time/late/cuti/love), `color` (mapping warna badge), `ts` (timestamp untuk sorting).
- Scope wilayah otomatis diterapkan lewat `AdminPresenter::*For(scope)` sehingga Admin Wilayah tidak pernah melihat data wilayah lain.
- Empty state ditampilkan bila belum ada aktivitas hari ini.
