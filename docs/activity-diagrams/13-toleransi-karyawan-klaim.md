# 13 — Toleransi Karyawan Klaim (Lupa Absen)

Alur karyawan mengajukan klaim toleransi karena lupa absen datang atau lupa absen pulang. Batas maksimal 4 klaim per bulan (default `love_max = 4`), tidak boleh weekend, dan hanya untuk bulan berjalan.

## Aktor
- **Karyawan** — pemohon klaim.
- **Sistem** — `Karyawan\LoveController` + `AdminPresenter::loveQuota` + `AdminPresenter::approversFor`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka /karyawan/love"]:::user
    S1["2. Load claims + loveMax<br/>+ loveQuota + approvers"]:::sys
    A2["3. Lihat sisa kuota X/4<br/>+ riwayat klaim"]:::user
    D1{"Sisa kuota > 0?"}:::dec
    E0["INFO<br/>Tombol Ajukan disabled<br/>Kuota habis"]:::err

    A3["4. Klik Ajukan Toleransi"]:::user
    A4["5. Pilih jenis, tanggal,<br/>jam, alasan, approver"]:::user
    A5["6. Klik Kirim"]:::user
    S2["7. Validate input<br/>jenis, tgl, jam, alasan, approver_id"]:::sys

    D2{"Punya site_id?"}:::dec
    E1["ERROR<br/>Titik belum di-assign"]:::err

    D3{"Tanggal weekend?"}:::dec
    E2["ERROR<br/>Tidak boleh weekend"]:::err

    D4{"Bulan sama<br/>Asia/Makassar?"}:::dec
    E3["ERROR<br/>Hanya bulan sama"]:::err

    D5{"loveQuota sisa > 0?"}:::dec
    E4["ERROR<br/>Sisa 0 - reset bulan depan"]:::err

    D6{"approver_id valid<br/>dari approversFor?"}:::dec
    E5["ERROR<br/>Atasan tidak valid"]:::err

    S3["8. ToleranceClaim::create<br/>status=pending, region_id + site_id auto"]:::sys
    OK["SUKSES<br/>Toleransi diajukan<br/>menunggu persetujuan"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1
    D1 -->|tidak| E0
    E0 --> END

    D1 -->|ya| A3
    A3 --> A4
    A4 --> A5
    A5 --> S2
    S2 --> D2
    D2 -->|tidak| E1
    D2 -->|ya| D3
    D3 -->|ya| E2
    D3 -->|tidak| D4
    D4 -->|tidak| E3
    D4 -->|ya| D5
    D5 -->|tidak| E4
    D5 -->|ya| D6
    D6 -->|tidak| E5
    D6 -->|ya| S3
    S3 --> OK
    OK --> END

    E1 -->|ulangi| A4
    E2 -->|ulangi| A4
    E3 -->|ulangi| A4
    E4 --> END
    E5 -->|ulangi| A4

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
| Merah muda | Jalur error / info kuota habis |
| Hijau muda | Hasil sukses |

## Langkah-Langkah Detail

1. Karyawan membuka `/karyawan/love`.
2. `LoveController::index` load daftar klaim milik karyawan, `loveMax`, sisa `loveQuota`, dan daftar `approvers` yang valid.
3. UI menampilkan sisa kuota `X/4` dan riwayat klaim.
4. Kalau kuota habis, tombol Ajukan disabled dan menampilkan info "Sisa Toleransi 0 - reset bulan depan".
5. Karyawan menekan Ajukan Toleransi. Form terbuka dengan field: jenis (`lupa_absen` atau `lupa_pulang`), tanggal (weekday, bulan berjalan), jam `HH:MM`, alasan minimal 5 char, pilih atasan (Admin Wilayah dari `approversFor`).
6. Karyawan menekan Kirim.
7. Sistem validasi payload: `jenis` enum, `tgl` `before_or_equal:today`, `jam` format `HH:MM`, `alasan` `5..1000`, `approver_id` present.
8. Kalau semua guard lolos, `ToleranceClaim::create` dengan `status = pending`, `region_id` + `site_id` otomatis dari data karyawan.

### Guard yang dicek server (berurutan)

- **D2** `site_id` sudah di-assign — kalau belum → error "Titik belum di-assign".
- **D3** Tanggal bukan Sabtu/Minggu — weekend ditolak.
- **D4** Bulan sama dengan sekarang zona `Asia/Makassar` — mencegah backdated ke bulan lalu.
- **D5** Sisa `loveQuota` > 0 — kalau habis, tolak.
- **D6** `approver_id` termasuk daftar `AdminPresenter::approversFor` untuk karyawan tersebut.

## Catatan implementasi
- Kuota dihitung `love_max - approved - pending` bulan berjalan Asia/Makassar. Pending juga potong kuota supaya tidak spam pengajuan.
- Approver harus salah satu Admin Wilayah `region_id` sama dengan karyawan (bisa juga admin site-spesifik). Detail resolver di `AdminPresenter::approversFor`.
- Weekend ditolak karena Sabtu/Minggu bukan hari kerja — tidak ada absen yang perlu dikompensasi.
- Cek `same-month` pakai timezone `Asia/Makassar`, bukan UTC, supaya karyawan WITA tidak keblokir di midnight rollover.
