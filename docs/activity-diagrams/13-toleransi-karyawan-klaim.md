# 13 — Toleransi Karyawan Klaim (Lupa Absen)

Alur karyawan mengajukan klaim toleransi karena lupa absen datang atau lupa absen pulang. Batas maksimal 4 klaim per bulan (default `love_max = 4`).

## Aktor
- **Karyawan** — pemohon klaim.
- **Sistem** — `Karyawan\LoveController` + `AdminPresenter::loveQuota` + `AdminPresenter::approversFor`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Buka /karyawan/love]
        K2[/Lihat sisa kuota X/4 + riwayat klaim/]
        K3{Sisa kuota > 0?}
        K4[Tombol Ajukan disabled + info kuota habis]
        K5[Klik Ajukan Toleransi]
        K6[/Pilih jenis: lupa_absen atau lupa_pulang<br/>Pilih tanggal (weekday, bulan ini)<br/>Isi jam HH:MM, alasan >=5 char<br/>Pilih atasan (Admin Wilayah)/]
        K7[Klik Kirim]
    end

    subgraph Sistem[Sistem]
        S1[LoveController::index<br/>load claims + settings.loveMax + loveQuota + approvers]
        S2[Validate jenis, tgl before_or_equal today,<br/>jam HH:MM, alasan 5..1000, approver_id]
        S3{Site_id assigned?}
        S4[Error: Titik belum di-assign]
        S5{Tanggal weekend?}
        S6[Error: Tanggal tidak boleh weekend]
        S7{Bulan sama dengan sekarang Asia/Makassar?}
        S8[Error: Klaim hanya untuk bulan sama]
        S9{loveQuota sisa > 0?}
        S10[Error: Sisa Toleransi 0 - reset bulan depan]
        S11{approver_id valid dari approversFor?}
        S12[Error: Atasan tidak valid]
        S13[ToleranceClaim::create<br/>status=pending, region_id + site_id auto]
        S14[[Flash success: Toleransi diajukan - menunggu persetujuan]]
    end

    K1 --> S1
    S1 --> K2
    K2 --> K3
    K3 -->|Tidak| K4
    K4 --> End((Selesai))
    K3 -->|Ya| K5
    K5 --> K6
    K6 --> K7
    K7 --> S2
    S2 --> S3
    S3 -->|Tidak| S4
    S3 -->|Ya| S5
    S5 -->|Ya| S6
    S5 -->|Tidak| S7
    S7 -->|Tidak| S8
    S7 -->|Ya| S9
    S9 -->|Tidak| S10
    S9 -->|Ya| S11
    S11 -->|Tidak| S12
    S11 -->|Ya| S13
    S13 --> S14
    S14 --> End
    S4 --> End
    S6 --> End
    S8 --> End
    S10 --> End
    S12 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Kuota dihitung `love_max - approved - pending` bulan berjalan Asia/Makassar. Pending juga potong kuota supaya tidak spam.
- Approver harus salah satu Admin Wilayah `region_id` sama dengan karyawan (bisa juga admin site-spesifik). Detail di `AdminPresenter::approversFor`.
- Weekend tolak karena hari libur sudah tidak dihitung sebagai hari kerja.
- `same-month` check pakai timezone Asia/Makassar bukan UTC.
