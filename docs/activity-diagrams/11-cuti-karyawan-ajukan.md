# 11 — Cuti Karyawan (Ajukan dan Batalkan)

Alur karyawan mengajukan cuti dan membatalkan pengajuan (hanya saat masih `Menunggu` level 0).

## Aktor
- **Karyawan** — pemohon cuti.
- **Sistem** — `Karyawan\CutiController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Buka /karyawan/cuti]
        K2[/Lihat riwayat pengajuan sendiri/]
        K3{Aksi?}
        K4[Klik Ajukan cuti]
        K5[/Pilih jenis: Tahunan, Sakit, Besar, Melahirkan<br/>Isi mulai, selesai, alasan >=5 char/]
        K6[Klik Kirim pengajuan]
        K7[Klik Batalkan pada baris pengajuan]
        K8[ConfirmDialog danger:<br/>Batalkan pengajuan cuti?]
        K9{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[CutiController::index<br/>load Leave where employee_id + presenter approver]
        S2[Validate jenis, mulai after_or_equal today,<br/>selesai after_or_equal mulai, alasan 5..1000]
        S3[Leave::create status=Menunggu, level=0]
        S4[[Flash success: Pengajuan cuti dikirim]]
        S5{Cuti punya sendiri + status=Menunggu + level=0?}
        S6[403 Forbidden]
        S7[Leave::delete]
        S8[[Flash success: Cuti dibatalkan]]
    end

    K1 --> S1
    S1 --> K2
    K2 --> K3
    K3 -->|Ajukan| K4
    K4 --> K5
    K5 --> K6
    K6 --> S2
    S2 --> S3
    S3 --> S4
    S4 --> End((Selesai))
    K3 -->|Batalkan| K7
    K7 --> K8
    K8 --> K9
    K9 -->|Tidak| End
    K9 -->|Ya| S5
    S5 -->|Tidak| S6
    S5 -->|Ya| S7
    S7 --> S8
    S8 --> End
    S6 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- `mulai` tidak boleh lebih awal dari hari ini (`after_or_equal:today`).
- Karyawan hanya bisa membatalkan pengajuan sendiri yang statusnya `Menunggu` dan `level = 0`. Setelah admin mulai approve (level ≥ 1), tombol Batalkan tidak tampil.
- Alur approval berlanjut ke [`12-cuti-approval-3-level.md`](./12-cuti-approval-3-level.md).
