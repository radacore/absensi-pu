# 19 — Profil Karyawan

Karyawan mengubah data pribadi yang bisa diedit sendiri: nomor telepon, email, dan foto profil. NIK/NIP/golongan/jabatan/unit/region/site hanya boleh diubah oleh Admin.

## Aktor
- **Karyawan** — pemilik akun.
- **Sistem** — `Karyawan\ProfilController::index`, `update`, `destroyFoto`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    K1["1. Buka /karyawan/profil"]:::user
    S1["2. Load me + assigned<br/>tampilkan kartu identitas"]:::sys
    K2["3. Pilih aksi<br/>Ubah data / Hapus foto"]:::user
    D1{"Aksi?"}:::dec

    K3["4a. Ubah phone/email<br/>atau upload foto max 2MB"]:::user
    K4["5a. Klik Simpan<br/>data pribadi"]:::user

    S2["6a. PUT /karyawan/profil"]:::sys
    S3["7a. Validate:<br/>phone max 20, email max 255,<br/>foto image max 2MB"]:::sys
    D2{"Payload foto?"}:::dec
    E1["ERROR 422<br/>Validasi gagal"]:::err

    S4["8a. File upload<br/>→ Storage::put foto/*"]:::sys
    S5["8b. String foto_url<br/>→ set langsung"]:::sys
    S6["9. employee->save"]:::sys

    K5["4b. Klik Hapus foto"]:::user
    K6["5b. ConfirmDialog danger:<br/>Hapus foto profil?"]:::user
    D3{"Yakin?"}:::dec
    S7["6b. DELETE /karyawan/<br/>profil/foto"]:::sys
    S8["7b. foto_url = null, save"]:::sys

    OK["SUKSES<br/>Flash: Data pribadi disimpan<br/>atau Foto dihapus"]:::ok
    END([SELESAI]):::se

    START --> K1
    K1 --> S1
    S1 --> K2
    K2 --> D1

    D1 -->|ubah data| K3
    K3 --> K4
    K4 --> S2
    S2 --> S3
    S3 --> E1
    S3 --> D2
    D2 -->|file| S4
    D2 -->|string / kosong| S5
    S4 --> S6
    S5 --> S6
    S6 --> OK

    D1 -->|hapus foto| K5
    K5 --> K6
    K6 --> D3
    D3 -->|tidak| END
    D3 -->|ya| S7
    S7 --> S8
    S8 --> OK

    D1 -->|ganti sandi| END

    OK --> END
    E1 -->|ulangi| K3

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

1. Karyawan membuka `/karyawan/profil`.
2. `ProfilController::index` memuat data `me` + `assigned` dan menampilkan kartu identitas + form data pribadi.
3. Karyawan memilih aksi: Ubah data pribadi (phone/email/foto), Hapus foto profil, atau Ganti sandi (dialihkan ke [diagram 18](./18-ganti-password-karyawan.md)).
4. (a) Untuk ubah data: edit phone/email dan/atau pilih file foto baru (max 2MB). (b) Untuk hapus: klik tombol Hapus foto.
5. (a) Klik Simpan data pribadi. (b) Konfirmasi lewat `ConfirmDialog` danger.
6. (a) FE PUT ke `/karyawan/profil`. (b) FE DELETE ke `/karyawan/profil/foto`.
7. (a) Server validate: `phone` nullable max 20, `email` nullable email max 255, `foto` nullable image max 2MB. (b) Server set `foto_url = null` lalu save.
8. Cek payload foto: file multipart → `Storage::put('foto/…')` dan set `foto_url`; string base64/URL → set `foto_url` langsung dari string; kosong → skip.
9. `employee->save()` dan flash success.

## Catatan implementasi
- Controller: `app/Http/Controllers/Karyawan/ProfilController.php` (`index`, `update`, `destroyFoto`).
- Foto disimpan ke `storage/app/public/foto/` via `Storage::put`, diakses publik via symlink `public/storage/foto/*`.
- Dua jalur upload: multipart file (server store) atau string `foto_url` (URL/base64 dari FE, misalnya crop preview).
- NIK, NIP, golongan, jabatan, unit, region, dan site hanya bisa diubah oleh Admin lewat halaman Employees.
- Ganti password punya alur sendiri di [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md).
