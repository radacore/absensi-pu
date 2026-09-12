# 07 — Admin Wilayah CRUD

Alur Super Admin mengelola akun Admin Wilayah (role `admin_wilayah`). Tidak tersedia untuk Admin Wilayah sendiri.

## Aktor
- **Super Admin** — satu-satunya yang bisa CRUD akun admin wilayah.
- **Sistem** — `Admin\AdminWilayahController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph SuperAdmin[Super Admin]
        A1[Buka /super-admin/admin-wilayah]
        A2[/Lihat daftar admin wilayah/]
        A3{Aksi?}
        A4[Tambah admin wilayah]
        A5[/Isi nama, email, wilayah, password/]
        A6[Simpan]
        A7[Edit admin]
        A8[/Ubah field/]
        A9[Simpan]
        A10[Toggle aktif/nonaktif]
        A11{Nonaktifkan?}
        A12[ConfirmDialog warning:<br/>Nonaktifkan akun ini?]
        A13[Aktifkan langsung]
        A14[Hapus admin]
        A15[ConfirmDialog danger:<br/>Hapus akun admin?]
        A16{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[AdminWilayahController::index]
        S2{Role super_admin?}
        S3[403 Forbidden]
        S4[Validate email unik + wilayah exists + password min 8]
        S5[User::create role admin_wilayah, is_active true]
        S6[User::update]
        S7[User::update is_active toggle]
        S8[User::delete cascade session]
        S9[[Flash success]]
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
    A9 --> S2
    S2 -->|Ya edit| S6
    S6 --> S9
    A3 -->|Toggle| A10
    A10 --> A11
    A11 -->|Ya| A12
    A11 -->|Tidak| A13
    A12 --> S7
    A13 --> S7
    S7 --> S9
    A3 -->|Hapus| A14
    A14 --> A15
    A15 --> A16
    A16 -->|Tidak| End((Batal))
    A16 -->|Ya| S8
    S8 --> S9
    S9 --> End
    S3 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class SuperAdmin actor
    class Sistem system
```

## Catatan implementasi
- Menu "Admin Wilayah" di sidebar hanya muncul untuk Super Admin (via `superOnly: true` di `AdminLayout.jsx` menuDefs).
- Admin nonaktif tidak bisa login karena guard `web` cek `is_active = true` di `AdminAuthController::store`.
- Toggle aktif/nonaktif hanya butuh ConfirmDialog saat menonaktifkan (aksi destructive); aktivasi langsung.
