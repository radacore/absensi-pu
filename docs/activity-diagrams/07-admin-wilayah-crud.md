# 07 — Admin Wilayah CRUD

Alur Super Admin mengelola akun Admin Wilayah (role `admin_wilayah`). Fitur ini tidak tersedia untuk Admin Wilayah sendiri. Toggle aktif/nonaktif dipakai untuk enable/disable login tanpa hapus data.

## Aktor
- **Super Admin** — satu-satunya yang bisa CRUD akun admin wilayah.
- **Sistem** — `Admin\AdminWilayahController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman<br/>/super-admin/admin-wilayah"]:::user
    S1["2. AdminWilayahController::index"]:::sys
    A2["3. Lihat daftar<br/>admin wilayah"]:::user
    D1{"Pilih aksi?"}:::dec

    A3a["4a. Klik + Tambah"]:::user
    A3b["4b. Klik Edit"]:::user
    A3c["4c. Klik Toggle<br/>aktif/nonaktif"]:::user
    A3d["4d. Klik Hapus"]:::user

    A4a["5a. Isi form:<br/>nama, email, wilayah,<br/>password min 8 char"]:::user
    A4b["5b. Ubah field akun"]:::user
    D2{"5c. Nonaktifkan?"}:::dec
    A4c1["6c. ConfirmDialog warning<br/>Nonaktifkan akun ini?"]:::user
    A4c2["6c. Aktifkan langsung<br/>tanpa konfirmasi"]:::user
    A4d["5d. ConfirmDialog danger<br/>Hapus akun admin?"]:::user

    A5["7. Submit / konfirmasi"]:::user
    D3{"8. Yakin?"}:::dec

    D4{"9. Role user =<br/>super_admin?"}:::dec
    S2["10. Validasi:<br/>email unik, wilayah exists,<br/>password min 8"]:::sys

    S3["11a. User::create()<br/>role admin_wilayah,<br/>is_active = true"]:::sys
    S4["11b. User::update()"]:::sys
    S5["11c. User::update<br/>is_active toggle"]:::sys
    S6["11d. User::delete()<br/>cascade session"]:::sys

    E1["ERROR<br/>403 Forbidden"]:::err
    CANCEL["BATAL<br/>Tidak ada perubahan"]:::ok
    OK["SUKSES<br/>Flash sesuai aksi"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|tambah| A3a
    A3a --> A4a
    A4a --> A5
    A5 --> D4

    D1 -->|edit| A3b
    A3b --> A4b
    A4b --> A5

    D1 -->|toggle| A3c
    A3c --> D2
    D2 -->|ya nonaktifkan| A4c1
    D2 -->|tidak, aktifkan| A4c2
    A4c1 --> D3
    A4c2 --> D4

    D1 -->|hapus| A3d
    A3d --> A4d
    A4d --> D3

    D3 -->|tidak| CANCEL
    D3 -->|ya| D4

    D4 -->|tidak| E1
    D4 -->|ya create| S2
    D4 -->|ya update| S4
    D4 -->|ya toggle| S5
    D4 -->|ya delete| S6

    S2 --> S3
    S3 --> OK
    S4 --> OK
    S5 --> OK
    S6 --> OK

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
| Biru muda | Aksi Aktor (Super Admin) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses / batal |

## Langkah-Langkah Detail

1. Super Admin membuka halaman `/super-admin/admin-wilayah`.
2. `AdminWilayahController::index` mengambil daftar user dengan role `admin_wilayah`.
3. Super Admin melihat daftar admin wilayah beserta status aktif dan wilayah yang dikelola.
4. Super Admin memilih salah satu aksi:
   - **4a.** Tambah admin wilayah baru.
   - **4b.** Edit data admin wilayah.
   - **4c.** Toggle status aktif/nonaktif.
   - **4d.** Hapus akun admin wilayah.
5. Super Admin mengisi form atau melihat konfirmasi:
   - **5a.** Form create: nama, email, wilayah, password (min 8 karakter).
   - **5b.** Form edit dengan field terisi.
   - **5c.** Cek arah toggle: menonaktifkan atau mengaktifkan.
   - **5d.** `ConfirmDialog` tone danger untuk hapus.
6. Untuk toggle: aksi menonaktifkan butuh `ConfirmDialog` warning; mengaktifkan langsung tanpa konfirmasi.
7. Super Admin submit atau klik Ya pada dialog.
8. Sistem cek konfirmasi user.
9. Sistem verifikasi role — semua aksi di controller ini butuh `role = super_admin`.
10. Sistem validasi payload — email unik di tabel `users`, `region_id` valid, password minimal 8 karakter (untuk create).
11. Sistem eksekusi aksi:
    - **11a.** `User::create()` dengan role `admin_wilayah` dan `is_active = true`.
    - **11b.** `User::update()`.
    - **11c.** `User::update(is_active)` toggle.
    - **11d.** `User::delete()` dengan cascade session (user logout otomatis kalau sedang login).

## Catatan implementasi
- Menu "Admin Wilayah" di sidebar hanya muncul untuk Super Admin (via flag `superOnly: true` di `AdminLayout.jsx` `menuDefs`).
- Admin nonaktif tidak bisa login karena guard `web` cek `is_active = true` di `AdminAuthController::store`.
- Toggle aktif/nonaktif hanya butuh `ConfirmDialog` saat menonaktifkan (aksi destructive terhadap akses); aktivasi langsung tanpa konfirmasi.
- Password disimpan bcrypt via cast `'password' => 'hashed'` di model `User`.
