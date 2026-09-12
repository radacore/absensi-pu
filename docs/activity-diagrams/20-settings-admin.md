# 20 — Settings Admin

Pengaturan absensi global (jam kerja, toleransi, kuota love). Hanya Super Admin yang boleh menulis; Admin Wilayah hanya read-only dengan badge peringatan.

## Aktor
- **Super Admin** — full akses read + write.
- **Admin Wilayah** — hanya read-only view (badge di sidebar).
- **Sistem** — `Admin\SettingController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /super-admin/settings<br/>atau /admin/settings"]:::user
    S1["2. Load AttendanceSetting<br/>+ readOnly flag"]:::sys
    D1{"Role<br/>super_admin?"}:::dec
    RO["3a. Read-only view<br/>+ badge peringatan"]:::sys

    A2["3b. Ubah jam_masuk, jam_pulang,<br/>toleransi 0..60,<br/>love_max 1..10"]:::user
    A3["4. Klik Simpan<br/>pengaturan"]:::user

    S2["5. PUT /super-admin/<br/>settings"]:::sys
    D2{"Role<br/>super_admin?"}:::dec
    E1["ERROR 403<br/>Forbidden"]:::err

    S3["6. Validate:<br/>jamMasuk HH:MM,<br/>jamPulang after jamMasuk,<br/>toleransi 0..60, loveMax 1..10"]:::sys
    D3{"Valid?"}:::dec
    E2["ERROR<br/>Validasi gagal"]:::err

    S4["7. AttendanceSetting::update<br/>singleton id=1"]:::sys
    OK["SUKSES<br/>Flash: Pengaturan<br/>disimpan"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> D1
    D1 -->|tidak| RO
    RO --> END
    D1 -->|ya| A2
    A2 --> A3
    A3 --> S2
    S2 --> D2
    D2 -->|tidak| E1
    D2 -->|ya| S3
    S3 --> D3
    D3 -->|tidak| E2
    D3 -->|ya| S4
    S4 --> OK
    OK --> END

    E1 -->|kembali| A1
    E2 -->|ulangi| A2

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
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

1. Admin membuka `/super-admin/settings` (Super Admin) atau `/admin/settings` (Admin Wilayah).
2. `SettingController::index` memuat singleton `AttendanceSetting` beserta flag `readOnly` untuk FE.
3. Cek role: (a) bukan `super_admin` → tampilkan view read-only + badge peringatan, alur berhenti. (b) `super_admin` → form editable dengan field jam kerja, toleransi, `love_max`, dsb.
4. Super Admin klik tombol Simpan pengaturan.
5. FE PUT ke `/super-admin/settings`.
6. Server re-check role. Admin Wilayah yang bypass FE → 403 Forbidden.
7. Validate payload: `jamMasuk` format `HH:MM`, `jamPulang` `HH:MM` dan setelah `jamMasuk`, `toleransi` 0..60 menit, `loveMax` 1..10. Kalau invalid → kembalikan errors.
8. `AttendanceSetting::update(...)` menimpa singleton (single row `id=1`).

## Catatan implementasi
- Controller: `app/Http/Controllers/Admin/SettingController.php`.
- `AttendanceSetting` adalah singleton (single row, `id=1`). Update selalu mengubah baris ini.
- Perubahan `love_max` berlaku bulan berikutnya (reset di tanggal 1, 00:00 WITA). Sisa bulan berjalan tetap pakai kuota lama.
- Field `hari_kerja` dan `timezone` tidak diubah dari UI saat ini (default Sen–Jum, Asia/Makassar).
- Badge read-only untuk Admin Wilayah ditentukan lewat `readOnly = user->role !== 'super_admin'` yang di-share ke FE.
