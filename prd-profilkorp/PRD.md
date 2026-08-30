# PRD: BBWS Pompengan Jeneberang

## Executive Summary & Product Vision

This document outlines the Product Requirements for **BBWS Pompengan Jeneberang**, aplikasi profil dinas + HR portal dengan admin berjenjang dan PWA karyawan.

**Konteks domain:** Pusat di **Makassar**, cabang di **Kabupaten/Kota se-Sulawesi Selatan** (21 Kabupaten + 3 Kota = 24 wilayah). Pusat mengelola semua cabang; tiap cabang (Admin Wilayah) mengelola karyawannya sendiri termasuk input **N titik proyek per wilayah** (contoh: **Bendungan A, Jembatan B, Embung C, Irigasi D** — tiap titik punya **lat/lng + radius absen**) untuk validasi GPS+selfie.

The vision is to provide a dynamic, high-performance, and secure public-facing corporate website where all content is easily manageable through a comprehensive admin panel, plus a regional HR management + mobile PWA for employees. The system will leverage a modern monolithic architecture using Laravel 13 and React 19 with Inertia.js v2 to deliver a seamless user experience for both public visitors, admin pusat/cabang, and employees.

## Problem Statement & Target Users

**Problem:** Traditional static company websites are difficult and time-consuming for non-technical staff to update. This leads to outdated information, poor user engagement, and missed business opportunities. Existing CMS solutions can be overly complex or lack specific features needed for a corporate profile. Additionally, regional organizations need to manage employee data per Kabupaten/Kota with distinct admin scopes and provide mobile HR self-service for employees.

**Target Users:**
*   **Super Admin Pusat (Makassar):** Admin pusat di Makassar — mengelola semua cabang Kabupaten/Kota, akun Admin Wilayah, **N titik proyek per wilayah (lat/lng/radius per titik)** + konten global & broadcast pengumuman.
*   **Admin Wilayah / Wilayah (Kabupaten/Kota):** Admin per Kantor Wilayah BBWS PJ di Kabupaten/Kota se-Sulsel — input dan kelola data karyawan Lengkap HR untuk cabangnya sendiri, **input/edit N titik proyek di wilayahnya** (contoh **Bendungan A, Jembatan B**, masing-masing **lat/lng + radius absen meter**) untuk validasi absensi karyawannya, view data cabang lain read-only.
*   **Karyawan BBWS PJ:** ASN/non-ASN di pusat maupun cabang, login via email + password di mobile PWA untuk absensi GPS+selfie **hanya ke titik proyek assigned-nya (1 karyawan = 1 titik, `office_location_id`)** — mis. Andi Saputra assigned Bendungan Bili-Bili 200m, cek `distance <= radius_m` titik assigned saja, **tanpa titik (NULL) tidak bisa absen 422**, di luar assigned ditolak 422), cuti berjenjang, pengumuman, profil, **4 Love/bulan (reset 1st 00:00 WITA, fleksibel total love diatur Super Admin, pakai Love untuk excuse terlambat dalam radius titik assigned dengan dokumen, bulan yang sama).**

## System Scope & User Roles

The system is composed of two primary components: a regional admin dashboard multi-tenant per Kantor Wilayah (Makassar Pusat + 23/24 Wilayah Kabupaten/Kota se-Sulsel), and a mobile PWA for employees. Content & HR management scoped per cabang dengan oversight pusat Makassar.

| Role | Description | Permissions |
|:---|:---|:---|
| **Super Admin Pusat (Makassar)** | Admin pusat di Makassar. | - Full CRUD on all cabang (Kabupaten/Kota se-Sulsel) + semua data karyawan. - Input/edit **N titik proyek per wilayah** (contoh Bendungan A, Jembatan B — masing-masing lat/lng/radius_m via map picker + Leaflet) + **assign/pindah karyawan per titik (1 karyawan = 1 `office_location_id`)**. - Manage akun Admin Wilayah. - Broadcast pengumuman pusat. - Full content, settings, analytics. |
| **Admin Wilayah / Wilayah (Kab/Kota)** | Admin per Kantor Wilayah BBWS PJ di Kabupaten/Kota se-Sulsel. Scope = region_id. | - CRUD data karyawan Lengkap HR **only for own cabang** (read cabang lain, write own) **+ assign tiap karyawan ke 1 titik proyek (`office_location_id`)**. - **Input/edit N titik proyek di wilayahnya sendiri** (Bendungan A, Jembatan B, dst — masing-masing lat, lng, radius_m 50–1000m) untuk geofence absensi karyawannya + **dedicated page per titik `/regions/{region}/sites/{site}` (Leaflet draggable+circle, anggota per titik saja)**. - Approve cuti level wilayah (filter per titik). - Kirim pengumuman wilayah. - View attendance own cabang (filter per titik, kolom Titik Proyek). |
| **Karyawan** (Employee) | ASN/non-ASN pusat/cabang, login email+password, mobile PWA. Strict own-data only. | - View/edit own profile (limited) **+ lihat titik assigned (nama/lat/lng/radius)**. - Absensi GPS+selfie — **hanya valid ke titik proyek assigned-nya (`office_location_id`): `distance <= radius_m(assigned)`**; `office_location_id IS NULL` (tanpa titik) → **tidak bisa absen 422**; di luar assigned ditolak 422. - Ajukan cuti berjenjang. - **4 Love/bulan (reset bulanan, total fleksibel Super Admin): jika `status=late` & `distance <= radius_m(titik assigned)` di bulan yang sama (hari/tanggal boleh beda), bisa ajukan dokumen/alasan pakai 1 Love → approval 1 level Admin Wilayah; Love juga cek `dalam radius titik assigned` (bukan titik lain).** - View pengumuman (pusat + wilayahnya). |

## Functional Requirements

### Admin Dashboard (Super Admin & Admin Wilayah — Pisah URL)
*   **FR-10: Secure Authentication (Super Admin & Admin Wilayah — Opsi B Pisah URL):** Dedicated login **terpisah URL**: **Super Admin Pusat** via `SUPER_ADMIN_PATH` (contoh `/super-admin-<hash>`, dev: `/super-admin`) dan **Admin Wilayah** via `WILAYAH_PATH` (contoh `/wilayah-<hash>`, dev: `/wilayah`). Keduanya email+password via Laravel Sanctum 4.x dengan role & region scoping. URL tidak saling tukar — Super Admin tidak login via `/wilayah`, Admin Wilayah tidak via `/super-admin`. Karyawan tetap via `KARYAWAN_PATH` (contoh `/karyawan-<hash>`). Ketiga URL di-obfuscate di production via env.
*   **FR-11: Main Dashboard:** Overview with analytics, recent contact submissions, quick links. Super Admin sees all regions; Admin Wilayah sees filtered stats for own region + read-only global stats.

*   **FR-21: Global Settings Management:** Site-wide settings termasuk **Jam Kerja Global** (jam_masuk, jam_pulang, toleransi_late_menit, hari_kerja Senin-Jumat). Only Super Admin Pusat (Makassar) can edit; Admin Wilayah read-only.

### Karyawan Mobile PWA (Employee Self-Service)
*   **FR-22: Karyawan Authentication (Email + Password):** Dedicated mobile PWA login via email + password untuk Karyawan di `KARYAWAN_PATH` (`/karyawan` di dev). Sanctum session dengan guard `karyawan`, rate-limited, supports PWA install. Karyawan only accesses own data. Karyawan & Admin Wilayah tidak punya fitur reset mandiri — password direset/diganti oleh Super Admin/Admin Wilayah di Admin Management (Karyawan oleh Admin Wilayah own region; Admin Wilayah oleh Super Admin). Super Admin & Admin Wilayah login bukan di PWA melainkan di `SUPER_ADMIN_PATH` / `WILAYAH_PATH` masing-masing.
*   **FR-23: Karyawan Profile (View & Limited Edit):** View own Lengkap HR profile (NIK, NIP, golongan, jabatan, unit kerja, status, foto, kontak). Can edit limited fields (foto, kontak, password). Cannot view other employees.
*   **FR-24: Admin Wilayah - Employee Data Management (Lengkap HR):** Admin Wilayah inputs/manages employee data for own Kantor Wilayah only (read all, write own). Fields: NIK (UK), NIP, name, golongan, jabatan, unit kerja, status kepegawaian, foto (S3), kontak, dokumen. Super Admin manages all cabang + akun Admin Wilayah. Isolasi via `region_id`.
*   **FR-25: Absensi GPS + Selfie (Mobile PWA, Geofence Titik Assigned — 1 Karyawan = 1 Titik):** Karyawan check-in/out via PWA dengan validasi GPS **hanya terhadap titik proyek assigned-nya** (`employee.office_location_id`, 1 karyawan = 1 titik — contoh Andi Saputra → Bendungan Bili-Bili 200m). N titik per wilayah (Bendungan A, Jembatan B — masing-masing lat/lng + radius_m 50–1000m input admin). Jika `office_location_id IS NULL` (tanpa titik) → **tidak bisa absen (422 "Belum di-assign titik")**; jika di luar radius titik assigned → **ditolak 422 (di luar radius titik assigned, tidak tercatat)**. Selfie upload S3 `/attendance/{region_id}/{employee_id}/{date}/`. Records: timestamp (Asia/Makassar), lat/lng, selfie_url, status on_time/late/early_leave + `office_location_id` + `distance_m` ke assigned. Admin views attendance own cabang (filter/kolom Titik Proyek + link `GET /regions/{region}/sites/{site}`). `GeofenceService::isWithinAssignedSite` Haversine `dist <= radius_m(assigned)`.
*   **FR-26: Cuti Berjenjang (Leave Workflow):** Karyawan ajukan cuti (tanggal, jenis, alasan, dokumen). Approval berjenjang: Atasan Langsung → Admin Wilayah (Wilayah) → Super Admin Pusat (Makassar). Status: pending/approved_level1/approved/rejected. Notifikasi via app + email.
*   **FR-27: Pengumuman (Broadcast Pusat + Wilayah):** Super Admin Pusat (Makassar) broadcast ke semua cabang; Admin Wilayah kirim targeted ke cabangnya sendiri. Karyawan views pengumuman relevant (pusat + cabangnya) in PWA inbox with read status.
*   **FR-28: Region/Wilayah Management — N Titik Proyek per Wilayah (1 Karyawan = 1 Titik):** Super Admin CRUD Kantor (Kantor Pusat + Wilayah Kab/Kota se-Sulsel) dengan **N titik proyek per wilayah**, tiap titik punya **nama titik (ex: Bendungan A, Jembatan B), lat/lng via map picker Leaflet, radius absen meter (50–1000, default 200), address, is_active**. Dedicated page per titik `GET /regions/{region}/sites/{site}` (Leaflet draggable+circle, edit titik) + **anggota per titik saja** (`office_location_id == site.id`). Admin Wilayah dapat **tambah/edit/hapus titik proyek di wilayahnya sendiri** (own region, N titik, N≤20, hapus last titik diblokir 422) dan view cabang lain read-only. Karyawan 1=1 titik (`office_location_id`): tanpa titik tidak bisa absen 422; absen valid hanya `distance <= radius_m(titik assigned)` via `isWithinAssignedSite`. Validasi radius 50–1000m per titik.
*   **FR-29: PWA Mobile Experience:** Employee PWA is installable (manifest + service worker), offline-capable for viewing cached profile/pengumuman, responsive 320px+, touch 44px, push notification ready. Tampilkan **titik assigned** per karyawan (badge `Ditugaskan di: Bendungan Bili-Bili — Kab. Gowa lat/lng • radius 200m`, profil card titik, rekap note `Di luar {radius}m tidak tercatat 422`) + jarak ke titik assigned & badge `Dalam/Di luar radius titik assigned` saat absen; **tanpa titik tampil warning 422 "Belum di-assign titik — hubungi Admin Wilayah"** (absen & Love disabled).
*   **FR-30: Jam Kerja Global (Aturan Masuk/Pulang):** Sistem punya **1 aturan jam kerja global** untuk semua Kantor Wilayah BBWS Pompengan Jeneberang, dikelola Super Admin via Global Settings. Field: `jam_masuk` (default 07:30 WITA), `jam_pulang` (default 16:00 WITA), `toleransi_late_menit` (default 15), `hari_kerja` (Senin-Jumat). Logic absensi: `timestamp check-in <= jam_masuk + toleransi → on_time`, `> jam_masuk+toleransi → late`, `type=out sebelum jam_pulang → early_leave` (optional). Validasi waktu server-side (WITA, Asia/Makassar). Admin Wilayah & Karyawan read-only.
*   **FR-31: Love System — 4 Hati / Bulan, Fleksibel, Dalam Radius Titik Assigned, 1 Level Admin Wilayah (Sebulan):** Setiap karyawan punya **4 Love/bulan** (default, reset `1st 00:00 WITA` tiap bulan, `love_sisa = love_max`). **Total Love fleksibel**: `global_settings.love_max_default` (1–10, default 4) diatur Super Admin Pusat (berlaku bulan depan, log). **Aturan pakai Love**: Jika `absensi.status=late` **dan** `distance <= radius_m(titik assigned)` (`employee.office_location_id`, 1 karyawan=1 titik — ex Bendungan Bili-Bili 200m) maka karyawan bisa **ajukan 1 Love Claim di bulan yang sama** dengan late tersebut (hari/tanggal boleh beda, selama masih di bulan kalender yang sama, 1 Love per 1 late) dengan dokumen/alasan via PWA. **Tanpa titik (`office_location_id IS NULL`) tidak bisa pakai Love (disabled + warning 422).** **Approval 1 level: Admin Wilayah** (own region) approve/reject dengan cek `distance <= radius_m(assigned)`; approve → `love_sisa-1`, `attendances.status` → `excused_love` (dianggap on_time di rekap), reject → tetap `late`. Di luar radius assigned **tidak bisa pakai Love** (absen ditolak 422). Jika `love_sisa=0` → late tidak bisa di-excuse. Tampilan PWA: 4 dot gold `#FCB833`, sisa `3/4`, tombol `Gunakan Love` (disabled jika `sisa=0` atau `di luar assigned` atau `tanpa titik`), history; Admin Love kolom `Titik Proyek` + badge `Dalam/Di luar` + Approve disabled `!inRadius || sisa=0`. **Login semua pakai email; Reset password Admin Wilayah dilakukan oleh Super Admin (via Admin Management), bukan self-service.**

## Non-Functional Requirements

| Category | Requirement | Metric / Target |
|:---|:---|:---|
| **Performance** | Fast Page Loads | - LCP < 2.5s, FCP < 1.8s, TTFB < 200ms cached. - PWA Lighthouse >90 mobile, offline cache for karyawan. |
| **Security** | System Integrity | - HTTPS enforced. OWASP Top 10 protection. **Tiga URL obfuscated terpisah**: `SUPER_ADMIN_PATH`, `WILAYAH_PATH`, `KARYAWAN_PATH`. Rate limiting on all logins (super-admin + wilayah + karyawan + contact form). Region isolation middleware (write own region only). Super Admin URL tidak melayani login Admin Wilayah dan sebaliknya. |
| **Scalability** | Traffic Handling | - Stateless app. Handle 1,000 concurrent public + 500 concurrent karyawan PWA with <500ms. |
| **Usability** | Accessibility & UX | - Fully responsive + PWA installable. WCAG 2.1 AA. 44px tap targets. GPS+camera permission UX for absensi. |
| **Maintainability** | Code Quality | - PSR-12, >80% backend coverage, well-documented. RBAC & region scoping tested. |
| **Reliability** | System Uptime | - 99.9% uptime public + karyawan PWA. All S3 uploads (selfie) transactional. |
| **Privacy** | Data Isolation | - Karyawan only sees own data. Admin Wilayah write own region only (read all). Super Admin sees all. |

## Technology Stack & Rationale

| Component | Technology | Rationale (Why) |
|:---|:---|:---|
| Backend | Laravel 13 (PHP 8.4+) | Latest stable Laravel (Q1 2025). Requires PHP 8.4+, improved performance, native support for Vite 7, and refined Eloquent. |
| Frontend | React 19+ | Latest React with Server Components, Actions, and improved concurrent rendering. Fully compatible with Inertia v2. |
| Adapter | Inertia.js v2 | Latest major version — deferred props, prefetching, and poll optimization. Still bridges Laravel & React without separate API. |
| Styling | Tailwind CSS v4 | Latest utility-first engine (Oxide) — 5x faster builds, CSS-first config, container queries native. |
| Database | MySQL 8.4 LTS / 9.x | Latest LTS (8.4) or innovation release (9.x). Full Laravel 13 support, improved JSON & vector support. |
| File Storage | AWS S3 | Highly scalable, durable, and cost-effective cloud storage for media assets. |
| Authentication | Laravel Sanctum 4.x | Latest lightweight SPA auth, compatible with Laravel 13 session handling. |
| Build Tool | Vite 7 | Default with Laravel 13. Lightning-fast HMR, Rolldown-based bundling, optimized production builds. |
| Hosting | VPS Ubuntu 24.04 LTS (e.g., Linode) | Latest LTS OS, cost-effective with full control over PHP 8.4 + Node 22 environment. |

## Success Metrics & KPIs

| Metric | KPI | Target |
|:---|:---|:---|
| User Engagement | Average Time on Page | > 90 seconds on key pages (Dashboard, Pengumuman). |
| Lead Generation | Contact Form Submissions | ≥ 10 valid submissions per month after launch. |
| Admin Efficiency | Content Update Time | < 5 minutes for a standard content update (e.g., new blog post). |
| Technical Performance | Google PageSpeed Insights | Score > 90 for Mobile on the homepage. |
| Brand Reach | Organic Search Traffic | 20% increase in organic traffic within 6 months post-launch. |

## Risk Analysis & Mitigation

| Risk | Impact | Mitigation Strategy |
|:---|:---|:---|
| **Admin/Karyawan Panel Breach** | High | Unauthorized data access, region leak. | - Strong password (email + min 8 chars). 2FA for Super Admin. Region middleware + policy checks. Rate limiting per guard (super-admin / wilayah / karyawan terpisah). Pisah URL `/super-admin` vs `/wilayah` mengurangi surface enumeration. |
| **Karyawan Data Leak Across Regions** | High | Employee sees other region's data. | - Enforce `region_id` scoping at query + policy layer. Tests for isolation. Audit logs per region. |
| **Fake GPS Absensi** | Medium | Fraudulent attendance. | - Geofence validation server-side, selfie liveness check, timestamp + device info logging. |
| **Data Loss on S3** | High | Loss of all company media assets. | - Enable versioning on the AWS S3 bucket. - Implement a restrictive IAM policy for the application user. - Regularly back up bucket metadata. |
| **Performance Bottlenecks** | Medium | Poor user experience, high bounce rate. | - Implement aggressive caching strategies (page, query, config) using Redis. - Use a Content Delivery Network (CDN) for assets. - Optimize database queries and use eager loading. |
| **Vendor Lock-in (AWS)** | Low | Increased costs or difficulty migrating. | - Use Laravel's abstract `Filesystem` driver. This allows switching the storage provider (e.g., to DigitalOcean Spaces) with minimal code changes. |

## Constraints & Assumptions

*   **Constraint:** Domain BBWS Pompengan Jeneberang: **Pusat di Makassar** + Wilayah di Kabupaten/Kota se-Sulsel (24 wilayah). Model multi-tenancy per Kantor Wilayah via `region_id`.
*   **Constraint:** RBAC 3 roles: Super Admin Pusat (Makassar), Admin Wilayah/Wilayah (per Kantor), Karyawan (NIK login, own-data-only).
*   **Constraint:** Tiap wilayah wajib punya **N titik proyek** (contoh Bendungan A, Jembatan B — masing-masing **nama titik + lat/lng + radius absen meter**) yang di-input admin (Super Admin untuk semua wilayah, Admin Wilayah untuk **N titik di wilayahnya sendiri**). Radius default 200m, range 50–1000m **per titik**, validasi absensi server-side terhadap geofence titik terdekat karyawannya (`min(distance) <= radius_m(titik)`). **Absen di luar semua titik ditolak (tidak tercatat, tidak bisa pakai Love).**
*   **Constraint:** Love System: 4/bulan default, reset bulanan, total fleksibel Super Admin (1–10), pakai Love hanya untuk late dalam radius + dokumen + approval 1 level Admin Wilayah (hari yang sama).
*   **Constraint:** Admin Wilayah can view all cabang but write only own cabang. Karyawan cannot view other employees.
*   **Constraint:** Stack Laravel 13, React 19, Inertia v2, Tailwind v4, Vite 7, MySQL 8.4 LTS, AWS S3, PWA — all latest stable.
*   **Assumption:** Daftar 24 Kab/Kota Sulsel + koordinat kantor awal disediakan BBWS PJ (atau admin input manual via map picker saat setup).
*   **Assumption:** Data Lengkap HR karyawan per cabang disediakan atau di-input Admin Wilayah.
*   **Assumption:** Karyawan device support GPS + camera; browser support PWA install; ada internet di kantor untuk validasi GPS.
*   **Assumption:** SMTP + Google Analytics credentials disediakan untuk notifikasi & dashboard.

## Out of Scope

The initial release of BBWS Pompengan Jeneberang will **NOT** include:
*   Multi-language support.
*   E-commerce functionality.
*   Public user registration (only karyawan NIK-based).
*   Automated CI/CD pipeline setup and configuration (but GitHub Actions template provided).
*   Integrated email marketing/newsletter system (only transactional email for cuti/pengumuman).