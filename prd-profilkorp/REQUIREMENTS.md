# REQUIREMENTS.md: BBWS Pompengan Jeneberang

## 1. Functional Requirements

### 1.2. Admin Dashboard

**FR-10: Secure Authentication (Super Admin & Admin Wilayah)**
The system SHALL provide dedicated login for Super Admin Pusat and Admin Wilayah (per Kabupaten/Kota), implementing session-based auth via Laravel Sanctum 4.x with role & region scoping. Admin URL obfuscated.
*   Admin MUST be able to log in via email + password. Super Admin manages Admin Wilayah accounts (CRUD, assign region_id).
*   Invalid attempts SHALL return generic error + rate limiting (5/15min per IP per guard).
*   System MUST maintain session with region_id in session; all write queries scoped to own region (Admin Wilayah), Super Admin unscoped.

**FR-11: Main Dashboard Overview (Role-Scoped)**
The system SHALL present a role-scoped dashboard: Super Admin sees global analytics + all regions stats; Admin Wilayah sees own region stats (employee count, attendance today, pending cuti) + read-only global stats.
*   Dashboard MUST display metrics scoped by region_id for Admin Wilayah.
*   Section for recent contact submissions + pending leave requests (for Admin Wilayah).
*   Quick links MUST be role-filtered (e.g., Admin Wilayah sees "Kelola Karyawan Wilayah Saya").

**FR-12: Page Content Management (WYSIWYG)**
The system SHALL provide a WYSIWYG editor (e.g., TinyMCE) within the Admin Dashboard to manage the content of static pages such as "About Us" and "Services".
*   The WYSIWYG editor MUST allow administrators to format text, insert images, and create links for page content.
*   Content saved through the WYSIWYG editor MUST be accurately displayed on the corresponding public page.
*   The editor SHOULD support common rich text functionalities like bold, italic, lists, and headings.

**FR-13: Portfolio Project Management (CRUD)**
The system SHALL provide a full CRUD (Create, Read, Update, Delete) interface for managing portfolio projects. This includes fields for title, description, client, date, and an interface to upload/manage associated images and videos.
*   The administrator MUST be able to create new portfolio projects with all required details.
*   Existing portfolio projects MUST be editable and deletable.
*   The interface MUST allow uploading multiple images and videos for each project and associating them correctly.

**FR-14: Team Member Management (CRUD)**
The system SHALL provide a full CRUD interface for managing team member profiles.
*   The administrator MUST be able to add, edit, and delete team member profiles.
*   Each team member profile MUST include fields for name, title, photo, and a brief biography.
*   Changes made to team member profiles SHALL be reflected on the public team page.

**FR-15: Blog Post Management (CRUD)**
The system SHALL provide a full CRUD interface for managing blog posts, including managing categories, tags, author, and publication status (Draft, Published).
*   The administrator MUST be able to create, edit, and delete blog posts.
*   Each blog post MUST support assignment to one or more categories and tags.
*   The administrator SHALL be able to set a blog post's status to "Draft" or "Published", controlling its visibility on the public site.

**FR-16: Testimonial Management (CRUD)**
The system SHALL provide a full CRUD interface for managing testimonials, including an "approved" status to control visibility on the public site.
*   The administrator MUST be able to add, edit, and delete client testimonials.
*   Each testimonial MUST have a toggle or checkbox to set its "approved" status.
*   Only testimonials marked as "approved" SHALL be displayed on the public website.

**FR-17: Contact Submission Viewer**
The system SHALL provide a view within the Admin Dashboard to list, read, and archive all messages submitted through the public contact form.
*   The administrator MUST be able to view a list of all contact form submissions.
*   Clicking on a submission from the list SHALL display its full details (Name, Email, Subject, Message, Submission Date).
*   The administrator SHALL be able to mark submissions as "archived" to remove them from the active list.

**FR-18: Centralized Media Library**
The system SHALL provide a centralized interface for managing all uploaded media. All files MUST be stored on and served from AWS S3. The library MUST support image uploads, deletions, and browsing.
*   The administrator MUST be able to upload images and other media files through the interface.
*   Uploaded files MUST be stored in the configured AWS S3 bucket.
*   The media library MUST display a browsable list of all uploaded media, allowing for selection and deletion.

**FR-19: Advanced SEO Management**
The system SHALL provide a dedicated section within each page/post editor to manually override meta titles, meta descriptions, and Open Graph tags.
*   For each editable content item (e.g., page, blog post, service), the administrator MUST have fields to input custom meta title, meta description, and Open Graph properties.
*   Custom SEO values entered SHALL override any dynamically generated values for that specific content item.
*   The system SHALL validate the length of meta titles and descriptions to adhere to common SEO best practices.

**FR-20: Content Versioning**
For critical pages (e.g., About Us, Service details), the system MUST save a history of content changes. The administrator MUST be able to view past versions and rollback to a previous version.
*   The system SHALL automatically create a new version record whenever critical page content is saved.
*   The administrator MUST be able to view a list of historical versions for a given page, including the date and user who made the change.
*   The administrator SHALL be able to select a previous version and restore it as the current active content.

**FR-21: Global Settings Management + Jam Kerja Global**
The system SHALL provide an interface to manage site-wide information: Company Name, Logo, Favicon, Contact Email, Phone Number, Address, Social Media Profile links, **and Global Working Hours**.
*   Only Super Admin (Kantor Pusat) MUST be able to update global settings + jam kerja; Admin Wilayah read-only.
*   Jam Kerja Global fields REQUIRED: `jam_masuk` (default 07:30 WITA), `jam_pulang` (default 16:00 WITA), `toleransi_late_menit` (default 15), `hari_kerja` (Senin-Jumat, default 5 hari). Stored in `global_settings` (keys: `jam_masuk`, `jam_pulang`, etc.) or `attendance_settings` table, cached with Redis. Timezone `Asia/Makassar` (WITA).
*   Changes SHALL be immediately reflected: absensi next day uses new jam; PWA shows jam kerja di dashboard home.

### 1.3. Karyawan Mobile PWA (Employee Self-Service) — NEW

**FR-22: Karyawan & Admin Authentication (Email + Password, PWA)**
The system SHALL provide dedicated login via **email + password untuk semua role** (Super Admin, Admin Wilayah, Karyawan) — bukan NIK. Menggunakan Laravel Sanctum dengan guard sesuai role, region-scoped untuk Admin Wilayah/Karyawan.
*   Semua role MUST log in via email (unique) + password (min 8) (`/login` obfuscated per role).
*   Invalid login SHALL return generic error + rate limit 5 failed /15min per IP + per NIK throttling.
*   Session MUST contain `user_id`/`employee_id` + `region_id`; all queries auto-scoped. PWA manifest + service worker installable. **Tidak ada self-service reset mandiri** — karyawan & Admin Wilayah password direset oleh admin di Admin Management; karyawan tidak dapat reset sendiri via email link.

**FR-23: Karyawan Profile (View & Limited Edit, Own-Data-Only)**
The system SHALL allow karyawan to view own Lengkap HR profile and edit limited fields.
*   Profile MUST display: NIK, NIP, name, golongan, jabatan, unit kerja, status kepegawaian, foto (S3), kontak, region (read-only).
*   Karyawan SHALL be able to edit: foto (S3 upload <5MB), kontak (phone, email), password. Cannot edit NIK/NIP/golongan/jabatan/unit/status/region.
*   System MUST enforce own-data-only: `GET /api/karyawan/me` only; 403 if accessing other employee_id. Strict policy test required.

**FR-24: Admin Wilayah — Employee Data Management (Lengkap HR, Region-Scoped)**
The system SHALL allow Admin Wilayah to input/manage employee data for own Kabupaten/Kota only; Super Admin manages all.
*   Fields REQUIRED: NIK (UK, 16 digits), NIP (optional, UK), name, golongan, jabatan, unit_kerja, status_kepegawaian (enum: PNS/PPPK/Kontrak), foto (S3), email, phone, region_id (auto-set to own region for Admin Wilayah), dokumen pendukung (optional S3).
*   Admin Wilayah MUST be able to Create/Read/Update/Delete employees **only where region_id == own region**. Can VIEW (read-only) employees of other regions with indicator "Read Only".
*   Super Admin can CRUD all regions + manage Admin Wilayah accounts (assign region). All writes MUST be transactional + audited (admin_id, timestamp).

**FR-25: Absensi GPS + Selfie + Jam Kerja Global (Mobile PWA, Geofenced Multi-Lokasi)**
The system SHALL provide check-in/out via mobile PWA dengan validasi **1–3 lokasi kantor per wilayah** (tiap lokasi lat/lng + radius_m fleksibel).
*   Karyawan MUST be able to tap "Absen Masuk/Pulang" → browser requests GPS + camera → system validates **(1) lokasi**: lat/lng within **salah satu** lokasi kantor di wilayahnya (distance ≤ radius_m lokasi terdekat), **(2) waktu**: timestamp vs Global Jam Kerja (jam_masuk + toleransi → on_time/late) in WITA.
*   Selfie photo MUST be uploaded to S3 (`/attendance/{region_id}/{employee_id}/{date}/`), stored with timestamp, lat, lng, device info. Max 5MB, image only.
*   Records: `employee_id`, `region_id`, `type` (in/out), `timestamp` (Asia/Makassar), `lat`, `lng`, `selfie_url`, `status` (on_time/late/early_leave), `distance_m`, `is_fake_gps` flag. Tidak ada out_of_range — di luar radius ditolak (422) tidak tercatat.
*   Global jam: default `jam_masuk 07:30`, `jam_pulang 16:00`, `toleransi 15 menit`, `hari_kerja Senin-Jumat`. Super Admin editable via FR-21; all wilayah share same jam. Non-hari_kerja ditolak. **Mesti di dalam radius salah satu lokasi** — di luar semua radius ditolak 422.
*   Admin Wilayah views attendance list for own region; Super Admin views all. Employee views own history paginated. Offline queue: store locally and sync when online — server validates waktu on sync (use server timestamp).

**FR-26: Cuti Berjenjang (Multi-Level Approval)**
The system SHALL support leave requests with berjenjang approval: Karyawan → Atasan Langsung → Admin Wilayah → Super Admin Pusat.
*   Karyawan SHALL be able to submit cuti: jenis (tahunan/sakit/ besar), tanggal mulai/selesai, alasan, dokumen (optional S3). Status initial `pending`.
*   Approval flow MUST be: `pending` → `approved_level1` (Atasan) → `approved_level2` (Admin Wilayah) → `approved` (Super Admin). Any level can `rejected` (terminal). Notifications (email + PWA push ready) at each transition.
*   Each approval MUST record `approved_by`, `approved_at`, `notes`. Admin dashboards show pending queue per level scoped by region.

**FR-27: Pengumuman (Broadcast + Wilayah Targeted)**
The system SHALL support announcements: Super Admin broadcast to all regions, Admin Wilayah targeted to own region.
*   Fields: title, content (HTML WYSIWYG), attachment (optional S3), `scope` (global/region), `region_id` (if region), `published_at`, `is_pinned`.
*   Karyawan MUST see inbox: combined global + own region announcements, sorted newest, with read/unread status (`announcement_reads` pivot).
*   Admin Wilayah can CRUD only own region announcements (read all). Super Admin CRUD all. Push notification ready (PWA).

**FR-28: Region/Wilayah Management (Kantor BBWS PJ se-Sulsel + Geofence)**
The system SHALL allow Super Admin to CRUD Kantor (Kantor Pusat + Wilayah Kab/Kota se-Sulsel) with geofence config.
*   Fields: name, slug (UK), kantor_name, tipe (pusat/cabang), lat, lng, radius_m (default 200m, 50–1000 range), address, is_active. Input via map picker.
*   Super Admin CRUD all wilayah + lokasi (1–3 per wilayah); Admin Wilayah can edit lokasi & radius di wilayahnya sendiri (own region), view others read-only. Karyawan linked to one region, validasi terhadap lokasi terdekat di wilayahnya. Geofence per lokasi, di luar semua radius ditolak.

**FR-29: PWA Mobile Experience (Installable, Offline-Capable)**
The system SHALL deliver karyawan PWA that is installable, responsive 320px+, offline-capable.
*   PWA MUST have `manifest.json`, `service-worker.js` caching static + profile + pengumuman + jam kerja global (stale-while-revalidate).
*   App MUST be responsive mobile-first (320–767 default), touch 44px min, handle GPS/camera permission UX gracefully, display global jam kerja (07:30–16:00) + countdown to jam masuk/pulang.
*   Lighthouse PWA score >90, install prompt, push notification infrastructure ready (VAPID keys in .env).

**FR-32: Reset Password via Admin (Tanpa Self-Service)
The system SHALL allow password reset hanya via Admin Management — tidak ada fitur lupa/reset mandiri untuk karyawan/Admin Wilayah.
*   Karyawan: password direset oleh Admin Wilayah (own region) atau Super Admin — di tabel Karyawan → action Reset Password → generate token/link atau set password baru, kirim email ke karyawan. Audit log. Karyawan tidak dapat kirim link sendiri.
*   Admin Wilayah: password direset oleh Super Admin — di tabel Admin Wilayah → Reset Password → email link ke admin_wilayah.

**FR-30: Jam Kerja Global (Global, Super Admin Only)**
The system SHALL have ONE global working hours config for all kantor cabang BBWS Pompengan Jeneberang.
*   Fields: `jam_masuk` (TIME, default 07:30), `jam_pulang` (TIME, default 16:00), `toleransi_late_menit` (INT, default 15), `hari_kerja` (JSON array, default ["Senin","Selasa","Rabu","Kamis","Jumat"]), `timezone` (default Asia/Makassar WITA). Stored as `global_settings` keys or `attendance_settings` table, cached Redis, versioned.
*   Only Super Admin Pusat (Makassar) can edit via Global Settings UI; Admin Wilayah/Karyawan read-only. Change affects absensi validation next day.
*   Absensi logic: `check-in <= jam_masuk + toleransi → on_time`, `> jam_masuk+toleransi → late`, `check-out < jam_pulang → early_leave` (optional flag), `non-hari_kerja → rejected or flagged`. Time comparison in WITA, server-side. Di luar radius ditolak, tidak bisa pakai Love.

**FR-31: Love System — 4 Hati / Bulan, Fleksibel, Dalam Radius, 1 Level Admin Wilayah**
The system SHALL provide Love (4 hearts/month) as buffer untuk late dalam radius.
*   Global config: `love_max_default` (INT 1–10, default 4) di `global_settings`/`attendance_settings`, diatur Super Admin, berlaku bulan depan, log, cached Redis. Reset bulanan `1st 00:00 WITA` via scheduled job: `love_sisa = love_max` per karyawan per bulan, track di `employee_love_balances` (employee_id, period YYYY-MM, love_sisa, love_max_at_period).
*   Trigger: `attendances.status=late` DAN `distance_m <= radius_m` (dalam radius, selfie valid) → karyawan dapat ajukan **1 Love Claim** di hari yang sama dengan late (00:00–23:59 WITA) via PWA: `POST /api/karyawan/love-claims {attendance_id, alasan, dokumen}` (dokumen PDF/image max 5MB, alasan max 500).
*   Approval 1 level: `Admin Wilayah` (admin wilayah own region) `POST /api/admin/love-claims/{id}/approve` / `reject`. Approve → `love_sisa-1`, `attendances.status → excused_love` (di rekap dianggap on_time, tidak hitung late), `love_claims.status=approved`. Reject → tetap `late`. Di luar radius tidak ada claim (absen ditolak 422).
*   Jika `love_sisa=0` → late tidak bisa di-excuse (tombol Gunakan Love disabled), tetap late + notifikasi Admin. Love tidak bisa minus, tidak bisa carry-over (reset tiap bulan).
*   UI: Dashboard 4 dot gold #FCB833 (terisi/abu #E2E8F0), text "Sisa toleransi: 3/4", tombol "Gunakan Love" pada late pending claim, history Love Claims di Rekap.

## 2. Non-Functional Requirements

| Category | Requirement | Measurable Target |
|:---|:---|:---|
| Performance | Fast Page Loads | LCP < 2.5s; FCP < 1.8s; TTFB < 200ms cached; PWA Lighthouse >90 mobile |
| Security | System Integrity | HTTPS enforced; OWASP Top 10; Obfuscated admin+karyawan URLs; Rate limiting on all logins (admin, karyawan NIK) & contact; Region isolation middleware |
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
*   Admin & Karyawan URLs MUST be obfuscated (not `/admin`, `/karyawan`) via `ADMIN_PATH` & `KARYAWAN_PATH` env.

## 4. Assumptions

*   The client WILL provide initial content + region list (Kabupaten/Kota) + geofence lat/lng/radius before development.
*   The client WILL provide initial Lengkap HR employee data per region or admin wilayah will input via UI.
*   The client WILL provide Google Analytics + SMTP + VAPID keys for PWA push credentials.
*   Karyawan devices support GPS + camera for absensi; modern mobile browser supports PWA install.
*   The hosting environment (VPS Ubuntu 24.04 LTS) WILL be configured with Nginx, PHP 8.4, MySQL 8.4 LTS, Node 22, Redis 7.
*   AWS S3 bucket and IAM credentials WILL be provided for media + selfie storage.