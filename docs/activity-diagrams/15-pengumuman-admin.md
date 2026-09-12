# 15 — Pengumuman Admin (CRUD)

Alur Admin membuat, mengedit, dan menghapus pengumuman. Scope `Global` hanya bisa dibuat oleh Super Admin; `Wilayah` bisa oleh Admin Wilayah (dipaksa ke region-nya).

## Aktor
- **Super Admin** — CRUD pengumuman scope Global dan Wilayah.
- **Admin Wilayah** — hanya scope Wilayah untuk region-nya sendiri.
- **Sistem** — `Admin\PengumumanController`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> A1

    subgraph Admin[Admin]
        A1[Buka /super-admin/pengumuman atau /admin/pengumuman]
        A2[/Lihat daftar sesuai scope/]
        A3{Aksi?}
        A4[Klik + Buat Pengumuman]
        A5[/Isi judul, konten, scope, region_id opsional, pin/]
        A6[Klik Terbitkan]
        A7[Klik Edit]
        A8[/Ubah field/]
        A9[Klik Simpan]
        A10[Klik Hapus]
        A11[ConfirmDialog danger:<br/>Hapus pengumuman ini?]
        A12{Yakin?}
    end

    subgraph Sistem[Sistem]
        S1[PengumumanController::index<br/>AdminPresenter::announcementsFor scope]
        S2[Validate judul, konten, scope in Global,Wilayah,<br/>region_id exists, pin bool]
        S3{Role admin_wilayah + scope=Global?}
        S4[Error: Admin Wilayah tidak boleh Global]
        S5{Scope=Wilayah + role admin_wilayah?}
        S6[Set region_id = user->region_id paksa]
        S7{Scope=Wilayah + region_id kosong?}
        S8[Error: Pilih wilayah]
        S9{Scope=Global?}
        S10[Set region_id = null]
        S11[Announcement::create dengan created_by = user->id]
        S12[[Flash success: Pengumuman dibuat]]
        S13{Pengumuman lama = Global + user admin_wilayah?}
        S14[403 Tidak bisa edit/hapus Global]
        S15{Region_id pengumuman = user->region_id?}
        S16[403 di luar wilayah Anda]
        S17[Announcement::update]
        S18[Announcement::delete cascade reads]
        S19[[Flash success: Pengumuman diperbarui/dihapus]]
    end

    A1 --> S1
    S1 --> A2
    A2 --> A3
    A3 -->|Buat| A4
    A4 --> A5
    A5 --> A6
    A6 --> S2
    S2 --> S3
    S3 -->|Ya| S4
    S3 -->|Tidak| S5
    S5 -->|Ya| S6
    S6 --> S7
    S5 -->|Tidak| S7
    S7 -->|Ya| S8
    S7 -->|Tidak| S9
    S9 -->|Ya| S10
    S10 --> S11
    S9 -->|Tidak| S11
    S11 --> S12
    S12 --> End((Selesai))
    A3 -->|Edit| A7
    A7 --> A8
    A8 --> A9
    A9 --> S13
    S13 -->|Ya| S14
    S13 -->|Tidak| S15
    S15 -->|Tidak| S16
    S15 -->|Ya| S17
    S17 --> S19
    S19 --> End
    A3 -->|Hapus| A10
    A10 --> A11
    A11 --> A12
    A12 -->|Tidak| End
    A12 -->|Ya| S13
    S13 -->|Ya hapus| S14
    S13 -->|Tidak hapus| S15
    S15 -->|Ya hapus| S18
    S18 --> S19
    S4 --> End
    S8 --> End
    S14 --> End
    S16 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Admin actor
    class Sistem system
```

## Catatan implementasi
- Field `pin` bikin pengumuman muncul di paling atas daftar (baik admin maupun karyawan).
- Admin Wilayah otomatis `region_id` di-paksa ke wilayahnya walau frontend mengirim region_id lain.
- Delete cascade menghapus baris di `announcement_reads` juga.
