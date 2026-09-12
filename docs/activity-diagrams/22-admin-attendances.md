# 22 — Admin Attendances (Kelola Kehadiran)

Halaman `/admin/attendances` untuk Admin lihat, filter, ekspor CSV, dan hapus catatan kehadiran. Super Admin lihat semua wilayah; Admin Wilayah hanya wilayahnya.

## Aktor
- **Super Admin** — akses semua data attendance.
- **Admin Wilayah** — hanya `region_id` sendiri.
- **Sistem** — `Admin\AttendanceController::index` + `destroy`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman<br/>/super-admin/attendances<br/>atau /admin/attendances"]:::user
    S1["2. AttendanceController::index<br/>attendancesFor(scope), max 500 baris"]:::sys
    S2["3. Load regions untuk<br/>dropdown filter"]:::sys
    A2["4. Terapkan filter:<br/>tanggal, wilayah, titik,<br/>status, cari nama/email"]:::user
    A3["5. Lihat ringkasan:<br/>hadir, terlambat,<br/>pakai toleransi"]:::user
    D1{"Pilih aksi?"}:::dec

    A4a["6a. Klik baris untuk detail"]:::user
    A5a["7a. Lihat foto selfie +<br/>lokasi lat/lng + jarak"]:::user

    A4b["6b. Klik Export CSV"]:::user
    S3["7b. Generate CSV client-side<br/>via Blob + URL.createObjectURL"]:::sys
    A5b["8b. Download file CSV<br/>filter aktif"]:::user

    A4c["6c. Klik Hapus baris"]:::user
    A5c["7c. ConfirmDialog danger<br/>Hapus catatan absensi<br/>{nama} + {tanggal}?"]:::user
    D2{"8c. Yakin?"}:::dec
    S4["9c. DELETE /attendances/{id}"]:::sys
    D3{"10c. region_id attendance<br/>= scope user?"}:::dec
    S5["11c. Attendance::delete()"]:::sys

    E1["ERROR<br/>403 Forbidden"]:::err
    CANCEL["BATAL<br/>Tidak dihapus"]:::ok
    OK["SUKSES<br/>Flash: Absensi dihapus /<br/>File CSV terunduh"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> S2
    S2 --> A2
    A2 --> A3
    A3 --> D1

    D1 -->|detail| A4a
    A4a --> A5a
    A5a --> END

    D1 -->|export| A4b
    A4b --> S3
    S3 --> A5b
    A5b --> OK

    D1 -->|hapus| A4c
    A4c --> A5c
    A5c --> D2
    D2 -->|tidak| CANCEL
    D2 -->|ya| S4
    S4 --> D3
    D3 -->|tidak| E1
    D3 -->|ya| S5
    S5 --> OK

    OK --> END
    CANCEL --> END
    E1 --> END

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
| Biru muda | Aksi Aktor (Admin) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses / batal |

## Langkah-Langkah Detail

1. Admin membuka halaman daftar attendance.
2. `AttendanceController::index` memanggil `AdminPresenter::attendancesFor($scope)` — max 500 baris terakhir.
3. Controller juga load daftar wilayah untuk dropdown filter.
4. Admin bisa memfilter data berdasarkan tanggal, wilayah, titik proyek, status (hadir/terlambat/pakai toleransi), atau cari nama/email.
5. Admin melihat ringkasan angka di header: total hadir, total terlambat, total pakai toleransi.
6. Admin memilih salah satu aksi:
   - **6a.** Klik baris untuk lihat detail.
   - **6b.** Export data ke CSV.
   - **6c.** Hapus catatan absensi tertentu.
7. Sistem/user menjalankan langkah berikutnya sesuai aksi:
   - **7a.** Panel detail muncul dengan foto selfie, koordinat lat/lng, dan jarak ke titik proyek.
   - **7b.** FE generate CSV dari data terfilter menggunakan `Blob` + `URL.createObjectURL`.
   - **7c.** `ConfirmDialog` tone danger meminta konfirmasi dengan nama karyawan + tanggal absensi.
8. Untuk hapus: user konfirmasi lewat dialog.
9. Kalau konfirmasi, browser kirim `DELETE /attendances/{id}`.
10. Controller cek scope: `region_id` attendance harus sesuai scope user.
11. Kalau lolos, `Attendance::delete()` dieksekusi dan flash success ditampilkan.

## Catatan implementasi
- Data attendance dibatasi 500 baris terakhir untuk performa; filter dilakukan client-side.
- Export CSV pakai `Blob` + `URL.createObjectURL` di FE, tidak melalui server.
- Hanya Admin yang punya akses hapus. Kalau Admin Wilayah coba hapus baris di luar `region_id`-nya → 403.
- Foto selfie ditampilkan dari `attendance.selfie_url` (fallback avatar default).
- Filter dropdown wilayah untuk Admin Wilayah hanya menampilkan wilayahnya sendiri (auto-select).
