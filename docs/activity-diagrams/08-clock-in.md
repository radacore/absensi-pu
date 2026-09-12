# 08 — Clock-In (Absen Masuk)

Alur karyawan absen masuk. Wajib berada dalam radius titik penugasan dan hanya boleh sekali per hari kerja.

## Aktor
- **Karyawan** — akun aktif dengan `site_id` yang sudah di-assign Admin.
- **Sistem** — `Karyawan\AttendanceController` (`index` + `clockIn`) dan helper `AdminPresenter::haversineM`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman<br/>/karyawan/absensi"]:::user
    S1["2. Load assigned + settings<br/>+ history + alreadyToday"]:::sys
    D1{"Punya site_id?"}:::dec
    E1["ERROR<br/>Titik belum di-assign"]:::err

    A2["3. Klik Buka kamera<br/>dan lokasi"]:::user
    A3["4. Browser minta izin GPS"]:::user
    D2{"Izin GPS diberikan?"}:::dec
    A4a["5a. Pakai koordinat aktual"]:::user
    A4b["5b. Fallback demo:<br/>assigned.lat + 0.00035"]:::user
    A5["6. Preview jarak ke titik"]:::user
    D3{"Dalam radius?"}:::dec
    E2["ERROR<br/>X m di luar radius"]:::err

    A6["7. Klik Kirim absen masuk"]:::user
    S2["8. Validate lat, lng,<br/>selfie_url"]:::sys
    D4{"Sudah absen<br/>hari ini?"}:::dec
    E3["ERROR<br/>Sudah absen work_date"]:::err

    S3["9. Hitung haversineM<br/>koordinat vs site"]:::sys
    D5{"Jarak <= radius_m?"}:::dec
    E4["ERROR<br/>X m dari Y m<br/>di luar radius"]:::err

    S4["10. Cek jam sekarang<br/>Asia/Makassar"]:::sys
    D6{"Jam > jam_masuk<br/>+ toleransi?"}:::dec
    S5a["11a. status = late"]:::sys
    S5b["11b. status = on_time"]:::sys

    S6["12. Attendance::create<br/>clock_in_at, lat_in, lng_in,<br/>distance_in_m, selfie_url"]:::sys
    OK["SUKSES<br/>Absen tercatat<br/>Terlambat / Tepat waktu"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> D1
    D1 -->|tidak| E1
    D1 -->|ya| A2
    A2 --> A3
    A3 --> D2
    D2 -->|ya| A4a
    D2 -->|tidak| A4b
    A4a --> A5
    A4b --> A5
    A5 --> D3
    D3 -->|tidak| E2
    D3 -->|ya| A6
    A6 --> S2
    S2 --> D4
    D4 -->|ya| E3
    D4 -->|tidak| S3
    S3 --> D5
    D5 -->|tidak| E4
    D5 -->|ya| S4
    S4 --> D6
    D6 -->|ya| S5a
    D6 -->|tidak| S5b
    S5a --> S6
    S5b --> S6
    S6 --> OK
    OK --> END

    E2 -->|ulangi| A2
    E4 -->|ulangi| A2

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

1. Karyawan membuka halaman `/karyawan/absensi`.
2. `AttendanceController::index` memuat data `assigned` (site penugasan), `settings` (jam kerja + toleransi), `history` absensi, dan flag `alreadyToday`.
3. Karyawan menekan tombol Buka kamera dan lokasi.
4. Browser meminta izin akses GPS.
5. Kalau izin diberikan (5a), sistem pakai koordinat aktual. Kalau ditolak (5b), fallback ke koordinat demo (`assigned.lat + 0.00035`) supaya alur tetap bisa diuji.
6. UI menampilkan preview jarak dari posisi karyawan ke titik penugasan.
7. Karyawan menekan tombol Kirim absen masuk → POST `/karyawan/absensi/clock-in`.
8. Sistem validasi input `lat`, `lng`, dan `selfie_url` (opsional).
9. Sistem hitung jarak dengan `AdminPresenter::haversineM` (server-side, konsisten).
10. Jarak dibandingkan dengan `site.radius_m`. Lebih besar → 422 dengan pesan jarak aktual.
11. Cek jam sekarang zona `Asia/Makassar` vs `jam_masuk + toleransi_late_menit`. Lebih lambat → `status = late` (11a), kalau tepat/awal → `status = on_time` (11b).
12. `Attendance::create` menyimpan `clock_in_at`, `lat_in`, `lng_in`, `distance_in_m`, dan `selfie_url`. Flash success ditampilkan.

## Catatan implementasi
- Haversine dihitung server-side di `AdminPresenter::haversineM` (`app/Support/AdminPresenter.php`) untuk konsistensi dengan clock-out.
- Batas toleransi default 15 menit dari `AttendanceSetting::toleransi_late_menit`, dapat diubah oleh Super Admin.
- Constraint unik `[employee_id, work_date]` di migrasi mencegah double clock-in per hari (backup di level DB selain guard aplikasi).
- Selfie URL opsional; jika dikirim, disimpan sebagai path relatif ke storage.
- Fallback koordinat demo hanya untuk kenyamanan uji manual; validasi radius tetap dieksekusi.
