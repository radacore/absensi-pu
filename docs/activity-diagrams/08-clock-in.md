# 08 — Clock-In (Absen Masuk)

Alur karyawan absen masuk. Wajib berada dalam radius titik penugasan.

## Aktor
- **Karyawan** — akun aktif dengan `site_id`.
- **Sistem** — `Karyawan\AttendanceController::clockIn` + `AdminPresenter::haversineM`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Buka /karyawan/absensi]
        K2{Punya site_id?}
        K3[Lihat pesan 'Titik belum di-assign']
        K4[Klik Buka kamera dan lokasi]
        K5[/Browser minta izin GPS/]
        K6{Izin diberikan?}
        K7[Pakai koordinat aktual]
        K8[Fallback demo: assigned.lat + 0.00035]
        K9[Preview jarak ke titik]
        K10{Dalam radius?}
        K11[/Lihat toast: X m di luar radius/]
        K12[Klik Kirim absen masuk]
    end

    subgraph Sistem[Sistem]
        S1[AttendanceController::index<br/>load assigned + settings + history + alreadyToday]
        S2[POST /karyawan/absensi/clock-in]
        S3[Validate lat, lng, selfie_url]
        S4{Punya site_id?}
        S5[422 site belum di-assign]
        S6{Sudah absen hari ini?}
        S7[422 sudah absen work_date]
        S8[Hitung haversineM lat,lng vs site.lat,site.lng]
        S9{Jarak <= radius_m?}
        S10[422 X m dari Y m di luar radius]
        S11[Cek jam sekarang Asia/Makassar]
        S12{Jam > jam_masuk + toleransi?}
        S13[status = late]
        S14[status = on_time]
        S15[Attendance::create dengan clock_in_at,<br/>lat_in, lng_in, distance_in_m, selfie_url]
        S16[[Flash success: Absen tercatat - Terlambat / Tepat waktu]]
    end

    K1 --> S1
    S1 --> K2
    K2 -->|Tidak| K3
    K3 --> End((Selesai))
    K2 -->|Ya| K4
    K4 --> K5
    K5 --> K6
    K6 -->|Ya| K7
    K6 -->|Tidak| K8
    K7 --> K9
    K8 --> K9
    K9 --> K10
    K10 -->|Tidak| K11
    K11 --> K4
    K10 -->|Ya| K12
    K12 --> S2
    S2 --> S3
    S3 --> S4
    S4 -->|Tidak| S5
    S4 -->|Ya| S6
    S6 -->|Ya| S7
    S6 -->|Tidak| S8
    S8 --> S9
    S9 -->|Tidak| S10
    S9 -->|Ya| S11
    S11 --> S12
    S12 -->|Ya| S13
    S12 -->|Tidak| S14
    S13 --> S15
    S14 --> S15
    S15 --> S16
    S16 --> End
    S5 --> End
    S7 --> End
    S10 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Haversine dihitung server-side di `AdminPresenter::haversineM` untuk konsistensi.
- Batas toleransi default 15 menit dari `AttendanceSetting::toleransi_late_menit`, dapat diubah oleh Super Admin.
- Kolom unik `[employee_id, work_date]` di migrasi mencegah double clock-in per hari.
- Selfie URL opsional; jika dikirim, disimpan sebagai path relatif.
