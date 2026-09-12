# 14 — Toleransi Approval oleh Admin

Alur Admin approve atau reject klaim toleransi. Single-level, tidak berjenjang.

## Aktor
- **Admin** — Super Admin atau Admin Wilayah (Admin Wilayah hanya klaim di wilayahnya).
- **Sistem** — `Admin\LoveController`.

## State Machine

```mermaid
stateDiagram-v2
    [*] --> pending: karyawan submit
    pending --> approved: admin approve
    pending --> rejected: admin reject (note wajib 3..500)
    approved --> [*]
    rejected --> [*]
```

## Alur Admin

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/love atau /admin/love]
        A2[/Filter status: pending, approved, rejected + cari nama/]
        A3{Aksi?}
        A4[Klik Approve]
        A5[ConfirmDialog warning:<br/>Setujui klaim toleransi ini?]
        A6{Yakin?}
        A7[Klik Reject]
        A8[/Isi note alasan 3..500 char/]
        A9[Klik Kirim reject]
        A10[Klik Hapus]
        A11[ConfirmDialog danger:<br/>Hapus klaim toleransi?]
        A12{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[LoveController::index<br/>AdminPresenter::lovesFor scope + settings + approvers]
        S2{Region_id klaim = scope user?}
        S3[403 Forbidden]
        S4{status = pending?}
        S5[[Flash error: Hanya pending bisa di-approve/ditolak]]
        S6[status = approved]
        S7[ToleranceClaim::save]
        S8[[Flash success: Toleransi disetujui]]
        S9[Validate note 3..500]
        S10[status = rejected, note tersimpan]
        S11[[Flash success: Toleransi ditolak]]
        S12[ToleranceClaim::delete]
        S13[[Flash success: Klaim toleransi dihapus]]
    end

    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 -->|Approve| A4
    A4 --> A5
    A5 --> A6
    A6 -->|Tidak| End((Batal))
    A6 -->|Ya| S2
    S2 -->|Tidak| S3
    S2 -->|Ya| S4
    S4 -->|Tidak| S5
    S4 -->|Ya| S6
    S6 --> S7
    S7 --> S8
    S8 --> End
    A3 -->|Reject| A7
    A7 --> A8
    A8 --> A9
    A9 --> S2
    S2 -->|Ya reject| S4
    S4 -->|Ya reject| S9
    S9 --> S10
    S10 --> S7
    S7 --> S11
    S11 --> End
    A3 -->|Hapus| A10
    A10 --> A11
    A11 --> A12
    A12 -->|Tidak| End
    A12 -->|Ya| S2
    S2 -->|Ya delete| S12
    S12 --> S13
    S13 --> End
    S3 --> End
    S5 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Approve klaim tidak otomatis membuat entri attendance dengan `status = excused_love`; itu masih manual admin atau bisa dipertimbangkan sebagai peningkatan masa depan.
- Note reject minimal 3 karakter, maks 500. Validasi di backend + frontend UI.
- Kuota `love_max` dikonfigurasi di `AttendanceSetting` (default 4/bulan). Berlaku bulan depan setelah diubah.
