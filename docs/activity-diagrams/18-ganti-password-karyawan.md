# 18 — Ganti Password Karyawan setelah Reset

Karyawan login pakai NIK, melihat banner peringatan, dan mengganti password dengan aturan minimal 8 karakter + huruf besar+kecil+angka + tidak sama NIK.

## Aktor
- **Karyawan** — akun dengan `must_change_password = true`.
- **Sistem** — `Karyawan\ProfilController::updatePassword` + `HandleInertiaRequests`.

## Alur

```mermaid
flowchart TD
    Start((Mulai)) --> K1

    subgraph Karyawan[Karyawan]
        K1[Login pakai NIP/NIK dan NIK sebagai password]
        K2[/Lihat dashboard dengan banner kuning:<br/>Kata sandi Anda masih NIK, Ganti sekarang/]
        K3[Klik Ganti sekarang]
        K4[Halaman /karyawan/profil section Kata sandi]
        K5[/Isi current_password = NIK<br/>password baru<br/>konfirmasi/]
        K6[Klik Simpan kata sandi]
        K10[/Toast success: Kata sandi berhasil diperbarui/]
        K11[Banner kuning hilang otomatis]
    end

    subgraph Sistem[Sistem]
        S1[HandleInertiaRequests::share<br/>auth.employee.must_change_password = true]
        S2[Layout render banner kuning + CTA]
        S3[PUT /karyawan/profil/password]
        S4[Validate current_password required,<br/>password min 8 + regex A-Z + a-z + digit + confirmed]
        S5{Hash::check current_password?}
        S6[422 Kata sandi lama salah]
        S7{password baru == NIK?}
        S8[422 Kata sandi baru tidak boleh sama dengan NIK]
        S9[employee->password = new (auto bcrypt via cast)]
        S10[employee->must_change_password = false]
        S11[employee->save]
        S12[[Flash success: Kata sandi berhasil diperbarui]]
        S13[Rerender: auth.employee.must_change_password = false]
    end

    K1 --> S1
    S1 --> S2
    S2 --> K2
    K2 --> K3
    K3 --> K4
    K4 --> K5
    K5 --> K6
    K6 --> S3
    S3 --> S4
    S4 --> S5
    S5 -->|Tidak| S6
    S5 -->|Ya| S7
    S7 -->|Ya| S8
    S7 -->|Tidak| S9
    S9 --> S10
    S10 --> S11
    S11 --> S12
    S12 --> S13
    S13 --> K10
    K10 --> K11
    K11 --> End((Selesai))
    S6 --> End
    S8 --> End

    classDef actor fill:#EFF6FF,stroke:#1E3A8A,stroke-width:1px,color:#0F172A
    classDef system fill:#F1F5F9,stroke:#64748B,stroke-width:1px,color:#0F172A
    classDef warn fill:#FEF3C7,stroke:#F59E0B,stroke-width:1px,color:#92400E
    class Karyawan actor
    class Sistem system
```

## Catatan implementasi
- Banner hanya muncul di layout Karyawan (`resources/js/Layouts/KaryawanLayout.jsx`). CTA "Ganti sekarang" disembunyikan saat sudah di halaman profil untuk hindari redundansi.
- Aturan password ketat: minimal 8, wajib huruf besar + kecil + angka, tidak boleh persis dengan NIK. Divalidasi baik client-side (`toast.error`) maupun server-side.
- Flag `must_change_password` di-mati kan setelah save. Login berikutnya banner tidak muncul lagi.
- Sistem tidak memblokir aksi lain (absen, cuti, dll) — banner hanya peringatan agar user tidak lupa ganti.
