# 17 — Reset Password Karyawan oleh Admin

Alur Admin mereset kata sandi karyawan ke NIK dan mengaktifkan flag `must_change_password = true`.

## Aktor
- **Admin** — Super Admin (semua karyawan) atau Admin Wilayah (karyawan di wilayahnya).
- **Sistem** — `Admin\EmployeeController::resetPassword`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/employees atau /admin/employees]
        A2[/Lihat daftar karyawan/]
        A3[Klik tombol Reset di baris karyawan]
        A4[ConfirmDialog warning:<br/>Reset kata sandi Andi Saputra?<br/>Kata sandi baru = NIK karyawan]
        A5{Yakin?}
        A6[Batal, tetap]
        A7[Konfirmasi Reset ke NIK]
        A8[/Lihat toast: 'Kata sandi X direset ke NIK. Karyawan wajib ganti kata sandi saat login.'/]
    end

    subgraph Sistem[Sistem]
        S1[POST /super-admin/employees/id/reset-password]
        S2{Scope match user->region_id?}
        S3[403 Forbidden]
        S4[Set employee->password = employee->nik<br/>hash bcrypt via cast]
        S5[Set employee->must_change_password = true]
        S6[employee->save]
        S7[[Flash success + payload reset_password: employee_id, nama, nik]]
    end

    A1 --> A2
    A2 --> A3
    A3 --> A4
    A4 --> A5
    A5 -->|Tidak| A6
    A6 --> End((Selesai))
    A5 -->|Ya| S1
    S1 --> S2
    S2 -->|Tidak| S3
    S2 -->|Ya| S4
    S4 --> S5
    S5 --> S6
    S6 --> S7
    S7 --> A8
    A8 --> End
    S3 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Password lama langsung invalid karena hash ditimpa. Karyawan wajib login pakai NIK.
- `must_change_password` flag di-share ke shared props Inertia lewat `HandleInertiaRequests::share` (`auth.employee.must_change_password`) sehingga banner peringatan otomatis muncul di layout Karyawan.
- Admin Wilayah 403 kalau target karyawan di luar wilayahnya, sesuai pola scoping.
- Kelanjutan alur karyawan setelah reset ada di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
