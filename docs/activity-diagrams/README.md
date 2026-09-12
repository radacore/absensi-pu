# Activity Diagrams — BBWS Pompengan Jeneberang

Diagram alur (activity diagram) semua fitur inti aplikasi absensi dan manajemen wilayah BBWS Pompengan Jeneberang. Ditulis dengan Mermaid `flowchart TD` yang di-render otomatis di GitHub.

Setiap diagram memakai `subgraph` sebagai swimlane manual untuk memisahkan aktor (Karyawan, Sistem, Admin Wilayah, Super Admin) sehingga alur lintas peran terbaca jelas.

## Index

### Autentikasi
- [`01-login-admin.md`](./01-login-admin.md) — Super Admin dan Admin Wilayah masuk lewat email + kata sandi.
- [`02-login-karyawan.md`](./02-login-karyawan.md) — Karyawan masuk pakai NIP atau NIK.
- [`03-logout.md`](./03-logout.md) — Alur keluar untuk semua peran.

### Master Data (Admin)
- [`04-regions-crud.md`](./04-regions-crud.md) — Kelola 24 kantor wilayah dan titik proyek.
- [`05-sites-crud.md`](./05-sites-crud.md) — Tambah, ubah, pindah anggota, hapus titik.
- [`06-employees-crud.md`](./06-employees-crud.md) — CRUD karyawan dengan penugasan titik.
- [`07-admin-wilayah-crud.md`](./07-admin-wilayah-crud.md) — Kelola akun Admin Wilayah oleh Super Admin.

### Absensi
- [`08-clock-in.md`](./08-clock-in.md) — Absen masuk dengan verifikasi radius titik.
- [`09-clock-out.md`](./09-clock-out.md) — Absen pulang dan penutupan hari.
- [`10-rekap-kehadiran.md`](./10-rekap-kehadiran.md) — Rekap bulanan karyawan.

### Cuti Berjenjang
- [`11-cuti-karyawan-ajukan.md`](./11-cuti-karyawan-ajukan.md) — Pengajuan cuti dan pembatalan oleh karyawan.
- [`12-cuti-approval-3-level.md`](./12-cuti-approval-3-level.md) — Alur persetujuan cuti berjenjang tiga tahap.

### Toleransi (Lupa Absen)
- [`13-toleransi-karyawan-klaim.md`](./13-toleransi-karyawan-klaim.md) — Klaim lupa absen datang atau pulang oleh karyawan.
- [`14-toleransi-approval.md`](./14-toleransi-approval.md) — Approval dan penolakan klaim toleransi oleh Admin.

### Pengumuman
- [`15-pengumuman-admin.md`](./15-pengumuman-admin.md) — Publikasi pengumuman Global dan Wilayah.
- [`16-pengumuman-karyawan.md`](./16-pengumuman-karyawan.md) — Karyawan membaca dan menandai pengumuman.

### Password
- [`17-reset-password-admin.md`](./17-reset-password-admin.md) — Admin mereset kata sandi karyawan ke NIK.
- [`18-ganti-password-karyawan.md`](./18-ganti-password-karyawan.md) — Karyawan mengganti kata sandi setelah reset.

### Lain-lain
- [`19-profil-karyawan.md`](./19-profil-karyawan.md) — Update phone, email, foto profil oleh karyawan.
- [`20-settings-admin.md`](./20-settings-admin.md) — Pengaturan absensi (Super Admin only).
- [`21-dashboard-admin.md`](./21-dashboard-admin.md) — Ringkasan aktivitas terbaru per wilayah.
- [`22-admin-attendances.md`](./22-admin-attendances.md) — Admin lihat, filter, ekspor CSV, dan hapus kehadiran.
- [`23-karyawan-dashboard.md`](./23-karyawan-dashboard.md) — Home karyawan dengan sapaan, kuota, dan quick action.
- [`24-pwa-install-offline.md`](./24-pwa-install-offline.md) — Install PWA ke home screen dan alur offline service worker.

## Konvensi

- **Bentuk node**
  - `[Aksi]` untuk aktivitas biasa
  - `{Keputusan?}` untuk percabangan
  - `((Mulai))` dan `((Selesai))` untuk terminator
  - `[/Input pengguna/]` untuk input
  - `[[Notifikasi toast]]` untuk feedback UI
- **Swimlane** dipisah lewat `subgraph` dengan warna via `classDef`.
- **Label edge** memakai `-->|kondisi|` untuk mempertegas percabangan.
- **Warna aksen** mengikuti brand: navy `#1E3A8A` untuk sukses, gold `#FCB833` untuk aksi kritikal.

## Cara render lokal

Semua editor Markdown modern mendukung Mermaid. GitHub merender otomatis. Untuk preview di VS Code, pasang extension "Markdown Preview Mermaid Support".

Untuk export ke PNG atau SVG:

```bash
npx @mermaid-js/mermaid-cli -i docs/activity-diagrams/08-clock-in.md -o clock-in.png
```
