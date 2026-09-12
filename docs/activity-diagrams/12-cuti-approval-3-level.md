# 12 — Cuti Approval 3 Level

Alur persetujuan cuti berjenjang tiga tahap. Setiap approve menaikkan `level` +1. Reject terminal (langsung `Ditolak`). Approve pada `level=2` menjadikan status `Disetujui`.

## Aktor
- **Admin** — Super Admin atau Admin Wilayah (Admin Wilayah hanya lihat + approve cuti di wilayahnya).
- **Sistem** — `Admin\CutiController`.

## State Machine

```mermaid
stateDiagram-v2
    [*] --> Menunggu_L0: karyawan submit
    Menunggu_L0 --> Menunggu_L1: approve level 1
    Menunggu_L1 --> Menunggu_L2: approve level 2
    Menunggu_L2 --> Disetujui: approve level 3
    Menunggu_L0 --> Ditolak: reject (dengan note)
    Menunggu_L1 --> Ditolak: reject
    Menunggu_L2 --> Ditolak: reject
    Menunggu_L0 --> [*]: karyawan batalkan
    Disetujui --> [*]
    Ditolak --> [*]
```

## Alur Approve/Reject

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/cuti atau /admin/cuti]
        A2[/Filter status: Menunggu, Disetujui, Ditolak/]
        A3[Klik baris pengajuan]
        A4{Aksi?}
        A5[Klik Setujui]
        A6[ConfirmDialog warning:<br/>Setujui cuti ini?]
        A7{Yakin?}
        A8[Klik Tolak]
        A9[/Isi note alasan >=3 char/]
        A10[Klik Kirim tolak]
        A11[Klik Hapus]
        A12[ConfirmDialog danger:<br/>Hapus pengajuan cuti?]
        A13{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[CutiController::index<br/>AdminPresenter::leavesFor scope]
        S2{Cuti dalam scope user->region?}
        S3[403 Forbidden]
        S4{Status = Menunggu?}
        S5[[Flash error: Hanya yang Menunggu bisa di-approve]]
        S6[level += 1]
        S7{level == 3?}
        S8[status = Disetujui]
        S9[Simpan status = Menunggu, level baru]
        S10[Cuti::save]
        S11[[Flash success: Cuti disetujui final / Cuti naik ke level N]]
        S12{Status = Menunggu?}
        S13[status = Ditolak, note]
        S14[[Flash success: Cuti ditolak]]
        S15[Leave::delete]
        S16[[Flash success: Cuti dihapus]]
    end

    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 --> A4
    A4 -->|Setujui| A5
    A5 --> A6
    A6 --> A7
    A7 -->|Tidak| End((Batal))
    A7 -->|Ya| S2
    S2 -->|Tidak| S3
    S2 -->|Ya| S4
    S4 -->|Tidak| S5
    S4 -->|Ya| S6
    S6 --> S7
    S7 -->|Ya| S8
    S7 -->|Tidak| S9
    S8 --> S10
    S9 --> S10
    S10 --> S11
    S11 --> End
    A4 -->|Tolak| A8
    A8 --> A9
    A9 --> A10
    A10 --> S2
    S2 -->|Ya reject| S12
    S12 -->|Tidak| S5
    S12 -->|Ya| S13
    S13 --> S14
    S14 --> End
    A4 -->|Hapus| A11
    A11 --> A12
    A12 --> A13
    A13 -->|Tidak| End
    A13 -->|Ya| S2
    S2 -->|Ya delete| S15
    S15 --> S16
    S16 --> End
    S3 --> End
    S5 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Admin Wilayah hanya bisa approve/reject cuti di wilayahnya (cek `$cuti->employee->region_id`).
- Approve pada `level=2` menaikkan level ke 3 sekaligus set `status=Disetujui` (final).
- Reject dari `level` berapa pun terminal — tidak bisa di-approve lagi.
- Reject butuh input `note` minimal 3 karakter (validasi backend + frontend).
