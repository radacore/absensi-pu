# 09 — Clock-Out (Absen Pulang)

Alur karyawan absen pulang. Harus sudah clock-in hari ini dan berada dalam radius.

## Aktor
- **Karyawan** — sudah clock-in hari ini.
- **Sistem** — `Karyawan\AttendanceController::clockOut`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Klik tombol Absen pulang]
        K2{Sudah clock-in hari ini?}
        K3[/Toast: Belum absen masuk hari ini/]
        K4{GPS + kamera sudah aktif?}
        K5[Auto-buka kamera + minta GPS<br/>tampil toast 'Aktifkan GPS lalu tekan Absen pulang lagi']
        K6[/User aktifkan GPS lalu klik lagi/]
        K7{Dalam radius?}
        K8[/Toast: X m di luar radius/]
        K9[Kirim POST /karyawan/absensi/clock-out]
    end

    subgraph Sistem[Sistem]
        S1[Validate lat, lng]
        S2{Ada attendance work_date hari ini?}
        S3[422 belum absen masuk]
        S4{clock_out_at masih null?}
        S5[422 sudah absen pulang]
        S6[Hitung haversineM ke site]
        S7{Jarak <= radius_m?}
        S8[422 X m di luar radius]
        S9[Attendance::update<br/>clock_out_at, lat_out, lng_out]
        S10[[Flash success: Absen pulang tercatat]]
    end

    K1 --> K2
    K2 -->|Tidak| K3
    K3 --> End((Selesai))
    K2 -->|Ya| K4
    K4 -->|Tidak| K5
    K5 --> K6
    K6 --> K4
    K4 -->|Ya| K7
    K7 -->|Tidak| K8
    K8 --> End
    K7 -->|Ya| K9
    K9 --> S1
    S1 --> S2
    S2 -->|Tidak| S3
    S2 -->|Ya| S4
    S4 -->|Tidak| S5
    S4 -->|Ya| S6
    S6 --> S7
    S7 -->|Tidak| S8
    S7 -->|Ya| S9
    S9 --> S10
    S10 --> End
    S3 --> End
    S5 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Tombol "Absen pulang" di UI Karyawan mengecek dulu apakah GPS sudah aktif. Kalau belum, otomatis membuka kamera + memancing izin GPS lalu meminta user menekan tombol lagi (UX ramah).
- Server tidak menghitung status "early_leave" otomatis; itu keputusan admin lewat filter waktu di UI absensi.
- Idempoten: kedua kali clock-out di hari yang sama akan ditolak 422.
