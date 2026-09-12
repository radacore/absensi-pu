# 18 — Ganti Password Karyawan setelah Reset

Karyawan login dengan NIK sebagai password, melihat banner peringatan kuning, lalu mengganti password baru dengan aturan minimal 8 karakter + wajib huruf besar + kecil + angka + tidak sama dengan NIK.

## Aktor
- **Karyawan** — akun dengan flag `must_change_password = true` (biasanya baru direset admin).
- **Sistem** — `Karyawan\ProfilController::updatePassword` + `HandleInertiaRequests`.

## Flowchart

```mermaid
flowchart TD
    START([MULAI]):::se

    K1["1. Login pakai NIP/NIK<br/>password = NIK"]:::user
    S1["2. Share auth.employee.<br/>must_change_password = true"]:::sys
    S2["3. Layout render<br/>banner kuning + CTA"]:::sys

    K2["4. Klik Ganti sekarang<br/>menuju /karyawan/profil"]:::user
    K3["5. Isi form:<br/>current_password = NIK<br/>password baru + konfirmasi"]:::user
    K4["6. Klik Simpan<br/>kata sandi"]:::user

    S3["7. PUT /karyawan/<br/>profil/password"]:::sys
    S4["8. Validate:<br/>min 8, regex A-Z + a-z + digit,<br/>confirmed"]:::sys
    D1{"Current_password<br/>cocok Hash::check?"}:::dec
    E1["ERROR 422<br/>Kata sandi lama salah"]:::err

    D2{"Password baru<br/>== NIK?"}:::dec
    E2["ERROR 422<br/>Tidak boleh sama NIK"]:::err

    S5["9. employee->password = new<br/>auto bcrypt via cast"]:::sys
    S6["10. must_change_password<br/>= false, save"]:::sys
    S7["11. Rerender:<br/>banner kuning hilang"]:::sys

    OK["SUKSES<br/>Toast: Kata sandi<br/>berhasil diperbarui"]:::ok
    END([SELESAI]):::se

    START --> K1
    K1 --> S1
    S1 --> S2
    S2 --> K2
    K2 --> K3
    K3 --> K4
    K4 --> S3
    S3 --> S4
    S4 --> D1
    D1 -->|tidak| E1
    D1 -->|ya| D2
    D2 -->|ya| E2
    D2 -->|tidak| S5
    S5 --> S6
    S6 --> S7
    S7 --> OK
    OK --> END

    E1 -->|ulangi| K3
    E2 -->|ulangi| K3

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

1. Karyawan login pakai NIP/NIK dan NIK sebagai password (karena baru direset admin).
2. `HandleInertiaRequests::share` menaruh `auth.employee.must_change_password = true` di shared props Inertia.
3. Layout Karyawan render banner kuning "Kata sandi Anda masih NIK, Ganti sekarang" + CTA link.
4. Karyawan klik CTA menuju `/karyawan/profil` section Kata sandi.
5. Karyawan mengisi `current_password` (= NIK), `password` baru, dan konfirmasi.
6. Karyawan klik tombol Simpan kata sandi.
7. FE PUT ke `/karyawan/profil/password`.
8. Server validasi: `password` min 8, regex `[A-Z]`, `[a-z]`, `\d`, plus `confirmed` matching field konfirmasi.
9. Cek `Hash::check(current_password, employee->password)` — kalau salah → 422 "Kata sandi lama salah".
10. Cek `password === employee->nik` — kalau ya → 422 "Kata sandi baru tidak boleh sama dengan NIK".
11. Simpan: `employee->password = new` (di-hash otomatis via cast), `must_change_password = false`, `save()`. Rerender banner hilang.

## Catatan implementasi
- Controller: `app/Http/Controllers/Karyawan/ProfilController.php::updatePassword`.
- Banner hanya muncul di layout Karyawan (`resources/js/Layouts/KaryawanLayout.jsx`). CTA "Ganti sekarang" disembunyikan saat user sudah berada di halaman profil untuk hindari redundansi.
- Aturan password ketat divalidasi baik client-side (`toast.error` preview) maupun server-side (source of truth).
- Sistem tidak memblokir aksi lain (absen, cuti, dll) — banner hanya peringatan, bukan hard gate.
- Setelah berhasil, login berikutnya banner tidak muncul lagi.
