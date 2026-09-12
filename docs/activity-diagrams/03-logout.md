# 03 — Logout

Alur keluar untuk semua peran (Super Admin, Admin Wilayah, Karyawan). Ada ConfirmDialog tone danger sebelum request dikirim. Rute `/super-admin/logout`, `/admin/logout`, `/wilayah/logout` diproses `AdminAuthController::destroy`, dan `/karyawan/logout` diproses `EmployeeAuthController::destroy`.

## Aktor
- **User** — siapa pun yang sedang login (Admin atau Karyawan).
- **Sistem** — Laravel Auth + kontroler auth masing-masing area.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Klik tombol Keluar"]:::user
    S1["2. ConfirmDialog muncul<br/>Keluar dari akun?"]:::sys
    D1{"User yakin?"}:::dec

    A2["3a. Klik Batal<br/>tetap login"]:::user
    A3["3b. Klik Ya, keluar<br/>POST /logout"]:::user

    S2["4. Auth::guard(active)::logout()"]:::sys
    S3["5. session()::invalidate()<br/>session()::regenerateToken()"]:::sys
    S4["6. Redirect ke halaman<br/>login area asal"]:::sys

    CANCEL["BATAL<br/>Tetap di halaman"]:::ok
    OK["SUKSES<br/>Halaman login tampil"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> D1

    D1 -->|tidak| A2
    A2 --> CANCEL
    CANCEL --> END

    D1 -->|ya| A3
    A3 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> OK
    OK --> END

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
| Biru muda | Aksi Aktor (User) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil (sukses atau batal) |

## Langkah-Langkah Detail

1. User klik tombol Keluar di header atau menu profil.
2. Sistem menampilkan `ConfirmDialog` dengan tone danger dan pesan "Keluar dari akun?".
3. User memilih:
   - **3a.** Klik Batal → dialog ditutup, user tetap login.
   - **3b.** Klik Ya → form POST ke endpoint logout area terkait dikirim.
4. Controller memanggil `Auth::guard($active)->logout()` sesuai guard (`web` untuk admin, `employee` untuk karyawan).
5. Sistem invalidate session dan regenerate CSRF token.
6. Sistem redirect user ke halaman login area asal (`/super-admin/login`, `/admin/login`, atau `/karyawan/login`).

## Catatan implementasi
- Karyawan `Profil.jsx` dan Admin `AdminLayout.jsx` sama-sama pakai `useConfirm({ tone: 'danger' })` sebelum POST ke rute logout — konsisten dengan aturan konfirmasi untuk aksi destructive.
- `session()->invalidate()` mencegah reuse cookie lama, `regenerateToken()` bikin CSRF token baru sebagai mitigasi session fixation.
- Guard aktif dideteksi otomatis dari controller: `AdminAuthController::destroy` untuk web, `EmployeeAuthController::destroy` untuk employee.
