# 02 — Login Karyawan

Karyawan login memakai **NIP atau NIK** sebagai identifier dan kata sandi. Rute `/karyawan/login` diproses `EmployeeAuthController::store` dengan guard `employee`.

## Aktor
- **Karyawan** — pegawai dengan akun di tabel `employees`.
- **Sistem** — Laravel Auth guard `employee` + `EmployeeAuthController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Buka /karyawan/login]
        K2[/Isi NIP atau NIK dan kata sandi/]
        K3[Klik Masuk]
        K8[/Melihat toast error/]
        K9[Ulangi input]
        K10[Lihat dashboard karyawan]
        K11{Password masih = NIK?}
        K12[Lihat banner kuning<br/>Kata sandi masih NIK, ganti sekarang]
        K13[Klik Ganti sekarang<br/>ke /karyawan/profil]
    end

    subgraph Sistem[Sistem]
        S1{Field kosong?}
        S2[Query employees<br/>where nip = login OR nik = login]
        S3{Employee ditemukan?}
        S4{Hash::check password?}
        S5[Auth::guard employee->login]
        S6[Session regenerate]
        S7[Redirect /karyawan]
        S8[Kembalikan error 'NIP/NIK atau kata sandi salah']
        S9[Baca employee.must_change_password<br/>bagikan lewat shared prop auth.employee]
    end

    K3 --> S1
    S1 -->|Ya| S8
    S1 -->|Tidak| S2
    S2 --> S3
    S3 -->|Tidak| S8
    S3 -->|Ya| S4
    S4 -->|Tidak| S8
    S4 -->|Ya| S5
    S5 --> S6
    S6 --> S7
    S7 --> S9
    S9 --> K10
    K10 --> K11
    K11 -->|Ya| K12
    K11 -->|Tidak| End((Selesai))
    K12 --> K13
    K13 --> End
    S8 --> K8
    K8 --> K9
    K9 --> K3

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    classDef warn fill:#FEF3C7,stroke:#F59E0B,stroke-width:1px,color:#92400E
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- `EmployeeAuthController::store` (`app/Http/Controllers/Auth/EmployeeAuthController.php`) menerima input `login` yang bisa NIP atau NIK: `Employee::where('nip', $login)->orWhere('nik', $login)->first()`.
- Password di-hash bcrypt via cast `'password' => 'hashed'` di model `Employee`.
- `HandleInertiaRequests::share` mengekspor `auth.employee.must_change_password` untuk mendeteksi apakah karyawan perlu ganti password. Detail alur banner ada di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
