# REQUIREMENTS.md: BBWS Pompengan Jeneberang

## 1. Functional Requirements

### 1.2. Admin Dashboard

**FR-10: Secure Authentication (Super Admin & Admin Wilayah — Opsi B Pisah URL)**
The system SHALL provide **dua URL login terpisah**: **Super Admin Pusat** di `SUPER_ADMIN_PATH` (dev: `/super-admin`) dan **Admin Wilayah** di `WILAYAH_PATH` (dev: `/wilayah`), masing-masing session-based auth via Laravel Sanctum 4.x dengan role & region scoping. Ketiga URL (SUPER_ADMIN_PATH, WILAYAH_PATH, KARYAWAN_PATH) di-obfuscate di production via env random hash. Super Admin tidak dapat login via WILAYAH_PATH dan sebaliknya (guard terpisah, 403 jika role mismatch).
*   Admin MUST be able to log in via email + password **di URL-nya masing-masing** (`/super-admin/login` untuk Super Admin, `/wilayah/login` untuk Admin Wilayah). Super Admin manages Admin Wilayah accounts (CRUD, assign region_id) hanya via SUPER_ADMIN_PATH.
*   Invalid attempts SHALL return generic error + rate limiting **terpisah per guard** (super-admin 5/15min, wilayah 5/15min, karyawan 5/15min per IP per guard).
*   System MUST maintain session with `role` + `region_id` in session; `region_id=null` untuk Super Admin (all regions), `region_id=FK` untuk Admin Wilayah; all write queries scoped to own region (Admin Wilayah), Super Admin unscoped. Middleware `role:super_admin` untuk SUPER_ADMIN_PATH dan `role:admin_wilayah` untuk WILAYAH_PATH.

**FR-11: Main Dashboard Overview (Role-Scoped)**
The system SHALL present a role-scoped dashboard: Super Admin sees global analytics + all regions stats; Admin Wilayah sees own region stats (employee count, attendance today, pending cuti) + read-only global stats.
*   Dashboard MUST display metrics scoped by region_id for Admin Wilayah.
*   Section for recent contact submissions + pending leave requests (for Admin Wilayah).
*   Quick links MUST be role-filtered (e.g., Admin Wilayah sees "Kelola Karyawan Wilayah Saya").

**FR-21: Global Settings Management + Jam Kerja Global**
The system SHALL provide an interface to manage site-wide information: Company Name, Logo, Favicon, Contact Email, Phone Number, Address, Social Media Profile links, **and Global Working Hours**.
*   Only Super Admin (Kantor Pusat) MUST be able to update global settings + jam kerja; Admin Wilayah read-only.
*   Jam Kerja Global fields REQUIRED: `jam_masuk` (default 07:30 WITA), `jam_pulang` (default 16:00 WITA), `toleransi_late_menit` (default 15), `hari_kerja` (Senin-Jumat, default 5 hari). Stored in `global_settings` (keys: `jam_masuk`, `jam_pulang`, etc.) or `attendance_settings` table, cached with Redis. Timezone `Asia/Makassar` (WITA).
*   Changes SHALL be immediately reflected: absensi next day uses new jam; PWA shows jam kerja di dashboard home.

### 1.3. Karyawan Mobile PWA (Employee Self-Service) — NEW

**FR-22: Karyawan & Admin Authentication (Email + Password, PWA — Pisah URL)**
The system SHALL provide dedicated login via **email + password untuk semua role** (Super Admin, Admin Wilayah, Karyawan) — bukan NIK. Menggunakan Laravel Sanctum dengan guard sesuai role, region-scoped untuk Admin Wilayah/Karyawan. **Tiga URL terpisah**: `SUPER_ADMIN_PATH` (`/super-admin`), `WILAYAH_PATH` (`/wilayah`), `KARYAWAN_PATH` (`/karyawan` di dev, obfuscated di prod).
*   Semua role MUST log in via email (unique) + password (min 8) **di path-nya**: Super Admin `/super-admin/login`, Admin Wilayah `/wilayah/login`, Karyawan `KARYAWAN_PATH/login` — masing-masing rate limit 5 failed /15min per IP per guard. Tidak ada cross-login antar path.
*   Invalid login SHALL return generic error + rate limit 5 failed /15min per IP per guard (super-admin / wilayah / karyawan terpisah).
*   Session MUST contain `user_id`/`employee_id` + `role` + `region_id`; all queries auto-scoped. PWA manifest + service worker installable (khusus KARYAWAN_PATH). **Tidak ada self-service reset mandiri** — karyawan & Admin Wilayah password direset oleh admin di Admin Management; karyawan tidak dapat reset sendiri via email link.

**FR-23: Karyawan Profile (View & Limited Edit, Own-Data-Only)**
The system SHALL allow karyawan to view own Lengkap HR profile and edit limited fields.
*   Profile MUST display: NIK, NIP, name, golongan, jabatan, unit kerja, status kepegawaian, foto (S3), kontak, region (read-only).
*   Karyawan SHALL be able to edit: foto (S3 upload <5MB), kontak (phone, email), password. Cannot edit NIK/NIP/golongan/jabatan/unit/status/region.
*   System MUST enforce own-data-only: `GET /api/karyawan/me` only; 403 if accessing other employee_id. Strict policy test required.

**FR-24: Admin Wilayah — Employee Data Management (Lengkap HR, Region-Scoped, 1 Karyawan = 1 Titik)**
The system SHALL allow Admin Wilayah to input/manage employee data for own Kabupaten/Kota only; Super Admin manages all. **Tiap karyawan di-assign ke 1 titik proyek (`office_location_id` FK, NULLABLE — `NULL` = belum di-assign, tidak bisa absen 422).**
*   Fields REQUIRED: NIK (UK, 16 digits), NIP (optional, UK), name, golongan, jabatan, unit_kerja, status_kepegawaian (enum: PNS/PPPK/Kontrak), foto (S3), email, phone, region_id (auto-set to own region for Admin Wilayah), **`office_location_id` (FK OFFICE_LOCATION.id, nullable — pilih 1 titik di wilayahnya; kandidat tambah anggota di halaman titik hanya `regionId==region.id && office_location_id==null`)**, dokumen pendukung (optional S3).
*   Admin Wilayah MUST be able to Create/Read/Update/Delete employees **only where region_id == own region** + **assign/pindah titik via halaman dedicated `GET /regions/{region}/sites/{site}` (aksi Pindah)**. Can VIEW (read-only) employees of other regions with indicator "Read Only". Dashboard/Admin list menampilkan breakdown per titik + filter `Titik Proyek` + tanpa titik (`__null`).
*   Super Admin can CRUD all regions + manage Admin Wilayah accounts (assign region) + assign titik manapun. All writes MUST be transactional + audited (admin_id, timestamp).

**FR-25: Absensi GPS + Selfie + Jam Kerja Global (Mobile PWA, Geofenced Titik Assigned — 1 Karyawan = 1 Titik)**
The system SHALL provide check-in/out via mobile PWA dengan validasi **hanya ke titik proyek assigned karyawan** (`employee.office_location_id`, 1 karyawan = 1 titik — ex Andi Saputra → Bendungan Bili-Bili 200m). N titik per wilayah (Bendungan A, Jembatan B, Embung C — tiap titik lat/lng + radius_m 50–1000m input admin via Leaflet).
*   Karyawan MUST be able to tap "Absen Masuk/Pulang" → browser requests GPS + camera → system validates **(1) lokasi**: `employee.office_location_id == null` → **422 "Belum di-assign titik — hubungi Admin Wilayah" (tidak tercatat)**; else Haversine `dist` ke titik assigned → `within = dist <= radius_m(assigned)`; **di luar assigned ditolak 422 (tidak tercatat)**; **(2) waktu**: timestamp vs Global Jam Kerja (jam_masuk + toleransi → on_time/late) in WITA.
*   Selfie photo MUST be uploaded to S3 (`/attendance/{region_id}/{employee_id}/{date}/`), stored with timestamp, lat, lng, device info, **titik assigned (`office_location_id`, nama + distance_m + radius, badge Dalam/Di luar assigned)**. Max 5MB, image only.
*   Records: `employee_id`, `region_id`, `office_location_id` (FK titik assigned), `type` (in/out), `timestamp` (Asia/Makassar), `lat`, `lng`, `selfie_url`, `status` (on_time/late/early_leave/excused_love), `distance_m`, `is_fake_gps` flag. Tidak ada out_of_range — validasi `GeofenceService::isWithinAssignedSite`; `!within` atau `NULL assigned` → 422 tidak tercatat.
*   Global jam: default `jam_masuk 07:30`, `jam_pulang 16:00`, `toleransi 15 menit`, `hari_kerja Senin-Jumat`. Super Admin editable via FR-21; all wilayah share same jam. Non-hari_kerja ditolak. **Mesti di dalam radius titik assigned** (`distance <= radius_m(assigned)`) — di luar assigned atau tanpa titik ditolak 422.
*   Admin Wilayah views attendance list for own region (kolom **Titik Proyek** link `GET /regions/{region}/sites/{site}`, filter Wilayah→Titik Proyek→Status incl. `__null` tanpa titik, badge Dalam/Di luar, jarak) + breakdown per titik di Dashboard (cards Link per site + anggota/lat/lng/radius); Super Admin views all. Employee PWA views own history paginated (banner titik assigned lat/lng•radius, preview jarak/radius + badge, tombol absen disabled `!inRadius || tanpaTitik`). Offline queue: store locally and sync when online — server validates `isWithinAssignedSite` + waktu on sync (use server timestamp).

**FR-26: Cuti Berjenjang (Multi-Level Approval)**
The system SHALL support leave requests with berjenjang approval: Karyawan → Atasan Langsung → Admin Wilayah → Super Admin Pusat.
*   Karyawan SHALL be able to submit cuti: jenis (tahunan/sakit/ besar), tanggal mulai/selesai, alasan, dokumen (optional S3). Status initial `pending`.
*   Approval flow MUST be: `pending` → `approved_level1` (Atasan) → `approved_level2` (Admin Wilayah) → `approved` (Super Admin). Any level can `rejected` (terminal). Notifications (email + PWA push ready) at each transition.
*   Each approval MUST record `approved_by`, `approved_at`, `notes`. Admin dashboards show pending queue per level scoped by region.

**FR-27: Pengumuman (Broadcast + Wilayah Targeted)**
The system SHALL support announcements: Super Admin broadcast to all regions, Admin Wilayah targeted to own region.
*   Fields: title, content (HTML), attachment (optional S3), `scope` (global/region), `region_id` (if region), `published_at`, `is_pinned`.
*   Karyawan MUST see inbox: combined global + own region announcements, sorted newest, with read/unread status (`announcement_reads` pivot).
*   Admin Wilayah can CRUD only own region announcements (read all). Super Admin CRUD all. Push notification ready (PWA).

**FR-28: Region/Wilayah Management — N Titik Proyek per Wilayah (1 Karyawan = 1 Titik + Dedicated Page per Titik)**
The system SHALL allow Super Admin to CRUD Kantor (Kantor Pusat + Wilayah Kab/Kota se-Sulsel) plus **N titik proyek per wilayah** with geofence per titik + **dedicated page per titik**.
*   Fields Region: name, slug (UK), kantor_name, tipe (pusat/cabang), address, is_active. Fields per Titik Proyek (`office_locations` / `project_sites`): `nama_lokasi` / `nama_titik` (ex: Bendungan A, Jembatan B), `lat` DECIMAL(10,8), `lng` DECIMAL(11,8), `radius_m` INT 50–1000 default 200, `address` nullable, `is_active`. Input tiap titik via map picker Leaflet (draggable marker+circle) + radius slider 50–1000, minimal 1 titik per wilayah, N≤20, **hapus titik terakhir diblokir 422**.
*   Dedicated page per titik: `GET /regions/{region}/sites/{site}` → `Admin/SiteDetail` (tersedia di **3 prefix**: `super_admin.sites.show` / `admin.sites.show` / `wilayah.sites.show`) — menampilkan Leaflet per titik (draggable+circle), form edit titik (nama/lat/lng/radius/address), **anggota per titik saja** (`office_location_id == site.id`, 1 karyawan=1 titik, list + select kandidat **hanya karyawan `regionId==region.id && office_location_id==null`** + aksi `Tambah` / `Pindah` per titik), **kandidatTambah** filtered tanpa titik.
*   Super Admin CRUD all wilayah + N titik per wilayah (tambah/edit/hapus titik); **Admin Wilayah can tambah/edit/hapus titik proyek di wilayahnya sendiri (own region, N titik, N≤20)** — contoh tambah Bendungan A lalu Jembatan B di wilayahnya — view others read-only. Karyawan `1=1 titik` (`office_location_id`): tanpa titik tidak bisa absen; **validasi absensi/Love hanya `GeofenceService::isWithinAssignedSite(dist <= radius_m(assigned))`** — bukan `isWithinAnySite`; di luar assigned ditolak 422.

**FR-29: PWA Mobile Experience (Installable, Offline-Capable, Per Titik Assigned)**
The system SHALL deliver karyawan PWA that is installable, responsive 320px+, offline-capable — **sinkron model 1 karyawan = 1 titik (`office_location_id`)**.
*   PWA MUST have `manifest.json`, `service-worker.js` caching static + profile + pengumuman + jam kerja global (stale-while-revalidate) — **data assigned site (`loadRegions` + `office_location_id`) cached via `_shared.js` / `bbws_mock_*_v3`**.
*   App MUST be responsive mobile-first (320–767 default), touch 44px min, handle GPS/camera permission UX gracefully, display global jam kerja (07:30–16:00) + countdown to jam masuk/pulang — **plus banner/header titik assigned**: `Ditugaskan di: {nama_lokasi} — {regionName} • {lat},{lng} • radius {radius_m}m` (Dashboard/Karyawan Absensi/Profil/Rekap); **tanpa titik (`tanpaTitik`) tampil warning 422 "Belum di-assign titik — hubungi Admin Wilayah" + absen & Love disabled**.
*   Absensi MUST display preview jarak/radius ke titik assigned + badge **Dalam/Di luar radius titik assigned**; tombol absen disabled `!inRadius || tanpaTitik`; history tampil `jarak/radius` per assigned. Love cek `jarak <= radius_m(assigned)` only (bukan any site). Lighthouse PWA score >90, install prompt, push notification infrastructure ready (VAPID keys in .env).

**FR-32: Reset Password via Admin (Tanpa Self-Service)
The system SHALL allow password reset hanya via Admin Management — tidak ada fitur lupa/reset mandiri untuk karyawan/Admin Wilayah.
*   Karyawan: password direset oleh Admin Wilayah (own region) atau Super Admin — di tabel Karyawan → action Reset Password → generate token/link atau set password baru, kirim email ke karyawan. Audit log. Karyawan tidak dapat kirim link sendiri.
*   Admin Wilayah: password direset oleh Super Admin — di tabel Admin Wilayah → Reset Password → email link ke admin_wilayah.

**FR-30: Jam Kerja Global (Global, Super Admin Only)**
The system SHALL have ONE global working hours config for all kantor cabang BBWS Pompengan Jeneberang.
*   Fields: `jam_masuk` (TIME, default 07:30), `jam_pulang` (TIME, default 16:00), `toleransi_late_menit` (INT, default 15), `hari_kerja` (JSON array, default ["Senin","Selasa","Rabu","Kamis","Jumat"]), `timezone` (default Asia/Makassar WITA). Stored as `global_settings` keys or `attendance_settings` table, cached Redis, versioned.
*   Only Super Admin Pusat (Makassar) can edit via Global Settings UI; Admin Wilayah/Karyawan read-only. Change affects absensi validation next day.
*   Absensi logic: `check-in <= jam_masuk + toleransi → on_time`, `> jam_masuk+toleransi → late`, `check-out < jam_pulang → early_leave` (optional flag), `non-hari_kerja → rejected or flagged`. Time comparison in WITA, server-side. Di luar radius ditolak, tidak bisa pakai Love.

**FR-31: Love System — 4 Hati / Bulan, Fleksibel, Dalam Radius Titik Assigned (1 Karyawan = 1 Titik), 1 Level Admin Wilayah**
The system SHALL provide Love (4 hearts/month) as buffer untuk late **dalam radius titik proyek assigned karyawan** (`office_location_id`, 1=1 titik).
*   Global config: `love_max_default` (INT 1–10, default 4) di `global_settings`/`attendance_settings`, diatur Super Admin, berlaku bulan depan, log, cached Redis. Reset bulanan `1st 00:00 WITA` via scheduled job: `love_sisa = love_max` per karyawan per bulan, track di `employee_love_balances` (employee_id, period YYYY-MM, love_sisa, love_max_at_period).
*   Trigger: `attendances.status=late` DAN `distance_m <= radius_m(titik assigned)` **(Haversine ke `assigned OfficeLocation` via `GeofenceService::isWithinAssignedSite`, bukan `isWithinAnySite`)** → karyawan dapat ajukan **1 Love Claim di bulan yang sama** (hari/tanggal boleh beda, 1 Love per 1 late) via PWA: `POST /api/karyawan/love-claims {attendance_id, alasan, dokumen}` (dokumen PDF/image max 5MB, alasan max 500). **Tanpa titik (`office_location_id IS NULL`) → tidak bisa claim (disabled + warning 422 "Belum di-assign titik")**; **di luar radius assigned → tidak bisa claim (disabled, absen ditolak 422)**.
*   Approval 1 level: `Admin Wilayah` (admin wilayah own region) `POST /api/admin/love-claims/{id}/approve` / `reject` — **server cek `within = distance <= radius_m(assigned)`** (assigned site, 1=1); Approve → `love_sisa-1`, `attendances.status → excused_love` (di rekap dianggap on_time, tidak hitung late), `love_claims.status=approved`. Reject → tetap `late`. Tanpa titik / di luar assigned tidak ada claim (absen ditolak 422).
*   Jika `love_sisa=0` → late tidak bisa di-excuse (tombol Gunakan Love disabled plus guard `!hit || sisa===0`), tetap late + notifikasi Admin. Love tidak bisa minus, tidak bisa carry-over (reset tiap bulan).
*   UI: Dashboard 4 dot gold #FCB833 (terisi/abu #E2E8F0), text "Sisa toleransi: 3/4", tombol "Gunakan Love" pada late (disabled `!inRadius(assigned) || sisa===0 || tanpaTitik`), history Love Claims di Rekap. **Admin Love**: kolom **Titik Proyek** link `GET /regions/{region}/sites/{site}`, badge `Dalam/Di luar` (`jarak <= radius(assigned)`), Approve disabled `sisaLove===0 || !inRadius`.

## 2. Non-Functional Requirements

| Category | Requirement | Measurable Target |
|:---|:---|:---|
| Performance | Fast Page Loads | LCP < 2.5s; FCP < 1.8s; TTFB < 200ms cached; PWA Lighthouse >90 mobile |
| Security | System Integrity | HTTPS enforced; OWASP Top 10; **Tiga URL obfuscated terpisah** `SUPER_ADMIN_PATH` / `WILAYAH_PATH` / `KARYAWAN_PATH` (guard terpisah, tidak cross-login); Rate limiting on all logins (super-admin, wilayah, karyawan) & contact; Region isolation middleware |
| Scalability | Traffic Handling | Stateless; Handle 1,000 public + 500 karyawan PWA concurrent <500ms |
| Usability | Accessibility & UX | Fully responsive + PWA installable (320px+); WCAG 2.1 AA; 44px tap; GPS/camera UX |
| Maintainability | Code Quality | PSR-12; >80% backend coverage including RBAC & region scoping tests |
| Reliability | System Uptime | 99.9% public + karyawan PWA; All S3 uploads (selfie) transactional |
| Privacy | Data Isolation | Karyawan own-data only; Admin Wilayah write own region only; Super Admin all |

## 3. Technical Constraints

*   The system MUST support multi-tenancy per Kabupaten/Kota via `region_id` scoping for employees/attendances/leaves/announcements.
*   The system MUST implement RBAC with 3 roles: Super Admin Pusat, Admin Wilayah (per region), Karyawan (NIK login, own-data-only).
*   Admin Wilayah can view all regions but write only own region; Karyawan cannot view other employees — enforced at policy + query scope layer.
*   The technology stack (Laravel 13 + PHP 8.4+, React 19, Inertia v2, Tailwind v4, Vite 7, MySQL 8.4 LTS / 9.x, AWS S3, PWA) is fixed — all latest stable.
*   Tiap wilayah wajib punya **N titik proyek** (Bendungan A, Jembatan B — masing-masing **nama titik + lat/lng + radius_m 50–1000 per titik**, dedicated page `GET /regions/{region}/sites/{site}` + anggota per titik) — Super Admin all, Admin Wilayah own N≤20, last-site delete 422. **1 karyawan = 1 titik (`office_location_id`)**: tanpa titik 422 tidak bisa absen; validasi absensi/Love **hanya `GeofenceService::isWithinAssignedSite(dist <= radius_m(assigned))`** — bukan `isWithinAnySite`. **Absen di luar titik assigned atau tanpa titik ditolak 422 (tidak tercatat, tidak bisa pakai Love).**
*   Love System: 4/bulan default, reset bulanan, total fleksibel Super Admin (1–10), pakai Love hanya untuk late **dalam radius titik assigned (1=1)** + dokumen + approval 1 level Admin Wilayah (bulan yang sama, hari beda boleh) — cek `distance <= radius_m(assigned)`; tanpa titik tidak bisa claim.
*   Admin & Karyawan URLs MUST be obfuscated terpisah — `SUPER_ADMIN_PATH` (e.g. `/super-admin-<hash>`), `WILAYAH_PATH` (e.g. `/wilayah-<hash>`), `KARYAWAN_PATH` (e.g. `/karyawan-<hash>`) via env. Dev defaults: `/super-admin`, `/wilayah`, `/karyawan`. Tidak ada path `/admin` generik di production.

## 4. Assumptions

*   The client WILL provide initial content + region list (Kabupaten/Kota) + geofence lat/lng/radius before development.
*   The client WILL provide initial Lengkap HR employee data per region or admin wilayah will input via UI.
*   The client WILL provide Google Analytics + SMTP + VAPID keys for PWA push credentials.
*   Karyawan devices support GPS + camera for absensi; modern mobile browser supports PWA install.
*   The hosting environment (VPS Ubuntu 24.04 LTS) WILL be configured with Nginx, PHP 8.4, MySQL 8.4 LTS, Node 22, Redis 7.
*   AWS S3 bucket and IAM credentials WILL be provided for media + selfie storage.