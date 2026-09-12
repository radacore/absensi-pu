# 19 — Profil Karyawan

Karyawan mengubah data pribadi yang bisa diedit sendiri: nomor telepon, email, foto profil. NIK/NIP/golongan/unit tidak bisa diubah mandiri (butuh admin).

## Aktor
- **Karyawan** — pemilik akun.
- **Sistem** — `Karyawan\ProfilController::index`, `update`, `destroyFoto`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Buka /karyawan/profil]
        K2[/Lihat kartu identitas + form data pribadi/]
        K3{Aksi?}
        K4[Ubah phone / email]
        K5[Upload foto baru]
        K6[/Pilih file gambar max 2MB/]
        K7[Klik Simpan data pribadi]
        K8[Hapus foto profil]
        K9[ConfirmDialog danger:<br/>Hapus foto profil?]
        K10{Yakin?}
        K11[Ganti kata sandi<br/>lihat diagram 18]
    end

    subgraph Sistem[Sistem]
        S1[ProfilController::index<br/>load me + assigned]
        S2[PUT /karyawan/profil]
        S3[Validate phone nullable max 20,<br/>email nullable email max 255,<br/>foto nullable image max 2MB]
        S4{File foto ada?}
        S5[Storage::put foto/ + set foto_url]
        S6{foto_url string ada?}
        S7[Set foto_url dari string base64/URL]
        S8[Save employee]
        S9[[Flash success: Data pribadi disimpan]]
        S10[DELETE /karyawan/profil/foto]
        S11[Set foto_url = null, save]
        S12[[Flash success: Foto profil dihapus]]
    end

    K1 --> S1
    S1 --> K2
    K2 --> K3
    K3 -->|Ubah phone/email| K4
    K4 --> K7
    K3 -->|Upload foto| K5
    K5 --> K6
    K6 --> K7
    K7 --> S2
    S2 --> S3
    S3 --> S4
    S4 -->|Ya| S5
    S4 -->|Tidak| S6
    S6 -->|Ya| S7
    S6 -->|Tidak| S8
    S5 --> S8
    S7 --> S8
    S8 --> S9
    S9 --> End((Selesai))
    K3 -->|Hapus foto| K8
    K8 --> K9
    K9 --> K10
    K10 -->|Tidak| End
    K10 -->|Ya| S10
    S10 --> S11
    S11 --> S12
    S12 --> End
    K3 -->|Ganti sandi| K11
    K11 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Foto disimpan ke `storage/app/public/foto/` via `Storage::put` dan diakses via symlink `public/storage/foto/*`.
- Ada dua jalur upload: multipart file (server store) atau string `foto_url` (URL/base64 dari FE, misalnya crop preview).
- Ganti password punya alur sendiri di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
- NIK, NIP, golongan, jabatan, unit, region, site hanya bisa diubah oleh Admin lewat halaman Employees.
