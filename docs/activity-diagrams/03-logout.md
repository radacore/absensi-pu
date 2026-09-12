# 03 — Logout

Alur keluar untuk semua peran (Admin, Super Admin, Karyawan). Rute `/super-admin/logout`, `/admin/logout`, `/wilayah/logout` diproses `AdminAuthController::destroy`, dan `/karyawan/logout` diproses `EmployeeAuthController::destroy`.

## Aktor
- **User** — siapa pun yang login.
- **Sistem** — Laravel Auth + kontroler auth masing-masing.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> U1

    subgraph User[User Admin atau Karyawan]
        U1[Klik tombol Keluar]
        U2[Konfirmasi ConfirmDialog<br/>Keluar dari akun?]
        U3{Yakin?}
        U4[Batal, tetap login]
        U5[Kirim POST /logout]
    end

    subgraph Sistem[Sistem]
        S1[Auth::guard active->logout]
        S2[session->invalidate]
        S3[session->regenerateToken]
        S4[Redirect ke halaman login area asal]
    end

    U1 --> U2
    U2 --> U3
    U3 -->|Tidak| U4
    U4 --> End1((Selesai))
    U3 -->|Ya| U5
    U5 --> S1
    S1 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> End2((Selesai di halaman login))

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class User actor
    class Sistem system
```

## Catatan implementasi
- Karyawan `Profil.jsx` dan Admin `AdminLayout.jsx` sama-sama pakai `useConfirm({ tone: 'danger' })` sebelum POST ke rute logout, konsisten dengan aturan konfirmasi untuk aksi destructive.
- Session invalidate mencegah reuse cookie lama, `regenerateToken` bikin CSRF token baru.
