# 01 — Login Admin (Super Admin dan Admin Wilayah)

Alur autentikasi untuk akun admin. Rute `/super-admin/login` dan `/admin/login` sama-sama diproses oleh `AdminAuthController::store`, yang membedakan adalah role check setelah login sukses.

## Aktor
- **Admin** — Super Admin (role `super_admin`) atau Admin Wilayah (role `admin_wilayah`).
- **Sistem** — Laravel Auth guard `web` + `AdminAuthController`.

## Activity Diagram

```mermaid
flowchart TB
    Start(( )):::startEnd

    A1["Buka halaman login<br/>area super-admin atau admin"]:::actor
    A2["Isi email dan kata sandi"]:::actor
    A3["Klik tombol Masuk"]:::actor

    S1{"Field email atau<br/>kata sandi kosong?"}:::decision
    S2["Cari user by email<br/>dengan filter is_active = true"]:::system
    S3{"Kredensial valid?"}:::decision
    S4["Session::regenerate"]:::system
    S5{"Role sesuai area?<br/>super_admin di /super-admin<br/>admin_wilayah di /admin"}:::decision
    S6["Auth::guard(web)::logout"]:::system
    S7["Redirect ke /super-admin<br/>atau /admin"]:::system
    S8["Kembalikan ValidationException<br/>Akun tidak memiliki akses"]:::error
    S9["Kembalikan ValidationException<br/>Email atau kata sandi salah"]:::error

    A4["Lihat toast error di pojok bawah"]:::actor
    A5["Ulangi input kredensial"]:::actor

    D1["Tampilkan Dashboard Admin<br/>dengan sidebar dan aktivitas terbaru"]:::success

    End(( )):::startEnd

    Start --> A1
    A1 --> A2
    A2 --> A3
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
    D1 --> End
    S8 --> A4
    S9 --> A4
    A4 --> A5
    A5 --> A3

    classDef startEnd fill:#0F172A,stroke:#0F172A,stroke-width:0px,color:#fff
    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1.5px,color:#0F172A,rx:2,ry:2
    classDef system fill:#F8FAFC,stroke:#334155,stroke-width:1.5px,color:#0F172A,rx:2,ry:2
    classDef decision fill:#FEF3C7,stroke:#F59E0B,stroke-width:1.5px,color:#92400E,rx:2,ry:2
    classDef error fill:#FEF2F2,stroke:#EF4444,stroke-width:1.5px,color:#991B1B,rx:2,ry:2
    classDef success fill:#ECFDF5,stroke:#10B981,stroke-width:1.5px,color:#065F46,rx:2,ry:2

    linkStyle default stroke:#334155,stroke-width:1.5px
```

## Legenda

| Warna | Jenis |
|---|---|
| Navy (bulat pekat) | Start / End |
| Biru muda | Aksi Aktor (Admin) |
| Abu-abu | Aksi Sistem |
| Kuning | Keputusan (kondisi) |
| Merah | Error / kegagalan |
| Hijau | Sukses / hasil akhir |

## Catatan implementasi
- `AdminAuthController::store` (`app/Http/Controllers/Auth/AdminAuthController.php`) memanggil `Auth::guard('web')->attempt($credentials + ['is_active' => true])` — akun nonaktif otomatis ditolak.
- Session regenerate untuk mitigasi session fixation.
- Role validasi ambil `$isSuper = str_starts_with($request->path(), 'super-admin')` lalu bandingkan dengan `$user->role`. Salah area, logout paksa dan lempar `ValidationException`.
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.
