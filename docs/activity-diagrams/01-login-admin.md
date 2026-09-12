# 01 — Login Admin (Super Admin dan Admin Wilayah)

Alur autentikasi untuk akun admin. Rute `/super-admin/login` dan `/admin/login` sama-sama diproses oleh `AdminAuthController::store`, yang membedakan adalah role check setelah login sukses.

## Aktor
- **Admin** — Super Admin (role `super_admin`) atau Admin Wilayah (role `admin_wilayah`).
- **Sistem** — Laravel Auth guard `web` + `AdminAuthController`.

## Activity Diagram

![Login Admin Activity Diagram](./img/01-login-admin.png)

Sumber PlantUML: [`src/01-login-admin.puml`](./src/01-login-admin.puml)

## Catatan implementasi
- `AdminAuthController::store` (`app/Http/Controllers/Auth/AdminAuthController.php`) memanggil `Auth::guard('web')->attempt($credentials + ['is_active' => true])` — akun nonaktif otomatis ditolak.
- Session regenerate untuk mitigasi session fixation.
- Role validasi ambil `$isSuper = str_starts_with($request->path(), 'super-admin')` lalu bandingkan dengan `$user->role`. Salah area, logout paksa dan lempar `ValidationException`.
- Toast error dirender oleh `ToastHost` global yang membaca `errors` dari Inertia shared props.

## Cara render ulang
```bash
./docs/activity-diagrams/src/render.sh
```
Butuh `curl`, dijalankan lewat kroki.io (tidak perlu install PlantUML lokal).
