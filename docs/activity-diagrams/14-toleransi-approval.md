# 14 — Toleransi Approval oleh Admin

Alur Admin approve atau reject klaim toleransi. Single-level, tidak berjenjang. Reject wajib isi note alasan 3..500 karakter.

## Aktor
- **Admin** — Super Admin atau Admin Wilayah (Admin Wilayah hanya klaim di wilayahnya).
- **Sistem** — `Admin\LoveController` + `AdminPresenter::lovesFor` untuk scope.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /super-admin/love<br/>atau /admin/love"]:::user
    S1["2. LoveController::index<br/>AdminPresenter::lovesFor"]:::sys
    A2["3. Filter status + cari nama,<br/>klik baris klaim"]:::user
    D1{"Aksi?"}:::dec

    A3a["4a. Klik Approve"]:::user
    A3b["4b. Klik Reject"]:::user
    A3c["4c. Klik Hapus"]:::user

    A4a["5a. ConfirmDialog:<br/>Setujui klaim toleransi?"]:::user
    A4b["5b. Isi note 3..500 char<br/>+ Kirim reject"]:::user
    A4c["5c. ConfirmDialog:<br/>Hapus klaim toleransi?"]:::user

    D2{"Yakin?"}:::dec

    S2["6. Guard scope:<br/>region_id klaim = scope user?"]:::sys
    D3{"Scope OK?"}:::dec
    E1["ERROR 403<br/>Forbidden"]:::err

    D4{"status = pending?"}:::dec
    E2["ERROR<br/>Hanya pending bisa diproses"]:::err

    S3a["7a. status = approved"]:::sys
    S3b["7b. Validate note 3..500"]:::sys
    S3c["7c. status = rejected,<br/>note tersimpan"]:::sys

    S4a["8a. ToleranceClaim::save"]:::sys
    S4b["8b. ToleranceClaim::save"]:::sys

    S5["7d. ToleranceClaim::delete"]:::sys

    OK1["SUKSES<br/>Toleransi disetujui"]:::ok
    OK2["SUKSES<br/>Toleransi ditolak"]:::ok
    OK3["SUKSES<br/>Klaim toleransi dihapus"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|Approve| A3a
    A3a --> A4a
    A4a --> D2
    D2 -->|tidak| END

    D1 -->|Reject| A3b
    A3b --> A4b

    D1 -->|Hapus| A3c
    A3c --> A4c
    A4c --> D2

    D2 -->|ya| S2
    A4b --> S2

    S2 --> D3
    D3 -->|tidak| E1
    D3 -->|ya| D4
    D4 -->|tidak approve/reject| E2

    D4 -->|ya approve| S3a
    S3a --> S4a
    S4a --> OK1

    D4 -->|ya reject| S3b
    S3b --> S3c
    S3c --> S4b
    S4b --> OK2

    D4 -->|ya delete| S5
    S5 --> OK3

    OK1 --> END
    OK2 --> END
    OK3 --> END
    E1 --> END
    E2 --> END

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

1. Admin buka `/super-admin/love` atau `/admin/love`.
2. `LoveController::index` load klaim via `AdminPresenter::lovesFor` (Admin Wilayah auto-filter regionnya) + settings + approvers.
3. Admin bisa filter status (pending, approved, rejected) dan cari nama karyawan lalu klik baris klaim.
4. Pilih salah satu aksi: **Approve (4a)**, **Reject (4b)**, atau **Hapus (4c)**.
5. Untuk Approve/Hapus muncul `ConfirmDialog`. Untuk Reject muncul form note alasan wajib 3..500 karakter.
6. Guard scope: cek `region_id` klaim cocok dengan scope user (Super Admin lolos otomatis).
7. Guard status: hanya `pending` yang bisa di-approve/reject; kalau bukan → flash error.
   - **7a Approve**: `status = approved`.
   - **7b Reject**: validasi note 3..500, lalu `status = rejected` dengan note tersimpan.
   - **7d Hapus**: `ToleranceClaim::delete` (tersedia semua status).
8. Simpan model dan flash success sesuai aksi.

## Catatan implementasi
- Approve klaim tidak otomatis membuat entri attendance dengan `status = excused_love`; itu masih dikerjakan manual oleh admin atau bisa dipertimbangkan sebagai peningkatan masa depan.
- Note reject minimal 3 karakter, maks 500. Validasi di backend + frontend UI, keduanya harus konsisten.
- Kuota `love_max` dikonfigurasi di `AttendanceSetting` (default 4/bulan). Perubahan berlaku bulan berikutnya, tidak retroaktif.
- Controller: `app/Http/Controllers/Admin/LoveController.php`. Route grup `super-admin/love` dan `admin/love` share controller yang sama.
