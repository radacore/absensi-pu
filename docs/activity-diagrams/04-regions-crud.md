# 04 — Regions CRUD (Kantor Wilayah)

Alur Super Admin mengelola 24 kantor wilayah. Admin Wilayah hanya bisa lihat dan edit wilayahnya sendiri.

## Aktor
- **Super Admin** — akses penuh ke 24 wilayah.
- **Admin Wilayah** — hanya bisa update wilayah sendiri (`region_id == user->region_id`).
- **Sistem** — `Admin\RegionController` + `AdminPresenter::regionsFor($scope)`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/regions atau /admin/regions]
        A2[/Lihat daftar wilayah + titik/]
        A3{Aksi apa?}
        A4[Klik + Tambah Wilayah]
        A5[/Isi nama, tipe, kantor, alamat/]
        A6[Klik Simpan]
        A7[Klik Edit wilayah]
        A8[/Ubah field/]
        A9[Klik Simpan]
        A10[Klik Hapus wilayah]
        A11[ConfirmDialog tone danger:<br/>Hapus wilayah + titik + karyawan?]
        A12{Yakin?}
        A13[Batal]
        A14[Konfirmasi hapus]
    end

    subgraph Sistem[Sistem]
        S1[RegionController::index<br/>AdminPresenter::regionsFor scope]
        S2{Role super_admin?}
        S3[403 Forbidden]
        S4[Validasi nama unik + tipe]
        S5[Region::create]
        S6[Region::update]
        S7{Scope match user->region_id?}
        S8[Region::delete cascade titik + karyawan]
        S9[[Flash success: Wilayah dibuat/diperbarui/dihapus]]
    end

    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 -->|Tambah| A4
    A4 --> A5
    A5 --> A6
    A6 --> S2
    S2 -->|Tidak| S3
    S2 -->|Ya| S4
    S4 --> S5
    S5 --> S9
    A3 -->|Edit| A7
    A7 --> A8
    A8 --> A9
    A9 --> S7
    S7 -->|Tidak| S3
    S7 -->|Ya| S6
    S6 --> S9
    A3 -->|Hapus| A10
    A10 --> A11
    A11 --> A12
    A12 -->|Tidak| A13
    A12 -->|Ya| A14
    A14 --> S2
    S2 -->|Ya lanjut hapus| S8
    S8 --> S9
    S9 --> End((Selesai))
    S3 --> End
    A13 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    classDef danger fill:#FEF2F2,stroke:#EF4444,stroke-width:1px,color:#991B1B
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Store, update, delete route hanya diberikan ke Super Admin di `Admin/RegionController` via cek `$this->scopeRegion()`.
- Admin Wilayah tetap bisa GET `/admin/regions` untuk lihat wilayahnya via presenter yang di-scope.
- Cascade delete: menghapus wilayah otomatis menghapus semua titik dan karyawan di wilayah tersebut (foreign key `onDelete cascade`).
