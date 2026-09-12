# 16 — Pengumuman Karyawan (Baca dan Tandai)

Karyawan melihat daftar pengumuman scope `Global` dan wilayahnya sendiri. Badge angka unread muncul di menu Info sidebar dan berkurang otomatis setelah item ditandai baca.

## Aktor
- **Karyawan** — pembaca pengumuman.
- **Sistem** — `Karyawan\PengumumanController` + `AdminPresenter::announcementsForKaryawan` + `HandleInertiaRequests` (share `notifications.unreadAnnouncements`).

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Login karyawan<br/>lihat badge di menu Info"]:::user
    S1["2. Hitung unread:<br/>count Announcement in scope<br/>minus reads"]:::sys

    A2["3. Klik menu Info<br/>/karyawan/pengumuman"]:::user
    S2["4. Load daftar Global +<br/>region sendiri<br/>+ readIds"]:::sys
    A3["5. Pilih aksi<br/>Baca 1 / Tandai semua"]:::user

    D1{"Aksi?"}:::dec

    A4["6a. Klik item<br/>untuk baca"]:::user
    S3["7a. POST /karyawan/pengumuman/<br/>{id}/read"]:::sys
    S4["8a. AnnouncementRead::<br/>firstOrCreate"]:::sys

    A5["6b. Klik Tandai<br/>Semua Dibaca"]:::user
    A6["7b. Konfirmasi<br/>ConfirmDialog"]:::user
    D2{"Yakin?"}:::dec
    S5["8b. POST /karyawan/<br/>pengumuman/read-all"]:::sys
    S6["9b. Loop firstOrCreate<br/>AnnouncementRead per id"]:::sys

    S7["10. Rerender shared:<br/>unreadAnnouncements = 0"]:::sys
    OK["SUKSES<br/>Badge unread berkurang<br/>toast konfirmasi"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> S2
    S2 --> A3
    A3 --> D1

    D1 -->|baca 1| A4
    A4 --> S3
    S3 --> S4
    S4 --> S7

    D1 -->|tandai semua| A5
    A5 --> A6
    A6 --> D2
    D2 -->|tidak| END
    D2 -->|ya| S5
    S5 --> S6
    S6 --> S7

    S7 --> OK
    OK --> END

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

1. Karyawan login, layout Karyawan menampilkan badge angka di menu Info.
2. `HandleInertiaRequests::share` menghitung `notifications.unreadAnnouncements = count(Announcement scope karyawan) - count(reads)`.
3. Karyawan klik menu Info menuju `/karyawan/pengumuman`.
4. `PengumumanController::index` memuat daftar scope karyawan (`Global` OR `region_id = user->region_id`) beserta `readIds`.
5. Karyawan memilih aksi: baca satu item atau tandai semua sekaligus.
6. (a) Klik satu item pengumuman untuk membacanya. (b) Klik tombol Tandai Semua Dibaca.
7. (a) POST ke `/karyawan/pengumuman/{id}/read`. (b) Konfirmasi lewat `ConfirmDialog` sebelum eksekusi.
8. (a) `AnnouncementRead::firstOrCreate(['announcement_id' => $id, 'employee_id' => $me])`. (b) Jika user konfirmasi ya → POST `/karyawan/pengumuman/read-all`.
9. Untuk read-all: loop semua pengumuman in-scope dan `firstOrCreate` `AnnouncementRead` per id.
10. Inertia rerender shared props `notifications.unreadAnnouncements` (biasanya jadi 0 setelah tandai semua).

## Catatan implementasi
- Controller: `app/Http/Controllers/Karyawan/PengumumanController.php`.
- Badge angka di sidebar `resources/js/Layouts/KaryawanLayout.jsx` mengambil `props.notifications.unreadAnnouncements` (di-share via `HandleInertiaRequests`).
- Operasi idempoten: `firstOrCreate` mencegah duplicate row read walau user klik ulang.
- Scope karyawan konsisten: `where scope = 'Global' OR region_id = user->region_id`.
- Setelah `back()` dari Inertia, shared props di-refresh otomatis sehingga badge sinkron tanpa reload penuh.
