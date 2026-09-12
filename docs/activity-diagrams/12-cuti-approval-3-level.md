# 12 — Cuti Approval 3 Level

Alur persetujuan cuti berjenjang tiga tahap. Setiap approve menaikkan `level` +1. Reject terminal langsung menjadi `Ditolak`. Approve pada `level = 2` menaikkan ke `level = 3` sekaligus set `status = Disetujui` (final).

## Aktor
- **Karyawan** — pemohon cuti (submit + optional cancel di level 0).
- **Admin** — Super Admin atau Admin Wilayah. Admin Wilayah hanya lihat + approve cuti di wilayahnya.
- **Sistem** — `Admin\CutiController` (`update`, `destroy`) + `AdminPresenter::leavesFor` untuk scope.

## Diagram 1 — Flow Karyawan + State Cuti

Menggambarkan siklus hidup cuti dari sisi karyawan, plus transisi state yang dipicu admin (dilihat dari luar).

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Karyawan submit cuti"]:::user
    S1["2. Leave::create<br/>status=Menunggu, level=0"]:::sys
    ST0["3. State: Menunggu L0"]:::sys

    D1{"Karyawan batalkan<br/>sebelum admin?"}:::dec
    S2["4a. Leave::delete"]:::sys
    OK0["SELESAI<br/>Cuti dibatalkan"]:::ok

    D2{"Aksi admin<br/>di L0?"}:::dec
    ST1["5. State: Menunggu L1"]:::sys
    D3{"Aksi admin<br/>di L1?"}:::dec
    ST2["6. State: Menunggu L2"]:::sys
    D4{"Aksi admin<br/>di L2?"}:::dec

    OK1["SUKSES<br/>Disetujui (final)"]:::ok
    E1["DITOLAK<br/>Terminal + note"]:::err

    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> ST0
    ST0 --> D1
    D1 -->|ya| S2
    S2 --> OK0
    OK0 --> END
    D1 -->|tidak| D2

    D2 -->|approve| ST1
    D2 -->|reject| E1
    ST1 --> D3
    D3 -->|approve| ST2
    D3 -->|reject| E1
    ST2 --> D4
    D4 -->|approve L3| OK1
    D4 -->|reject| E1

    OK1 --> END
    E1 --> END

    classDef se fill:#0F172A,stroke:#0F172A,color:#fff,stroke-width:2px
    classDef user fill:#EFF6FF,stroke:#1E3A8A,color:#1E3A8A,stroke-width:1.5px
    classDef sys fill:#F8FAFC,stroke:#334155,color:#0F172A,stroke-width:1.5px
    classDef dec fill:#FEF3C7,stroke:#F59E0B,color:#92400E,stroke-width:1.5px
    classDef err fill:#FEE2E2,stroke:#EF4444,color:#991B1B,stroke-width:1.5px
    classDef ok fill:#DCFCE7,stroke:#10B981,color:#065F46,stroke-width:1.5px

    linkStyle default stroke:#334155,stroke-width:1.5px
```

## Diagram 2 — Flow Admin (Approve / Reject / Hapus)

Detail interaksi admin per baris pengajuan dengan semua guard dan cabang aksi.

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /super-admin/cuti<br/>atau /admin/cuti"]:::user
    S1["2. CutiController::index<br/>AdminPresenter::leavesFor"]:::sys
    A2["3. Filter status, klik<br/>baris pengajuan"]:::user
    D1{"Aksi?"}:::dec

    A3a["4a. Klik Setujui"]:::user
    A3b["4b. Klik Tolak"]:::user
    A3c["4c. Klik Hapus"]:::user

    A4a["5a. ConfirmDialog:<br/>Setujui cuti ini?"]:::user
    A4b["5b. Isi note alasan<br/>>=3 char + Kirim"]:::user
    A4c["5c. ConfirmDialog:<br/>Hapus pengajuan?"]:::user

    D2{"Yakin?"}:::dec

    S2["6. Guard scope:<br/>cuti dalam region user?"]:::sys
    D3{"Scope OK?"}:::dec
    E1["ERROR 403<br/>Forbidden"]:::err

    D4{"status = Menunggu?"}:::dec
    E2["ERROR<br/>Hanya Menunggu bisa diproses"]:::err

    S3a["7a. level += 1"]:::sys
    D5{"level == 3?"}:::dec
    S4a["8a. status = Disetujui<br/>(final)"]:::sys
    S4b["8b. status = Menunggu<br/>level baru"]:::sys
    S5a["9a. Leave::save"]:::sys
    OK1["SUKSES<br/>Cuti disetujui / naik level N"]:::ok

    S3b["7b. Validate note 3..500"]:::sys
    S4c["8c. status = Ditolak,<br/>note tersimpan"]:::sys
    S5b["9b. Leave::save"]:::sys
    OK2["SUKSES<br/>Cuti ditolak"]:::ok

    S3c["7c. Leave::delete"]:::sys
    OK3["SUKSES<br/>Cuti dihapus"]:::ok

    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|Setujui| A3a
    A3a --> A4a
    A4a --> D2
    D2 -->|tidak| END

    D1 -->|Tolak| A3b
    A3b --> A4b

    D1 -->|Hapus| A3c
    A3c --> A4c
    A4c --> D2

    D2 -->|ya| S2
    A4b --> S2

    S2 --> D3
    D3 -->|tidak| E1
    D3 -->|ya| D4
    D4 -->|tidak| E2

    D4 -->|ya approve| S3a
    S3a --> D5
    D5 -->|ya| S4a
    D5 -->|tidak| S4b
    S4a --> S5a
    S4b --> S5a
    S5a --> OK1

    D4 -->|ya reject| S3b
    S3b --> S4c
    S4c --> S5b
    S5b --> OK2

    D4 -->|ya delete| S3c
    S3c --> OK3

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
| Biru muda | Aksi Aktor (Karyawan / Admin) |
| Abu-abu putih | Proses Sistem / State cuti |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error / status Ditolak |
| Hijau muda | Hasil sukses / status Disetujui |

## Langkah-Langkah Detail

### Diagram 1 — Karyawan + State

1. Karyawan submit form pengajuan cuti (lihat [`11-cuti-karyawan-ajukan.md`](./11-cuti-karyawan-ajukan.md)).
2. `Leave::create` dengan `status = Menunggu` dan `level = 0`.
3. State awal cuti adalah `Menunggu L0`.
4. Kalau karyawan membatalkan sebelum admin sentuh (4a), record di-delete.
5. Admin approve di L0 → naik ke `Menunggu L1`.
6. Admin approve di L1 → naik ke `Menunggu L2`.
7. Admin approve di L2 → `level = 3` + `status = Disetujui` (final).
8. Reject di level manapun langsung terminal `Ditolak` dengan note wajib.

### Diagram 2 — Admin

1. Admin buka `/super-admin/cuti` atau `/admin/cuti`.
2. `CutiController::index` memakai `AdminPresenter::leavesFor` untuk scope (Admin Wilayah otomatis di-filter regionnya).
3. Admin bisa filter status (Menunggu, Disetujui, Ditolak) lalu klik salah satu baris.
4. Pilih salah satu aksi: **Setujui (4a)**, **Tolak (4b)**, atau **Hapus (4c)**.
5. Untuk Setujui/Hapus muncul `ConfirmDialog`; untuk Tolak muncul form note dengan minimal 3 karakter.
6. Guard scope: cek `$cuti->employee->region_id` cocok dengan `region_id` user (Super Admin lolos otomatis).
7. Guard status: hanya `Menunggu` yang bisa di-approve/reject; kalau bukan → flash error dan stop.
   - **7a Setujui**: `level += 1`. Kalau jadi `3`, `status = Disetujui`. Kalau belum, tetap `Menunggu` dengan level baru.
   - **7b Tolak**: validasi `note` 3..500, set `status = Ditolak` + simpan `note`.
   - **7c Hapus**: `Leave::delete` (idempotent cleanup).
8. Simpan model, flash success sesuai aksi.

## Catatan implementasi
- Controller: `app/Http/Controllers/Admin/CutiController.php`. Route grup `super-admin/cuti` dan `admin/cuti` share controller yang sama.
- Admin Wilayah hanya bisa approve/reject cuti di wilayahnya (cek `$cuti->employee->region_id` vs `Auth::user()->region_id`).
- Approve pada `level = 2` menaikkan level ke 3 sekaligus set `status = Disetujui` (final, tidak bisa direvisi).
- Reject dari `level` berapa pun terminal — tidak bisa di-approve lagi. Karyawan harus submit ulang.
- Reject butuh input `note` minimal 3 karakter (validasi backend + frontend), maks 500 char.
- State machine implicit di kolom `(status, level)`: `(Menunggu, 0..2)` → in-progress, `(Disetujui, 3)` → final approved, `(Ditolak, *)` → final rejected.
