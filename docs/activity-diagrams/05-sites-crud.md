# 05 — Sites CRUD (Titik Proyek)

Alur kelola titik proyek per wilayah. Setiap titik punya koordinat lat/lng dan `radius_m` untuk verifikasi absensi.

## Aktor
- **Super Admin** — kelola titik semua wilayah.
- **Admin Wilayah** — kelola titik di wilayahnya saja.
- **Sistem** — `Admin\SiteController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka detail wilayah<br/>/regions/id/sites/id]
        A2{Aksi?}
        A3[Tambah titik]
        A4[/Isi nama, lat, lng, radius, alamat<br/>lewat peta Leaflet/]
        A5[Simpan]
        A6[Ubah titik]
        A7[/Ubah field titik/]
        A8[Simpan]
        A9[Assign karyawan ke titik]
        A10[/Pilih karyawan dari wilayah/]
        A11[Konfirmasi]
        A12[Pindah anggota]
        A13[/Pilih titik tujuan/]
        A14[Hapus titik]
        A15[ConfirmDialog danger:<br/>Hapus titik + pindah anggota?]
        A16{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[SiteController::store<br/>validasi lat/lng + radius 50..1000m]
        S2[Site::create region_id auto]
        S3{Scope match region user?}
        S4[403 Forbidden]
        S5[Site::update]
        S6[Employee::update site_id]
        S7{Titik masih punya anggota?}
        S8[Pindahkan otomatis ke titik lain dalam wilayah]
        S9{Wilayah punya minimal 2 titik?}
        S10[Error: Wilayah harus punya minimal 1 titik]
        S11[Site::delete]
        S12[[Flash success sesuai aksi]]
    end

    A1 --> A2
    A2 -->|Tambah| A3
    A3 --> A4
    A4 --> A5
    A5 --> S3
    S3 -->|Tidak| S4
    S3 -->|Ya| S1
    S1 --> S2
    S2 --> S12
    A2 -->|Ubah| A6
    A6 --> A7
    A7 --> A8
    A8 --> S3
    S3 -->|Ya| S5
    S5 --> S12
    A2 -->|Assign| A9
    A9 --> A10
    A10 --> A11
    A11 --> S6
    S6 --> S12
    A2 -->|Pindah| A12
    A12 --> A13
    A13 --> S6
    A2 -->|Hapus| A14
    A14 --> A15
    A15 --> A16
    A16 -->|Tidak| End((Batal))
    A16 -->|Ya| S9
    S9 -->|Tidak| S10
    S9 -->|Ya| S7
    S7 -->|Ya| S8
    S8 --> S11
    S7 -->|Tidak| S11
    S11 --> S12
    S12 --> End
    S4 --> End
    S10 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- `SiteController::destroy` menjaga invariant "1 wilayah minimal 1 titik" dan otomatis memindahkan anggota titik terhapus ke titik lain dalam wilayah yang sama.
- `1 karyawan = 1 titik` — karyawan tidak boleh tanpa titik, pindah dilakukan via `SiteController::move` atau `assignEmployees`.
- Radius validasi 50–1000 meter agar realistis untuk area kantor/bendungan.
