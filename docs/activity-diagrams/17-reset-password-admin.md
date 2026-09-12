# 17 — Reset Password Karyawan oleh Admin

Alur Admin mereset kata sandi karyawan menjadi NIK dan mengaktifkan flag `must_change_password = true` sehingga karyawan wajib ganti sandi saat login berikutnya.

## Aktor
- **Admin** — Super Admin (semua karyawan) atau Admin Wilayah (karyawan di wilayahnya).
- **Sistem** — `Admin\EmployeeController::resetPassword`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /super-admin/employees<br/>atau /admin/employees"]:::user
    S1["2. Load daftar karyawan<br/>sesuai scope"]:::sys
    A2["3. Klik tombol Reset<br/>di baris karyawan"]:::user
    A3["4. ConfirmDialog warning:<br/>Reset kata sandi ke NIK?"]:::user
    D1{"Yakin?"}:::dec

    S2["5. POST /employees/<br/>{id}/reset-password"]:::sys
    D2{"Region karyawan<br/>= user->region_id?"}:::dec
    E1["ERROR 403<br/>Forbidden"]:::err

    S3["6. Set password = NIK<br/>hash bcrypt via cast"]:::sys
    S4["7. Set must_change_password<br/>= true"]:::sys
    S5["8. employee->save"]:::sys

    OK["SUKSES<br/>Toast: Kata sandi direset ke NIK<br/>karyawan wajib ganti saat login"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 --> D1
    D1 -->|tidak| END
    D1 -->|ya| S2
    S2 --> D2
    D2 -->|tidak| E1
    D2 -->|ya| S3
    S3 --> S4
    S4 --> S5
    S5 --> OK
    OK --> END

    E1 -->|kembali| A2

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

1. Admin membuka halaman daftar karyawan (`/super-admin/employees` atau `/admin/employees`).
2. Sistem memuat daftar karyawan sesuai scope role (Super Admin lihat semua, Admin Wilayah difilter `region_id`).
3. Admin klik tombol **Reset** pada baris karyawan target.
4. UI menampilkan `ConfirmDialog` warning: "Reset kata sandi {nama}? Kata sandi baru = NIK karyawan".
5. Kalau Admin konfirmasi, FE POST ke `/super-admin/employees/{id}/reset-password` (atau `/admin/…`).
6. Server cek scope: kalau `admin_wilayah` dan `employee->region_id` beda dari `user->region_id` → 403 Forbidden.
7. Set `employee->password = employee->nik` (dihash otomatis via cast `bcrypt`).
8. Set `employee->must_change_password = true`.
9. Simpan employee dan flash success dengan payload `reset_password: { employee_id, nama, nik }`.

## Catatan implementasi
- Controller: `app/Http/Controllers/Admin/EmployeeController.php::resetPassword`.
- Password lama langsung invalid karena hash ditimpa. Karyawan wajib login pakai NIK.
- Flag `must_change_password` di-share ke shared props Inertia lewat `HandleInertiaRequests::share` (`auth.employee.must_change_password`) sehingga banner peringatan otomatis muncul di layout Karyawan.
- Admin Wilayah 403 kalau target karyawan di luar wilayahnya, sesuai pola scoping global.
- Kelanjutan alur karyawan setelah reset ada di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
