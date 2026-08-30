# USERFLOW.md: BBWS Pompengan Jeneberang

## Overview

This document details the primary user flows for BBWS Pompengan Jeneberang, a corporate profile application with a public-facing website, a regional admin dashboard (Super Admin + Admin Wilayah per Kabupaten/Kota), and a mobile PWA for employees. The flows are organized by actor (Public User, Admin Wilayah, Super Admin, Karyawan) and focus on the most critical journeys: public content consumption, regional employee data management, and karyawan HR self-service (absensi GPS+selfie — di luar radius ditolak, Love 4/bulan pakai dokumen dalam radius, cuti berjenjang, pengumuman).  # Public site removed — fokus HR PWA

All flows are built on Laravel 13 (PHP 8.4+) with React 19 and Inertia.js v2 (Vite 7, Tailwind v4, MySQL 8.4 LTS, PWA), ensuring a seamless, modern user experience across admin and karyawan PWA interfaces. Region isolation: Admin Wilayah write own region only (read all) — termasuk **N titik proyek per wilayah** (write own, read all); Karyawan own-data-only, absen valid ke titik proyek terdekat di wilayahnya.

---

## Flow 3: Administrator – Login to Admin Dashboard (Opsi B: /super-admin vs /wilayah)

### Trigger
The administrator navigates to their **role-specific obfuscated login URL** — **Super Admin** → `SUPER_ADMIN_PATH/login` (dev `/super-admin/login`) atau **Admin Wilayah** → `WILAYAH_PATH/login` (dev `/wilayah/login`). Keduanya terpisah, tidak cross-login.

### Pre-conditions
- The admin login page is deployed and accessible.
- The administrator has valid credentials (username/email and password) configured in the database.
- The admin account is active (not disabled).

### Post-conditions
- The administrator is authenticated and logged in.
- A session is created and stored in the session store (Laravel Sanctum).
- The administrator is redirected to the main admin dashboard.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator (Super Admin / Wilayah) | Navigates to `SUPER_ADMIN_PATH/login` (Super Admin) atau `WILAYAH_PATH/login` (Wilayah) | Laravel renders login page via Inertia.js sesuai guard — title "Super Admin Pusat" vs "Admin Wilayah". URL salah role (mis. Super Admin ke `/wilayah/login`) tetap render tapi login akan 403 role mismatch. | If URL salah / not configured, 404. |
| 2 | Administrator | Enters email and password | React validates required + email format; login enabled. | If validation fails, error shown. |
| 3 | Administrator | Clicks the "Login" button | POST ke `/api/super-admin/login` (guard super_admin) atau `/api/wilayah/login` (guard wilayah) + CSRF, **rate limit terpisah** 5/15min per IP per guard. | CSRF invalid → 419. |
| 4 | System | Validates credentials + role sesuai URL | Laravel cek `users` where email + role sesuai guard (`super_admin` untuk SUPER_ADMIN_PATH, `admin_wilayah` untuk WILAYAH_PATH), bcrypt verify, buat Sanctum session per guard (tidak cross-login). | Not found / wrong password → 401 generic "Invalid credentials". Role mismatch → 403. Rate limited → 429. |
| 5 | System | Returns success + HttpOnly session cookie (per guard) | 200 + session cookie khusus guard. | N/A |
| 6 | Administrator | Redirected to dashboard role-scoped | Inertia redirect ke `/super-admin` (Super Admin) atau `/wilayah` (Admin Wilayah) — masing-masing layout & menu sesuai guard. | Redirect fail → stay login + error. |

---

## Flow 13: Karyawan – Login via Email (Mobile PWA, 1 Karyawan = 1 Titik)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan | Buka `/karyawan/login` (PWA, obfuscated `KARYAWAN_PATH`) | Render login **email+password** (React PWA 320px+, all roles email) — guard `karyawan` |
| 2 | Karyawan | Input **email** + password, tap Login | POST `/api/karyawan/login` with CSRF, rate limit **5/15min per IP + per email** (email UK) |
| 3 | System | Validate email exists, bcrypt check, create Sanctum karyawan session with **`region_id` + `office_location_id` (assigned site, NULL=belum assign)** | Set HttpOnly cookie, return employee + region + **`office_location` (titik assigned ex 201 Bendungan Bili-Bili — Gowa 200m, or NULL)** via `loadRegions/loadEmployees` sync (`_shared.js` `DUMMY_REGIONS` 24 regions `101/102/201/202/301...` + `DUMMY_EMPLOYEES` 1=1 mapping ex Andi 201/Siti 101/Budi 202, Rina null) |
| 4 | System | Redirect to `/karyawan` dashboard (PWA) | Show bottom nav: Home, Absensi, Cuti, Pengumuman, Profil — **greeting badge `Ditugaskan di: Bendungan Bili-Bili — Kab. Gowa`** atau warning `Belum di-assign titik — hubungi Admin Wilayah` if NULL (absen/Love disabled) |

## Flow 14: Karyawan – Absensi GPS + Selfie (Geofence Titik Assigned — 1 Karyawan = 1 Titik)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan | Lihat Dashboard PWA: greeting + header `Ditugaskan di: Bendungan Bili-Bili — Kab. Gowa lat/lng • radius 200m` (or warning 422 tanpa titik) | Assigned sync `MOCK_KARYAWAN_ID=1` Andi Saputra site 201 via `loadRegions` — Profil card titik + Love sisa/max |
| 2 | Karyawan | Tap "Absen Masuk" di `Karyawan/Absensi.jsx` | Banner assigned `lat/lng • radius`, request GPS + Camera permission (explain UX) |
| 3 | Karyawan | GPS captured + selfie taken | Preview **`jarak/radius` ke titik assigned** (ex 48/200m) + badge **`Dalam/Di luar radius titik assigned`** (`GeofenceService::isWithinAssignedSite`, bukan `isWithinAnySite`), buttons **disabled `!inRadius \|\| tanpaTitik`** |
| 4 | Karyawan | Tap "Kirim Absensi" (enabled only if `inRadius && !tanpaTitik`) | POST `/api/karyawan/attendances` multipart (lat,lng,selfie) — jika `office_location_id IS NULL` → **422 "Belum di-assign titik"**; jika `distance > radius_m(assigned)` → **422 "Di luar radius titik assigned"** (tidak tercatat, `isWithinAssignedSite` only) |
| 5 | System | Validate `isWithinAssignedSite(lat,lng, assigned OffLoc)` server-side — Haversine `dist <= radius_m(assigned)`; `NULL assigned` 422; `!within` 422; else jam global `07:30–16:00` WITA toleransi 15m + fake GPS heuristic | Upload selfie to S3 `/attendance/{region}/{employee}/{date}/` (hanya jika dalam assigned), insert attendances with `office_location_id=assigned.id` (ex 201), status on_time/late/early_leave, `distance_m`; di luar assigned / tanpa titik ditolak 422 (tidak tercatat) |
| 6 | System | Return status + `distance_m` + `radius_m` + titik assigned nama (Bendungan Bili-Bili) + badge Dalam | PWA shows toast + updates history (`jarak/radius` per assigned), caches offline queue if offline — server re-validates `isWithinAssignedSite` + jam on sync (server timestamp) |

## Flow 15: Admin Wilayah – Input Karyawan Lengkap HR (Region-Scoped, 1 Karyawan = 1 Titik)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Admin Wilayah | Login via WILAYAH_PATH (`/wilayah/login`), navigate **"Kelola Karyawan"** (`Admin/Employees.jsx` per titik) | List filtered to **own region** (`region_id==OWN_REGION`/session `regionId`), **kolom+filter `Titik Proyek` (`office_location_id` select)** — ex Andi Saputra Bendungan Bili-Bili (201), Rina Tanpa Titik (`__null`), toggle "Lihat semua (read-only)" for other regions |
| 2 | Admin | Tap "Tambah Karyawan" | Render form Lengkap HR: NIK, NIP, nama, golongan, jabatan, unit_kerja, status, foto, kontak **+ select `Titik Proyek` (`office_location_id` 1 karyawan=1 titik, NULL=belum assign)** |
| 3 | Admin | Submit (ex assign ke 201 Bendungan Bili-Bili) | POST `/api/wilayah/employees` — middleware injects own `region_id`, validates NIK unique, **titik `regionId` match own region** (cross-region 403), uploads foto S3 |
| 4 | System | Insert employees with `region_id` + **`office_location_id` (FK, NULLABLE — SetNull on delete titik)**, audit log | Return 201, LS `_shared.js` `bbws_mock_employees_v3` sync, list refresh, breakdown per titik update |

## Flow 15b: Admin Wilayah – Kelola N Titik Proyek per Wilayah (Bendungan A, Jembatan B — Dedicated Page per Titik, Anggota per Titik)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Admin Wilayah (Kab. Gowa `OWN_REGION`) | Login via `WILAYAH_PATH/login` (`/wilayah/login`), navigasi **Dashboard** (breakdown per Titik cards `anggota`/`lat/lng•radius`/`Kelola` Link `/wilayah/regions/{region}/sites/{site}`) + **"Kantor / Titik Proyek"** (`Admin/Regions.jsx`) | Dashboard auto `activeRegion` from `wilayah`/`OWN_REGION`, `siteFilter` header `Semua titik` + breakdown `countsPerSite` + `totalTanpaTitik`; Regions list 24 wilayah; card **Kab. Gowa** expand N titik (Bendungan Bili-Bili 201 200m, ...), wilayah lain badge "Read-only", **Kelola → dedicated `GET /regions/{region}/sites/{site}` per titik** (`super_admin|admin|wilayah.sites.show` `curl 200` untuk `101/102/201/202/301`) |
| 2 | Admin Wilayah | Tap **Kelola** di Bendungan Bili-Bili → masuk **dedicated `SiteDetail`** (`Admin/SiteDetail.jsx` via `getAdminBase(url)`) | Leaflet draggable marker+circle per titik + form edit titik (nama/lat/lng/radius 50–1000/address), **anggota per titik saja** (`office_location_id==201`, 1 karyawan=1 titik, list Andi Saputra), select **kandidatTambah hanya `regionId==gowa && office_location_id==null`** (ex Rina), aksi **Tambah/Pindah** per titik saja |
| 3 | Admin Wilayah | Tap "Tambah Titik Proyek" di wilayahnya | Form: **nama titik** (ex Jembatan Pampang), **lat/lng via Leaflet map picker**, **radius 50–1000m slider N≤20**, alamat opsional, hapus last site blocked 422 |
| 4 | Admin Wilayah | Isi: nama=Jembatan Pampang, lat/lng pick di peta, radius=150m, submit | `POST /api/wilayah/regions/{gowaId}/office-locations` — guard `wilayah`, region scope own only, validasi radius 50–1000 + `lat/lng` range + N≤20 |
| 5 | System | Validasi: own region, lat/lng range, radius range, N≤20, **minimal 1 titik** terpenuhi | Insert `office_locations` (region_id=gowa, nama_lokasi=Jembatan Pampang, lat/lng/radius), return 201, `_shared.js` LS sync |
| 6 | System | Invalidate geofence cache `region:{id}:sites`, refresh list + Dashboard breakdown update | UI tampil 2 titik di Kab. Gowa (Bendungan + Jembatan) cards update `anggota`; toast "Titik Jembatan Pampang ditambahkan" |
| 7 | Admin Wilayah | Edit titik: di SiteDetail Bendungan Bili-Bili drag marker + ubah radius 200→300, submit | `PUT /api/wilayah/regions/{gowaId}/office-locations/{id}` — update `lat/lng/radius`, invalidate cache, circle radius update |
| 8 | Admin Wilayah | Coba hapus titik terakhir (hanya 1 tersisa) atau `office_location_id` masih ada anggota | `DELETE /.../office-locations/{id}` → **422 "Minimal 1 titik per wilayah"** (blocked) atau unassign dulu (SetNull) — list tetap 1 titik |
| 9 | Super Admin | Login `/super-admin/login` → Dashboard + "Kelola Kantor/Wilayah" → lihat 24 wilayah + semua N titik + attendances/cuti/love per titik | Dapat tambah/edit/hapus/pindah titik di wilayah manapun (unscoped); Admin Wilayah view wilayah lain: read-only |
| 10 | Karyawan Gowa Andi Saputra (assigned 201) | Absen: GPS **45m dari titik assigned Bendungan Bili-Bili 200m** (bukan ke Jembatan) | System `GeofenceService::isWithinAssignedSite(45 <= 200)` → **on_time/late valid**, simpan `office_location_id=201` + `distance_m=45` + badge `Dalam radius titik assigned`; jika ke Jembatan 45m tapi assigned Bendungan 800m → **422 ditolak (bukan `isWithinAnySite`)** |
| 11 | Admin Wilayah | Lihat **Attendances** filtered `Wilayah=Gowa → Titik=Bendungan Bili-Bili` (or `Cuti/Love` per titik) | Table kolom `Titik Proyek` Link `GET /regions/.../sites/201` + badge `Dalam/Di luar` + `Jarak`; detail drawer link titik + S3 `/attendance/{region}/{employee}/{date}` + jam `07:30–16:00` — Love Approve disabled `sisa==0 \|\| !inRadius(assigned)` |

## Flow 16–18: Karyawan – Cuti Berjenjang / Pengumuman

- **Cuti:** Karyawan ajukan (jenis, tgl, alasan, dokumen) → status pending → Admin Wilayah approves level2 → Super Admin final approve → notifications + timeline UI. Any level can reject.
- **Pengumuman:** Super Admin broadcast global OR Admin Wilayah targeted region → Karyawan inbox shows global + own region, read/unread via announcement_reads, mark read on tap, pinned on top.

### Additional Flows for Karyawan & Admin Wilayah (NEW) — Per Titik (1 Karyawan = 1 Titik)

## Flow 13: Karyawan – Love Claim (4 Hati, Dalam Radius Titik Assigned, 1 Level)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan Andi (assigned 201 Bendungan Bili-Bili 200m) | Late 07:52 (`distance=12m` **dari titik assigned 201**, `status=late`), lihat badge late di Rekap + card Love | PWA `Love.jsx` tampilkan 4 dot gold `#FCB833` `3/4 sisa`, jarak `12/200m` + badge `Dalam radius titik assigned` + tombol **"Gunakan Love" aktif** (`inRadius && sisa>0 && !tanpaTitik`); `tanpaTitik` → warning 422 disabled |
| 2 | Karyawan | Tap "Gunakan Love" → isi alasan + upload dokumen (**bulan yang sama**, hari beda boleh — `2026-03 late` claim `2026-03`, bukan `00:00–23:59 WITA` hari yang sama), submit | POST `/api/karyawan/love-claims` (attendance_id, alasan, dokumen) — validasi: own attendance, `status=late`, **`isWithinAssignedSite(12<=200)` assigned only (bukan any site)**, bulan sama, `love_sisa>0`, `office_location_id!=null`; di luar assigned / tanpa titik → **422 disabled (absen ditolak 422 → tidak ada claim)** |
| 3 | System | Validate & upload dokumen S3 `/love-claims/{region}/{employee}/{uuid}.pdf` + insert pending | Return pending, `love_sisa` masih `3` (belum deduct `love_max` 4) |
| 4 | Admin Wilayah Gowa | Lihat queue **Love Claims pending own region** filtered `Titik Proyek = Bendungan Bili-Bili` (filter `Wilayah→Titik→Status`), kolom `Titik Proyek` link `GET /regions/.../sites/201` + badge `Dalam/Di luar` + `jarak/radius`, review dokumen | Tap **Approve** (enabled only `inRadius && sisa>0`) → `love_sisa 3→2`, attendance `late→excused_love` (rekap jadi on_time), claim approved; Reject → tetap `late`; `!inRadius` → Approve disabled 403 |
| 5 | System | Notifikasi ke karyawan: Love disetujui, rekap late jadi excused | PWA update dot `2/4` sisa, history claim approved + header titik assigned tetap |

> **Per-titik gate:** Di luar radius **titik assigned** (1=1, via `isWithinAssignedSite`, bukan `isWithinAnySite`) **atau tanpa titik (`NULL`)** → tombol Gunakan Love **disabled**, tidak ada claim, absen ditolak **422** (cek `distance > radius_m(assigned)` atau `office_location_id IS NULL`). Admin Approve juga disabled `!inRadius \|\| sisa==0`.

---

## Summary of Key User Flows

BBWS Pompengan Jeneberang supports four primary actor types:

### Super Admin Pusat Flows
 - **Manage Regions & N Titik Proyek:** CRUD Kabupaten/Kota + **N titik proyek per wilayah** (nama/lat/lng/radius per titik) + geofence, CRUD admin wilayah accounts, assign region.
- **Global Content & Broadcast:** All content + global pengumuman + all region data + final cuti approval.

### Admin Wilayah (Kabupaten/Kota) Flows
- **Employee Data (Lengkap HR):** CRUD own region employees (read all, write own), foto S3, NIK validation, audit.
 - **Titik Proyek:** **Tambah/edit/hapus N titik proyek di wilayah sendiri** (Bendungan A, Jembatan B — lat/lng Leaflet + radius 50–1000), view wilayah lain read-only.
 - **HR Operations:** View own region attendances (label titik terdekat), approve cuti level2, kirim pengumuman wilayah.
- **Content:** As per role, manage public content (if allowed).

### Karyawan (Mobile PWA) Flows
- **Auth:** NIK+password login, PWA install, own-data-only.
- **Profile:** View own Lengkap HR, edit foto/kontak/password limited.
- **Absensi:** GPS+selfie geofenced, offline queue, history.
- **Cuti:** Ajukan + track berjenjang (pending → level1 → level2 → approved/rejected).
- **:** View list + detail own-only.
- **Pengumuman:** Inbox global+region, read/unread, attachment.

All flows leverage Inertia.js v2 for seamless navigation, React 19 + Tailwind v4 + PWA (manifest+SW) for mobile-first (320px+, 44px tap), and server-side region isolation + own-data policies for security.