# 02 — Login Karyawan

Karyawan masuk lewat halaman yang sama dengan semua peran, `/login`, memakai email dan kata sandi. `LoginController::store` membuka sesi guard `employee` bila email cocok dengan baris karyawan. Kalau kata sandi masih sandi bawaan, karyawan diarahkan untuk menggantinya lebih dulu.

## Aktor
- **Karyawan** — pegawai dengan akun di tabel `employees`.
- **Sistem** — Laravel Auth guard `employee` + `EmployeeAuthController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman<br/>/login"]:::user
    A2["2. Isi email<br/>dan kata sandi"]:::user
    A3["3. Klik tombol Masuk"]:::user

    S1["4. Validasi input:<br/>login dan sandi wajib"]:::sys
    D1{"Input kosong?"}:::dec

    S2["5. Query employees<br/>WHERE nip = login<br/>OR nik = login"]:::sys
    D2{"Employee ditemukan<br/>dan sandi cocok?"}:::dec

    S3["6. Auth::guard(employee)::login<br/>Session::regenerate()"]:::sys
    S4["7. Baca flag<br/>must_change_password"]:::sys
    D3{"Masih memakai<br/>sandi bawaan?"}:::dec

    S5["8a. Redirect ke<br/>/karyawan (dashboard)"]:::sys
    S6["8b. Tampilkan banner kuning<br/>Ganti sandi sekarang"]:::sys
    A4["9. Klik Ganti sekarang<br/>ke /karyawan/profil"]:::user

    E1["ERROR<br/>Email atau<br/>sandi salah"]:::err

    OK["SUKSES<br/>Dashboard Karyawan tampil"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> A2
    A2 --> A3
    A3 --> S1
    S1 --> D1

    D1 -->|ya| E1
    D1 -->|tidak| S2

    S2 --> D2
    D2 -->|tidak| E1
    D2 -->|ya| S3

    S3 --> S4
    S4 --> D3
    D3 -->|tidak| S5
    D3 -->|ya| S6
    S6 --> A4
    A4 --> S5

    S5 --> OK
    OK --> END

    E1 -->|ulangi input| A2

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
| Biru muda | Aksi Aktor (Karyawan) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

1. Karyawan membuka halaman `/login`.
2. Karyawan mengisi email dan kata sandi.
3. Karyawan menekan tombol Masuk.
4. Sistem validasi input `login` atau `email` dan `password` wajib terisi.
5. Sistem query tabel `employees` dengan `LOWER(email) = ?` memakai email yang dikirim.
6. Kalau employee ketemu dan `Hash::check` sandi cocok, `Auth::guard('employee')->login($employee)` + `session()->regenerate()`.
7. Sistem baca flag `employee.must_change_password` dan bagikan lewat shared prop `auth.employee`.
8. Kalau kata sandi masih sandi bawaan, banner kuning tampil di dashboard; kalau tidak, langsung redirect ke `/karyawan`.
9. Karyawan klik tombol "Ganti sekarang" untuk masuk ke `/karyawan/profil` dan mengganti password.

## Catatan implementasi
- `LoginController::store` (`app/Http/Controllers/Auth/LoginController.php`) menerima input `login` maupun `email`, keduanya berisi alamat email.
- Password di-hash bcrypt via cast `'password' => 'hashed'` di model `Employee`.
- `HandleInertiaRequests::share` mengekspor `auth.employee.must_change_password` untuk mendeteksi apakah karyawan perlu ganti password. Detail alur banner ada di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.
