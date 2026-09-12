# 06 — Employees CRUD (Karyawan)

Alur Admin mengelola data karyawan. Setiap karyawan **wajib** punya `region_id` dan `site_id` (1 karyawan = 1 titik). Password default `password123` untuk karyawan baru.

## Aktor
- **Super Admin** — CRUD karyawan semua wilayah.
- **Admin Wilayah** — CRUD karyawan di wilayahnya saja.
- **Sistem** — `Admin\EmployeeController` + `AdminPresenter::employeesFor($scope)`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    A1["1. Buka halaman employees<br/>/super-admin/employees<br/>atau /admin/employees"]:::user
    S1["2. EmployeeController::index<br/>employeesFor(scope)"]:::sys
    A2["3. Terapkan filter:<br/>wilayah, titik, status,<br/>cari nama/email"]:::user
    D1{"Pilih aksi?"}:::dec

    A3a["4a. Klik + Tambah Karyawan"]:::user
    A3b["4b. Klik Edit baris"]:::user
    A3c["4c. Klik Hapus baris"]:::user
    A3d["4d. Klik Reset password"]:::user

    A4a["5a. Isi form:<br/>NIK 16 digit, NIP, nama,<br/>email, golongan, jabatan,<br/>unit, status, wilayah, titik"]:::user
    A4b["5b. Ubah field karyawan"]:::user
    A4c["5c. ConfirmDialog danger<br/>Hapus + email +<br/>wilayah?"]:::user
    A4d["5d. Lanjut ke diagram 17<br/>Reset Password Admin"]:::user

    A5["6. Submit form"]:::user
    D2{"7. Yakin hapus?"}:::dec

    S2["8. Validasi input:<br/>NIK 16 digit unik,<br/>NIP unik nullable,<br/>email unik"]:::sys
    D3{"9. Wilayah = scope user?<br/>Titik ada di wilayah?"}:::dec

    S3["10a. Employee::create()<br/>password default 'password123'"]:::sys
    S4["10b. Employee::update()"]:::sys
    S5["10c. Employee::delete()"]:::sys

    E1["ERROR<br/>403 Forbidden<br/>scope mismatch"]:::err
    CANCEL["BATAL<br/>Tidak ada perubahan"]:::ok
    OK["SUKSES<br/>Flash sesuai aksi"]:::ok
    END([SELESAI]):::se

    START --> A1
    A1 --> S1
    S1 --> A2
    A2 --> D1

    D1 -->|tambah| A3a
    A3a --> A4a
    A4a --> A5
    A5 --> S2

    D1 -->|edit| A3b
    A3b --> A4b
    A4b --> A5

    D1 -->|hapus| A3c
    A3c --> A4c
    A4c --> D2
    D2 -->|tidak| CANCEL
    D2 -->|ya| D3

    D1 -->|reset| A3d
    A3d --> A4d
    A4d --> END

    S2 --> D3
    D3 -->|tidak| E1
    D3 -->|ya create| S3
    D3 -->|ya update| S4
    D3 -->|ya delete| S5

    S3 --> OK
    S4 --> OK
    S5 --> OK
    OK --> END
    CANCEL --> END
    E1 --> END

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
| Biru muda | Aksi Aktor (Admin) |
| Abu-abu putih | Proses Sistem |
| Kuning | Keputusan (kondisi if/else) |
| Merah muda | Jalur error |
| Hijau muda | Hasil sukses / batal |

## Langkah-Langkah Detail

1. Admin membuka halaman daftar karyawan.
2. `EmployeeController::index` memanggil `AdminPresenter::employeesFor($scope)` — Admin Wilayah otomatis di-scope ke `region_id` sendiri.
3. Admin bisa memfilter berdasarkan wilayah, titik proyek, status kepegawaian, atau cari nama/email.
4. Admin memilih salah satu aksi:
   - **4a.** Tambah karyawan baru.
   - **4b.** Edit karyawan existing.
   - **4c.** Hapus karyawan.
   - **4d.** Reset password ke NIK (lanjut ke diagram 17).
5. Admin mengisi form atau melihat konfirmasi sesuai aksi:
   - **5a.** Form create dengan semua field wajib (NIK, NIP, nama, email, golongan, jabatan, unit, status, wilayah, titik). Field `foto_url` opsional.
   - **5b.** Form edit dengan field yang sudah terisi.
   - **5c.** `ConfirmDialog` tone danger menampilkan nama + email + wilayah karyawan.
   - **5d.** Redirect ke alur diagram 17.
6. Admin submit form.
7. Khusus hapus: tunggu konfirmasi user.
8. Sistem validasi: NIK harus 16 digit unik, NIP unik nullable, email unik.
9. Sistem cek otorisasi: wilayah target harus sesuai scope user, dan titik yang dipilih harus berada di wilayah tersebut.
10. Sistem eksekusi CRUD:
    - **10a.** `Employee::create()` dengan password default `password123` (bcrypt).
    - **10b.** `Employee::update()`.
    - **10c.** `Employee::delete()`.

## Catatan implementasi
- Karyawan baru mendapatkan password default `password123`. Admin bisa langsung reset ke NIK via tombol Reset — lihat [`17-reset-password-admin.md`](./17-reset-password-admin.md).
- Filter di halaman: wilayah, titik proyek, status kepegawaian, cari nama/email.
- Field `foto_url` opsional; kalau tidak ada, UI pakai avatar default.
- Admin Wilayah hanya lihat karyawan di wilayahnya (lewat `AdminPresenter::employeesFor($user->region_id)`).
- Validasi titik: sistem memastikan `site_id` yang dipilih benar-benar berada di `region_id` target.
