# 01 — Login Admin (Super Admin dan Admin Wilayah)

Alur autentikasi untuk akun admin. Rute `/super-admin/login` dan `/admin/login` sama-sama diproses oleh `AdminAuthController::store`, yang membedakan adalah role check setelah login sukses.

## Aktor
- **Admin** — Super Admin (role `super_admin`) atau Admin Wilayah (role `admin_wilayah`).
- **Sistem** — Laravel Auth guard `web` + `AdminAuthController`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman login<br/>/super-admin/login atau /admin/login"]:::user
    A2["2. Isi email dan kata sandi"]:::user
    A3["3. Klik tombol Masuk"]:::user

    S1["4. Validasi input:<br/>email dan sandi wajib"]:::sys
    D1{"Input kosong?"}:::dec

    S2["5. Query users<br/>WHERE email dan is_active = true"]:::sys
    D2{"User ditemukan<br/>dan sandi cocok?"}:::dec

    S3["6. Auth::guard(web)::login(user)<br/>Session::regenerate()"]:::sys
    D3{"Role sesuai<br/>area yang diakses?"}:::dec

    S4["7a. Auth::guard(web)::logout()<br/>Hapus session"]:::sys
    S5["7b. Redirect ke area sesuai role"]:::sys

    E1["ERROR<br/>Email atau kata sandi salah"]:::err
    E2["ERROR<br/>Akun tidak punya akses"]:::err

    OK["SUKSES<br/>Dashboard Admin tampil"]:::ok
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

    S3 --> D3
    D3 -->|tidak| S4
    S4 --> E2
    D3 -->|ya| S5

    S5 --> OK
    OK --> END

    E1 -->|ulangi input| A2
    E2 -->|ulangi input| A2

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

1. Admin membuka halaman `/super-admin/login` atau `/admin/login`.
2. Admin mengisi field email dan kata sandi.
3. Admin menekan tombol Masuk.
4. Sistem validasi bahwa email dan kata sandi terisi.
5. Sistem query tabel `users` dengan filter `email = ? AND is_active = true`.
6. Kalau ketemu dan `Hash::check` sandi cocok, `Auth::guard('web')->login($user)` + `session()->regenerate()`.
7. Sistem cek role user vs area yang diakses: `super_admin` untuk `/super-admin`, `admin_wilayah` untuk `/admin`. Kalau tidak cocok → logout paksa dan error.
8. Kalau semua lolos, redirect ke `/super-admin` atau `/admin` — dashboard tampil.

## Catatan implementasi
- `AdminAuthController::store` (`app/Http/Controllers/Auth/AdminAuthController.php`) memanggil `Auth::guard('web')->attempt($credentials + ['is_active' => true])` — akun nonaktif otomatis ditolak.
- Session regenerate untuk mitigasi session fixation.
- Role validasi ambil `$isSuper = str_starts_with($request->path(), 'super-admin')` lalu bandingkan dengan `$user->role`. Salah area → logout paksa dan lempar `ValidationException`.
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.
