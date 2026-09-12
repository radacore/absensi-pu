# 04 — Regions CRUD (Kantor Wilayah)

Alur Super Admin mengelola 24 kantor wilayah. Admin Wilayah hanya bisa lihat dan edit wilayahnya sendiri (scoped ke `region_id`).

## Aktor
- **Super Admin** — akses penuh ke 24 wilayah (create, update, delete).
- **Admin Wilayah** — hanya bisa lihat dan update wilayah sendiri.
- **Sistem** — `Admin\RegionController` + `AdminPresenter::regionsFor($scope)`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman regions<br/>/super-admin/regions<br/>atau /admin/regions"]:::user
    S1["2. RegionController::index<br/>AdminPresenter::regionsFor(scope)"]:::sys
    A2["3. Lihat daftar wilayah<br/>+ jumlah titik"]:::user
    D1{"Pilih aksi?"}:::dec

    A3a["4a. Klik + Tambah Wilayah"]:::user
    A3b["4b. Klik Edit wilayah"]:::user
    A3c["4c. Klik Hapus wilayah"]:::user

    A4a["5a. Isi form:<br/>nama, tipe, kantor, alamat"]:::user
    A4b["5b. Ubah field wilayah"]:::user
    A4c["5c. ConfirmDialog danger<br/>Hapus wilayah + titik<br/>+ karyawan?"]:::user

    A5a["6a. Klik Simpan (create)"]:::user
    A5b["6b. Klik Simpan (update)"]:::user
    D2{"6c. User yakin?"}:::dec

    S2["7. Validasi input:<br/>nama unik, tipe valid"]:::sys
    D3{"8. Role & scope sesuai?"}:::dec

    S3["9a. Region::create()"]:::sys
    S4["9b. Region::update()"]:::sys
    S5["9c. Region::delete()<br/>cascade titik + karyawan"]:::sys

    E1["ERROR<br/>403 Forbidden<br/>scope mismatch"]:::err
    CANCEL["BATAL<br/>Tidak ada perubahan"]:::ok
    OK["SUKSES<br/>Flash: Wilayah<br/>dibuat/diperbarui/dihapus"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|tambah| A3a
    A3a --> A4a
    A4a --> A5a
    A5a --> S2

    D1 -->|edit| A3b
    A3b --> A4b
    A4b --> A5b
    A5b --> S2

    D1 -->|hapus| A3c
    A3c --> A4c
    A4c --> D2
    D2 -->|tidak| CANCEL
    D2 -->|ya| D3

    S2 --> D3
    D3 -->|tidak| E1
    D3 -->|ya create| S3
    D3 -->|ya update| S4
    D3 -->|ya delete| S5

    S3 --> OK
    S4 --> OK
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

1. Admin membuka halaman daftar wilayah.
2. `RegionController::index` memanggil `AdminPresenter::regionsFor($scope)` — scope null untuk Super Admin, `region_id` untuk Admin Wilayah.
3. Admin melihat daftar wilayah dengan ringkasan jumlah titik proyek per wilayah.
4. Admin memilih salah satu aksi:
   - **4a.** Tambah wilayah (khusus Super Admin).
   - **4b.** Edit wilayah.
   - **4c.** Hapus wilayah (khusus Super Admin).
5. Admin mengisi form atau melihat konfirmasi:
   - **5a.** Form create: nama, tipe (`kanwil`/`unit`), kantor pusat, alamat.
   - **5b.** Form edit dengan field yang sudah terisi.
   - **5c.** `ConfirmDialog` tone danger yang menjelaskan efek cascade.
6. Admin submit atau konfirmasi aksi.
7. Sistem validasi payload (nama unik, tipe valid).
8. Sistem cek otorisasi: create/delete butuh `role = super_admin`; update butuh `scope == user->region_id` atau Super Admin.
9. Sistem eksekusi CRUD sesuai aksi. Delete cascade otomatis menghapus titik dan karyawan di wilayah tersebut.

## Catatan implementasi
- `Admin/RegionController` (`app/Http/Controllers/Admin/RegionController.php`) memakai `$this->scopeRegion()` untuk membedakan akses Super Admin vs Admin Wilayah.
- Admin Wilayah tetap bisa GET `/admin/regions` untuk melihat wilayahnya via presenter yang di-scope.
- Cascade delete: menghapus wilayah otomatis menghapus semua titik (`sites`) dan karyawan (`employees`) di wilayah tersebut lewat foreign key `onDelete cascade`.
- Route store, update, dan delete hanya terdaftar di grup Super Admin.
