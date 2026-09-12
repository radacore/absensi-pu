# 05 — Sites CRUD (Titik Proyek)

Alur kelola titik proyek per wilayah. Setiap titik punya koordinat lat/lng dan `radius_m` (50–1000m) untuk verifikasi absensi. Sistem menjaga invariant: **1 wilayah minimal 1 titik**, dan **1 karyawan = 1 titik**.

## Aktor
- **Super Admin** — kelola titik semua wilayah.
- **Admin Wilayah** — kelola titik di wilayahnya saja.
- **Sistem** — `Admin\SiteController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka detail wilayah<br/>/regions/{id}/sites"]:::user
    D1{"Pilih aksi?"}:::dec

    A2a["2a. Tambah titik"]:::user
    A2b["2b. Ubah titik"]:::user
    A2c["2c. Assign karyawan"]:::user
    A2d["2d. Pindah anggota"]:::user
    A2e["2e. Hapus titik"]:::user

    A3a["3a. Isi nama, lat, lng,<br/>radius, alamat<br/>via peta Leaflet"]:::user
    A3b["3b. Ubah field titik"]:::user
    A3c["3c. Pilih karyawan<br/>dari wilayah"]:::user
    A3d["3d. Pilih titik tujuan<br/>untuk anggota"]:::user
    A3e["3e. ConfirmDialog danger<br/>Hapus titik +<br/>pindah anggota?"]:::user

    A4["4. Submit form"]:::user
    D2{"5. Yakin hapus?"}:::dec

    S1["6. Validasi input<br/>lat/lng, radius 50..1000m"]:::sys
    D3{"7. Scope match<br/>region user?"}:::dec

    S2["8a. Site::create()"]:::sys
    S3["8b. Site::update()"]:::sys
    S4["8c. Employee::update site_id<br/>(assign / pindah)"]:::sys

    D4{"9. Wilayah punya<br/>minimal 2 titik?"}:::dec
    D5{"10. Titik masih<br/>punya anggota?"}:::dec
    S5["11. Pindahkan anggota<br/>ke titik lain di wilayah"]:::sys
    S6["12. Site::delete()"]:::sys

    E1["ERROR<br/>403 Forbidden"]:::err
    E2["ERROR<br/>Wilayah harus punya<br/>minimal 1 titik"]:::err
    CANCEL["BATAL<br/>Tidak ada perubahan"]:::ok
    OK["SUKSES<br/>Flash sesuai aksi"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> D1

    D1 -->|tambah| A2a
    A2a --> A3a
    A3a --> A4
    A4 --> D3

    D1 -->|ubah| A2b
    A2b --> A3b
    A3b --> A4

    D1 -->|assign| A2c
    A2c --> A3c
    A3c --> A4

    D1 -->|pindah| A2d
    A2d --> A3d
    A3d --> A4

    D1 -->|hapus| A2e
    A2e --> A3e
    A3e --> D2
    D2 -->|tidak| CANCEL
    D2 -->|ya| D3

    D3 -->|tidak| E1
    D3 -->|ya create| S1
    D3 -->|ya update| S3
    D3 -->|ya assign/pindah| S4
    D3 -->|ya delete| D4

    S1 --> S2
    S2 --> OK
    S3 --> OK
    S4 --> OK

    D4 -->|tidak| E2
    D4 -->|ya| D5
    D5 -->|ya| S5
    D5 -->|tidak| S6
    S5 --> S6
    S6 --> OK

    OK --> END
    CANCEL --> END
    E1 --> END
    E2 --> END

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

1. Admin membuka halaman detail wilayah `/super-admin/regions/{id}/sites` atau `/admin/regions/{id}/sites`.
2. Admin memilih salah satu aksi:
   - **2a.** Tambah titik baru.
   - **2b.** Ubah titik existing.
   - **2c.** Assign karyawan ke titik.
   - **2d.** Pindah anggota ke titik lain di wilayah yang sama.
   - **2e.** Hapus titik.
3. Admin mengisi form sesuai aksi (koordinat via peta Leaflet untuk create/update titik).
4. Admin submit form.
5. Khusus aksi hapus: `ConfirmDialog` tone danger meminta konfirmasi karena akan memicu pemindahan anggota.
6. Sistem validasi payload — koordinat valid, `radius_m` antara 50–1000 meter.
7. Sistem cek scope wilayah: Admin Wilayah wajib `region_id == user->region_id`.
8. Sistem eksekusi aksi CRUD:
   - **8a.** `Site::create()` dengan `region_id` diisi otomatis dari route.
   - **8b.** `Site::update()`.
   - **8c.** `Employee::update(site_id)` untuk assign/pindah.
9. Khusus delete: cek invariant — wilayah harus tetap punya minimal 1 titik setelah delete (butuh minimal 2 titik sebelum delete).
10. Cek apakah titik yang akan dihapus masih punya anggota.
11. Kalau ada anggota, sistem otomatis memindahkan mereka ke titik lain dalam wilayah yang sama.
12. `Site::delete()` dieksekusi.

## Catatan implementasi
- `SiteController::destroy` (`app/Http/Controllers/Admin/SiteController.php`) menjaga invariant "1 wilayah minimal 1 titik" dan otomatis memindahkan anggota titik terhapus ke titik lain dalam wilayah.
- Aturan **1 karyawan = 1 titik** — karyawan tidak boleh tanpa titik. Pemindahan dilakukan via `SiteController::move` atau `SiteController::assignEmployees`.
- Radius 50–1000 meter dipilih agar realistis untuk area kantor/bendungan.
- Peta Leaflet dipakai untuk pilih koordinat, disimpan sebagai `latitude` + `longitude` decimal.
