# 11 — Cuti Karyawan (Ajukan dan Batalkan)

Alur karyawan mengajukan cuti dan membatalkan pengajuan. Batalkan hanya boleh saat status masih `Menunggu` dan `level = 0` (belum disentuh admin).

## Aktor
- **Karyawan** — pemohon cuti.
- **Sistem** — `Karyawan\CutiController` (`index`, `store`, `destroy`).

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /karyawan/cuti"]:::user
    S1["2. Load Leave own<br/>+ presenter approver"]:::sys
    A2["3. Lihat riwayat<br/>pengajuan sendiri"]:::user
    D1{"Pilih aksi?"}:::dec

    A3a["4a. Klik Ajukan cuti"]:::user
    A4["5. Pilih jenis, tanggal,<br/>isi alasan >=5 char"]:::user
    A5["6. Klik Kirim pengajuan"]:::user
    S2["7. Validate jenis, mulai,<br/>selesai, alasan"]:::sys
    D2{"Input valid?"}:::dec
    E1["ERROR<br/>Pesan validasi"]:::err
    S3["8. Leave::create<br/>status=Menunggu, level=0"]:::sys
    OK1["SUKSES<br/>Pengajuan cuti dikirim"]:::ok

    A3b["4b. Klik Batalkan<br/>pada baris pengajuan"]:::user
    A6["5b. ConfirmDialog danger:<br/>Batalkan pengajuan?"]:::user
    D3{"Yakin?"}:::dec
    S4["6b. Guard: milik sendiri +<br/>Menunggu + level=0"]:::sys
    D4{"Lolos guard?"}:::dec
    E2["ERROR 403<br/>Forbidden"]:::err
    S5["7b. Leave::delete"]:::sys
    OK2["SUKSES<br/>Cuti dibatalkan"]:::ok

    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|Ajukan| A3a
    A3a --> A4
    A4 --> A5
    A5 --> S2
    S2 --> D2
    D2 -->|tidak| E1
    D2 -->|ya| S3
    S3 --> OK1
    OK1 --> END
    E1 -->|ulangi| A4

    D1 -->|Batalkan| A3b
    A3b --> A6
    A6 --> D3
    D3 -->|tidak| END
    D3 -->|ya| S4
    S4 --> D4
    D4 -->|tidak| E2
    D4 -->|ya| S5
    S5 --> OK2
    OK2 --> END
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
| Biru muda | Aksi Aktor (Karyawan) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

1. Karyawan membuka `/karyawan/cuti`.
2. `CutiController::index` load daftar `Leave` milik karyawan + presenter data approver.
3. Karyawan melihat riwayat pengajuan sendiri.
4. Karyawan memilih salah satu aksi: **Ajukan (4a)** atau **Batalkan (4b)** pengajuan yang masih Menunggu.

### Cabang Ajukan

5. Karyawan memilih jenis cuti (Tahunan, Sakit, Besar, Melahirkan), tanggal mulai + selesai, dan isi alasan minimal 5 karakter.
6. Karyawan menekan Kirim pengajuan.
7. Sistem validasi: `jenis` enum, `mulai` `after_or_equal:today`, `selesai` `after_or_equal:mulai`, `alasan` `min:5|max:1000`.
8. Kalau lolos, `Leave::create` dengan `status = Menunggu` dan `level = 0`. Flash success "Pengajuan cuti dikirim".

### Cabang Batalkan

5b. UI tampilkan `ConfirmDialog` bertema danger: "Batalkan pengajuan cuti?".
6b. Kalau user konfirmasi, sistem cek guard: pengajuan milik user login, `status = Menunggu`, dan `level = 0`.
7b. Kalau semua lolos, `Leave::delete`. Kalau tidak, respons 403 Forbidden.

## Catatan implementasi
- `mulai` tidak boleh lebih awal dari hari ini (`after_or_equal:today`) supaya karyawan tidak backdated request.
- Karyawan hanya bisa membatalkan pengajuan sendiri yang statusnya `Menunggu` dan `level = 0`. Setelah admin mulai approve (level ≥ 1), tombol Batalkan tidak tampil di UI dan backend tetap 403 kalau di-force.
- Alur approval berlanjut ke [`12-cuti-approval-3-level.md`](./12-cuti-approval-3-level.md).
