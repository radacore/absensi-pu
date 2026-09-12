# 01 — Login Admin (Super Admin dan Admin Wilayah)

Alur autentikasi untuk akun admin. Rute `/super-admin/login` dan `/admin/login` sama-sama diproses oleh `AdminAuthController::store`, yang membedakan adalah role check setelah login sukses.

## Aktor
- **Admin** — Super Admin (role `super_admin`) atau Admin Wilayah (role `admin_wilayah`).
- **Sistem** — Laravel Auth guard `web` + `AdminAuthController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka halaman login area super-admin atau admin]
        A2[/Isi email dan kata sandi/]
        A3[Klik tombol Masuk]
        A9[/Melihat pesan error toast/]
        A10[Ulangi input kredensial]
    end

    subgraph Sistem[Sistem]
        S1{Field kosong?}
        S2[Cari user dengan email + is_active=true]
        S3{Kredensial valid?}
        S4[Regenerate session]
        S5{Role sesuai area?<br/>super_admin di /super-admin<br/>admin_wilayah di /admin}
        S6[Logout paksa dari guard web]
        S7[Redirect ke /super-admin atau /admin]
        S8[Kembalikan error 'Akun tidak memiliki akses']
        S9[Kembalikan error 'Email atau kata sandi salah']
    end

    subgraph Dashboard[Dashboard Admin]
        D1[Tampilkan Dashboard dengan sidebar dan aktivitas terbaru]
    end

    A3 --> S1
    S1 -->|Ya| S9
    S1 -->|Tidak| S2
    S2 --> S3
    S3 -->|Tidak| S9
    S3 -->|Ya| S4
    S4 --> S5
    S5 -->|Tidak| S6
    S6 --> S8
    S5 -->|Ya| S7
    S7 --> D1
    D1 --> End((Selesai))
    S8 --> A9
    S9 --> A9
    A9 --> A10
    A10 --> A3

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    classDef done fill:#ECFDF5,stroke:#10B981,stroke-width:1px,color:#065F46
    class Admin actor
    class Sistem system
    class Dashboard done
```

## Catatan implementasi
- `AdminAuthController::store` (`app/Http/Controllers/Auth/AdminAuthController.php`) memanggil `Auth::guard('web')->attempt($credentials + ['is_active' => true])` — akun nonaktif otomatis ditolak.
- Session regenerate untuk mitigasi session fixation.
- Role validasi ambil `$isSuper = str_starts_with($request->path(), 'super-admin')` lalu bandingkan dengan `$user->role`. Salah area, logout paksa dan lempar `ValidationException`.
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.
