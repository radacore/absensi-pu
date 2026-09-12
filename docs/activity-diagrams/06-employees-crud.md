# 06 — Employees CRUD (Karyawan)

Alur Admin mengelola data karyawan. Setiap karyawan wajib punya `region_id` dan `site_id` (1 karyawan = 1 titik).

## Aktor
- **Super Admin** — CRUD karyawan semua wilayah.
- **Admin Wilayah** — CRUD karyawan di wilayahnya saja.
- **Sistem** — `Admin\EmployeeController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/employees atau /admin/employees]
        A2[/Filter wilayah, titik, status/]
        A3{Aksi?}
        A4[Klik + Tambah Karyawan]
        A5[/Isi NIK 16 digit, NIP, nama, email,<br/>golongan, jabatan, unit, status,<br/>wilayah, titik/]
        A6[Klik Simpan]
        A7[Klik Edit baris]
        A8[/Ubah field/]
        A9[Klik Simpan]
        A10[Klik Hapus]
        A11[ConfirmDialog danger:<br/>Hapus + email + wilayah?]
        A12{Yakin?}
        A13[Klik Reset password]
        A14[Lanjut ke diagram 17]
    end

    subgraph Sistem[Sistem]
        S1[EmployeeController::index<br/>AdminPresenter::employeesFor scope]
        S2[Validate NIK 16 digit unik,<br/>NIP unik nullable, email unik]
        S3{Wilayah target = scope user?<br/>Titik ada di wilayah?}
        S4[403 Forbidden]
        S5[Employee::create<br/>password default 'password123']
        S6[Employee::update]
        S7[Employee::delete]
        S8[[Flash success sesuai aksi]]
    end

    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 -->|Tambah| A4
    A4 --> A5
    A5 --> A6
    A6 --> S2
    S2 --> S3
    S3 -->|Tidak| S4
    S3 -->|Ya| S5
    S5 --> S8
    A3 -->|Edit| A7
    A7 --> A8
    A8 --> A9
    A9 --> S2
    S2 --> S3
    S3 -->|Ya untuk edit| S6
    S6 --> S8
    A3 -->|Hapus| A10
    A10 --> A11
    A11 --> A12
    A12 -->|Tidak| End((Batal))
    A12 -->|Ya| S3
    S3 -->|Ya untuk delete| S7
    S7 --> S8
    A3 -->|Reset| A13
    A13 --> A14
    S8 --> End
    S4 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Karyawan baru mendapatkan password default `password123`. Admin bisa langsung reset ke NIK via tombol Reset — lihat [`17-reset-password-admin.md`](./17-reset-password-admin.md).
- Filter di halaman: wilayah, titik proyek, status kepegawaian, cari nama/email.
- Field `foto_url` opsional; kalau tidak ada, UI pakai avatar default.
- Admin Wilayah hanya lihat karyawan di wilayahnya (lewat `AdminPresenter::employeesFor(user->region_id)`).
