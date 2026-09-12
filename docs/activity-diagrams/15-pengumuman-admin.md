# 15 — Pengumuman Admin (CRUD)

Alur Admin mengelola pengumuman. Scope `Global` hanya bisa dibuat Super Admin; scope `Wilayah` bisa oleh Admin Wilayah tapi `region_id` dipaksa ke wilayahnya sendiri.

## Aktor
- **Super Admin** — CRUD scope Global dan Wilayah.
- **Admin Wilayah** — hanya scope Wilayah untuk region-nya sendiri.
- **Sistem** — `Admin\PengumumanController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /super-admin/pengumuman<br/>atau /admin/pengumuman"]:::user
    S1["2. Load daftar<br/>sesuai scope role"]:::sys
    A2["3. Pilih aksi<br/>Buat / Edit / Hapus"]:::user

    A3["4. Isi form:<br/>judul, konten, scope,<br/>region_id, pin"]:::user
    A4["5. Klik Terbitkan<br/>atau Simpan"]:::user

    S2["6. Validasi field<br/>judul, konten, scope, pin"]:::sys
    D1{"Admin Wilayah<br/>pilih scope Global?"}:::dec
    E1["ERROR<br/>Admin Wilayah<br/>tidak boleh Global"]:::err

    S3["7. Normalisasi region_id:<br/>Wilayah + admin_wilayah<br/>→ paksa user->region_id"]:::sys
    D2{"Scope Wilayah tapi<br/>region_id kosong?"}:::dec
    E2["ERROR<br/>Pilih wilayah"]:::err

    S4["8. Scope Global<br/>→ region_id = null"]:::sys

    D3{"Edit / Hapus<br/>pengumuman lama?"}:::dec
    S5["9. Cek ownership:<br/>Global + admin_wilayah?<br/>Region beda wilayah?"]:::sys
    E3["ERROR 403<br/>Tidak boleh edit<br/>di luar wilayah"]:::err

    S6["10. Announcement::create<br/>create_by = user->id"]:::sys
    S7["11. Announcement::update<br/>atau delete cascade"]:::sys

    OK["SUKSES<br/>Flash: Pengumuman<br/>dibuat/diperbarui/dihapus"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 --> A4
    A4 --> S2
    S2 --> D1
    D1 -->|ya| E1
    D1 -->|tidak| S3
    S3 --> D2
    D2 -->|ya| E2
    D2 -->|tidak| S4
    S4 --> D3
    D3 -->|create| S6
    D3 -->|update/delete| S5
    S5 --> E3
    S5 --> S7
    S6 --> OK
    S7 --> OK
    OK --> END

    E1 -->|ulangi| A3
    E2 -->|ulangi| A3
    E3 -->|kembali| A2

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

1. Admin membuka `/super-admin/pengumuman` (Super Admin) atau `/admin/pengumuman` (Admin Wilayah).
2. Sistem memuat daftar via `PengumumanController::index` + `AdminPresenter::announcementsFor($scope)`.
3. Admin memilih aksi: Buat baru, Edit item, atau Hapus item.
4. Untuk Buat/Edit, Admin mengisi form: judul, konten, scope (`Global` / `Wilayah`), `region_id` opsional, dan flag `pin`.
5. Admin klik Terbitkan (buat) atau Simpan (edit).
6. Server validasi payload: judul & konten wajib, scope harus `Global` atau `Wilayah`, `pin` boolean.
7. Server enforce role rule: `admin_wilayah` + scope `Global` → tolak. Scope `Wilayah` + `admin_wilayah` → `region_id` dipaksa ke `user->region_id`.
8. Scope `Wilayah` tapi `region_id` masih kosong → error "Pilih wilayah". Scope `Global` → `region_id` di-set `null`.
9. Untuk edit/hapus, cek ownership: pengumuman `Global` tidak boleh diubah `admin_wilayah`; pengumuman `Wilayah` dari region lain → 403.
10. Buat baru: `Announcement::create` dengan `created_by = user->id`.
11. Edit/Hapus: `Announcement::update` atau `Announcement::delete` (cascade ke `announcement_reads`).

## Catatan implementasi
- Controller: `app/Http/Controllers/Admin/PengumumanController.php` untuk store/update/destroy.
- Field `pin` menaikkan pengumuman ke urutan teratas (admin maupun karyawan).
- Admin Wilayah otomatis `region_id` di-paksa ke wilayahnya walau frontend mengirim region_id lain.
- Delete cascade menghapus baris di `announcement_reads` sehingga counter unread karyawan konsisten.
- ConfirmDialog danger dipakai untuk aksi Hapus di UI, tidak digambar sebagai node terpisah agar diagram tetap ringkas.
