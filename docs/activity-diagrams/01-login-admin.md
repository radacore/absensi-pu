# 01 — Login Admin (Super Admin dan Admin Wilayah)

Alur autentikasi untuk akun admin. Rute `/super-admin/login` dan `/admin/login` sama-sama diproses oleh `AdminAuthController::store`, yang membedakan adalah role check setelah login sukses.

## Aktor
- **Admin** — Super Admin (role `super_admin`) atau Admin Wilayah (role `admin_wilayah`).
- **Sistem** — Laravel Auth guard `web` + `AdminAuthController`.

## Activity Diagram (Swimlane)

```mermaid
flowchart LR
    Start(( )):::se

    subgraph LaneAdmin["👤 ADMIN"]
        direction TB
        A1["Buka halaman login<br/>super-admin atau admin"]
        A2["Isi email dan<br/>kata sandi"]
        A3["Klik tombol Masuk"]
        A4["Lihat toast error<br/>di pojok bawah"]
        A5["Ulangi input"]
    end

    subgraph LaneSistem["⚙️ SISTEM"]
        direction TB
        S1{"Field kosong?"}
        S2["Cari user by email<br/>is_active = true"]
        S3{"Kredensial valid?"}
        S4["Regenerate session"]
        S5{"Role sesuai area?"}
        S6["Logout paksa<br/>guard web"]
        S7["Redirect ke area"]
        S8["ValidationException<br/>Tidak ada akses"]
        S9["ValidationException<br/>Email atau sandi salah"]
    end

    subgraph LaneDash["✅ DASHBOARD"]
        direction TB
        D1["Tampilkan Dashboard<br/>Admin dengan sidebar<br/>dan aktivitas terbaru"]
    end

    End(( )):::se

    Start --> A1
    A1 --> A2 --> A3 --> S1
    S1 -->|Ya| S9
    S1 -->|Tidak| S2 --> S3
    S3 -->|Tidak| S9
    S3 -->|Ya| S4 --> S5
    S5 -->|Tidak| S6 --> S8
    S5 -->|Ya| S7 --> D1 --> End
    S8 --> A4
    S9 --> A4
    A4 --> A5 --> A3

    classDef se fill:#0F172A,stroke:#0F172A,color:#fff,stroke-width:0px

    style LaneAdmin fill:#EFF6FF,stroke:#1E3A8A,stroke-width:2px,color:#1E3A8A
    style LaneSistem fill:#F8FAFC,stroke:#334155,stroke-width:2px,color:#334155
    style LaneDash fill:#ECFDF5,stroke:#10B981,stroke-width:2px,color:#065F46

    style A1 fill:#fff,stroke:#1E3A8A,stroke-width:1.5px,color:#0F172A
    style A2 fill:#fff,stroke:#1E3A8A,stroke-width:1.5px,color:#0F172A
    style A3 fill:#fff,stroke:#1E3A8A,stroke-width:1.5px,color:#0F172A
    style A4 fill:#FEF2F2,stroke:#EF4444,stroke-width:1.5px,color:#991B1B
    style A5 fill:#fff,stroke:#1E3A8A,stroke-width:1.5px,color:#0F172A

    style S1 fill:#FEF3C7,stroke:#F59E0B,stroke-width:1.5px,color:#92400E
    style S2 fill:#fff,stroke:#334155,stroke-width:1.5px,color:#0F172A
    style S3 fill:#FEF3C7,stroke:#F59E0B,stroke-width:1.5px,color:#92400E
    style S4 fill:#fff,stroke:#334155,stroke-width:1.5px,color:#0F172A
    style S5 fill:#FEF3C7,stroke:#F59E0B,stroke-width:1.5px,color:#92400E
    style S6 fill:#fff,stroke:#334155,stroke-width:1.5px,color:#0F172A
    style S7 fill:#fff,stroke:#334155,stroke-width:1.5px,color:#0F172A
    style S8 fill:#FEF2F2,stroke:#EF4444,stroke-width:1.5px,color:#991B1B
    style S9 fill:#FEF2F2,stroke:#EF4444,stroke-width:1.5px,color:#991B1B

    style D1 fill:#fff,stroke:#10B981,stroke-width:1.5px,color:#065F46

    linkStyle default stroke:#64748B,stroke-width:1.5px
```

## Legenda

| Warna Border | Jenis Node |
|---|---|
| Navy (bulat pekat) | Start / End |
| Biru — kolom ADMIN | Aksi oleh Admin |
| Abu-abu — kolom SISTEM | Proses server |
| Kuning | Keputusan |
| Merah | Error / kegagalan |
| Hijau — kolom DASHBOARD | Hasil akhir sukses |

## Catatan implementasi
- `AdminAuthController::store` (`app/Http/Controllers/Auth/AdminAuthController.php`) memanggil `Auth::guard('web')->attempt($credentials + ['is_active' => true])` — akun nonaktif otomatis ditolak.
- Session regenerate untuk mitigasi session fixation.
- Role validasi ambil `$isSuper = str_starts_with($request->path(), 'super-admin')` lalu bandingkan dengan `$user->role`. Salah area, logout paksa dan lempar `ValidationException`.
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.
