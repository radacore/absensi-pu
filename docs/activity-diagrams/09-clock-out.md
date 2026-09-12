# 09 — Clock-Out (Absen Pulang)

Alur karyawan absen pulang. Harus sudah clock-in hari ini dan berada dalam radius titik penugasan. Kalau GPS belum siap, sistem otomatis buka kamera dan minta izin lokasi.

## Aktor
- **Karyawan** — sudah clock-in hari yang sama.
- **Sistem** — `Karyawan\AttendanceController::clockOut` + `AdminPresenter::haversineM`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Klik tombol<br/>Absen pulang"]:::user
    D1{"Sudah clock-in<br/>hari ini?"}:::dec
    E1["ERROR<br/>Belum absen masuk hari ini"]:::err

    D2{"GPS + kamera<br/>sudah aktif?"}:::dec
    A2["2. Auto buka kamera<br/>+ minta izin GPS"]:::user
    A3["3. Aktifkan GPS<br/>lalu klik lagi"]:::user

    D3{"Dalam radius?"}:::dec
    E2["ERROR<br/>X m di luar radius"]:::err

    A4["4. Kirim POST<br/>/karyawan/absensi/clock-out"]:::user
    S1["5. Validate lat, lng"]:::sys
    D4{"Ada attendance<br/>work_date hari ini?"}:::dec
    E3["ERROR 422<br/>Belum absen masuk"]:::err

    D5{"clock_out_at<br/>masih null?"}:::dec
    E4["ERROR 422<br/>Sudah absen pulang"]:::err

    S2["6. Hitung haversineM<br/>ke site"]:::sys
    D6{"Jarak <= radius_m?"}:::dec
    E5["ERROR 422<br/>X m di luar radius"]:::err

    S3["7. Attendance::update<br/>clock_out_at, lat_out, lng_out"]:::sys
    OK["SUKSES<br/>Absen pulang tercatat"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> D1
    D1 -->|tidak| E1
    D1 -->|ya| D2
    D2 -->|tidak| A2
    A2 --> A3
    A3 --> D2
    D2 -->|ya| D3
    D3 -->|tidak| E2
    D3 -->|ya| A4
    A4 --> S1
    S1 --> D4
    D4 -->|tidak| E3
    D4 -->|ya| D5
    D5 -->|tidak| E4
    D5 -->|ya| S2
    S2 --> D6
    D6 -->|tidak| E5
    D6 -->|ya| S3
    S3 --> OK
    OK --> END

    E2 -->|ulangi| A1
    E5 -->|ulangi| A1

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
| Biru muda | Aksi Aktor (Karyawan) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

1. Karyawan menekan tombol Absen pulang di halaman `/karyawan/absensi`.
2. Kalau GPS/kamera belum aktif, UI otomatis membuka kamera dan memancing izin GPS, lalu menampilkan toast "Aktifkan GPS lalu tekan Absen pulang lagi".
3. Karyawan aktifkan GPS di browser dan menekan tombol Absen pulang sekali lagi.
4. UI kirim POST `/karyawan/absensi/clock-out` dengan `lat` + `lng`.
5. `AttendanceController::clockOut` validasi input `lat` dan `lng`.
6. Sistem hitung jarak `haversineM` dari koordinat karyawan ke koordinat site — bandingkan dengan `site.radius_m`.
7. Kalau semua guard lolos, `Attendance` di-update dengan `clock_out_at`, `lat_out`, `lng_out`. Flash success "Absen pulang tercatat".

### Guard yang dicek server

- `D4` — harus ada baris `Attendance` untuk `work_date` hari ini (karyawan sudah clock-in).
- `D5` — `clock_out_at` harus masih `null` (idempoten: sekali clock-out per hari).
- `D6` — jarak <= radius; kalau di luar, 422 dengan jarak aktual.

## Catatan implementasi
- Tombol Absen pulang di UI Karyawan mengecek dulu state GPS/kamera. Kalau belum aktif, otomatis membuka kamera dan memancing izin GPS lalu meminta user menekan tombol lagi (UX ramah, hindari toast error mentah).
- Server tidak menghitung status `early_leave` otomatis; itu keputusan admin lewat filter waktu di UI absensi admin.
- Idempoten: request clock-out kedua di hari yang sama akan ditolak dengan 422.
