# PRD: BBWS Pompengan Jeneberang

## Executive Summary & Product Vision

This document outlines the Product Requirements for **BBWS Pompengan Jeneberang**, aplikasi profil dinas + HR portal dengan admin berjenjang dan PWA karyawan.

**Konteks domain:** Pusat di **Makassar**, cabang di **Kabupaten/Kota se-Sulawesi Selatan** (21 Kabupaten + 3 Kota = 24 wilayah). Pusat mengelola semua cabang; tiap cabang (Admin Wilayah) mengelola karyawannya sendiri termasuk input **lokasi kantor cabang** dan **radius absen** untuk validasi GPS+selfie.

The vision is to provide a dynamic, high-performance, and secure public-facing corporate website where all content is easily manageable through a comprehensive admin panel, plus a regional HR management + mobile PWA for employees. The system will leverage a modern monolithic architecture using Laravel 13 and React 19 with Inertia.js v2 to deliver a seamless user experience for both public visitors, admin pusat/cabang, and employees.

## Problem Statement & Target Users

**Problem:** Traditional static company websites are difficult and time-consuming for non-technical staff to update. This leads to outdated information, poor user engagement, and missed business opportunities. Existing CMS solutions can be overly complex or lack specific features needed for a corporate profile. Additionally, regional organizations need to manage employee data per Kabupaten/Kota with distinct admin scopes and provide mobile HR self-service for employees.

**Target Users:**
*   **Super Admin Pusat (Makassar):** Admin pusat di Makassar — mengelola semua cabang Kabupaten/Kota, akun Admin Wilayah, lokasi kantor & radius absen tiap cabang, konten global, broadcast pengumuman.
*   **Admin Wilayah / Cabang (Kabupaten/Kota):** Admin per Kantor Cabang BBWS PJ di Kabupaten/Kota se-Sulsel — input dan kelola data karyawan Lengkap HR untuk cabangnya sendiri, **input/edit lokasi kantor cabang (lat/lng) dan radius absen (meter)** untuk validasi absensi karyawannya, view data cabang lain read-only.
*   **Karyawan BBWS PJ:** ASN/non-ASN di pusat maupun cabang, login via NIK + password di mobile PWA untuk absensi GPS+selfie (cek jarak ke kantor cabangnya), cuti berjenjang, pengumuman, profil, **4 Love/bulan (reset 1st 00:00 WITA, fleksibel total love diatur Super Admin, pakai Love untuk excuse terlambat dalam radius dengan dokumen).**

## System Scope & User Roles

The system is composed of two primary components: a regional admin dashboard multi-tenant per Kantor Cabang (Makassar Pusat + 23/24 Cabang Kabupaten/Kota se-Sulsel), and a mobile PWA for employees. Content & HR management scoped per cabang dengan oversight pusat Makassar.

| Role | Description | Permissions |
|:---|:---|:---|
| **Super Admin Pusat (Makassar)** | Admin pusat di Makassar. | - Full CRUD on all cabang (Kabupaten/Kota se-Sulsel) + semua data karyawan. - Input/edit **lokasi kantor & radius absen tiap cabang** (lat/lng/radius_m). - Manage akun Admin Wilayah. - Broadcast pengumuman pusat. - Full content, settings, analytics. |
| **Admin Wilayah / Cabang (Kab/Kota)** | Admin per Kantor Cabang BBWS PJ di Kabupaten/Kota se-Sulsel. Scope = region_id. | - CRUD data karyawan Lengkap HR **only for own cabang** (read cabang lain, write own). - **Input/edit lokasi kantor cabang sendiri (lat, lng, radius_m)** untuk geofence absensi karyawannya. - Approve cuti level wilayah. - Kirim pengumuman wilayah. - View attendance own cabang. |
| **Karyawan** (Employee) | ASN/non-ASN pusat/cabang, login NIK+password, mobile PWA. Strict own-data only. | - View/edit own profile (limited). - Absensi GPS+selfie — divalidasi jarak ke **lokasi kantor cabangnya** (radius config, di luar radius ditolak, tidak tercatat). - Ajukan cuti berjenjang. - **4 Love/bulan (reset bulanan, total fleksibel Super Admin): jika terlambat & masih dalam radius, bisa ajukan dokumen/alasan pakai 1 Love untuk excuse late → approval 1 level Admin Cabang.** - View pengumuman (pusat + cabangnya). |

## Functional Requirements

### Admin Dashboard (Super Admin & Admin Wilayah)
*   **FR-10: Secure Authentication (Admin):** Dedicated login for Super Admin & Admin Wilayah. Implements session-based auth via Laravel Sanctum 4.x with role & region scoping. Admin URL obfuscated.
*   **FR-11: Main Dashboard:** Overview with analytics, recent contact submissions, quick links. Super Admin sees all regions; Admin Wilayah sees filtered stats for own region + read-only global stats.
*   **FR-12: Page Content Management:** WYSIWYG (TinyMCE) for static pages.
*   **FR-13: Portfolio Management:** Full CRUD for portfolio projects.
*   **FR-14: Team Management:** Full CRUD for team member profiles (public team display).
*   **FR-15: Blog Management:** Full CRUD for blog posts with categories/tags/status.
*   **FR-16: Testimonial Management:** Full CRUD with approved status.
*   **FR-17: Contact Submission Viewer:** List, read, archive contact submissions (scoped view for Admin Wilayah if needed).
*   **FR-18: Media Library:** Centralized S3 media management.
*   **FR-19: Advanced SEO Management:** Override meta/OG tags per page/post.
*   **FR-20: Content Versioning:** History & rollback for critical pages.
*   **FR-21: Global Settings Management:** Site-wide settings termasuk **Jam Kerja Global** (jam_masuk, jam_pulang, toleransi_late_menit, hari_kerja Senin-Jumat). Only Super Admin Pusat (Makassar) can edit; Admin Wilayah read-only.

### Karyawan Mobile PWA (Employee Self-Service)
*   **FR-22: Karyawan Authentication (NIK + Password):** Dedicated mobile PWA login via NIK + password. Sanctum session with `employee` guard, rate-limited, supports PWA install. Karyawan only accesses own data.
*   **FR-23: Karyawan Profile (View & Limited Edit):** View own Lengkap HR profile (NIK, NIP, golongan, jabatan, unit kerja, status, foto, kontak). Can edit limited fields (foto, kontak, password). Cannot view other employees.
*   **FR-24: Admin Wilayah - Employee Data Management (Lengkap HR):** Admin Wilayah inputs/manages employee data for own Kantor Cabang only (read all, write own). Fields: NIK (UK), NIP, name, golongan, jabatan, unit kerja, status kepegawaian, foto (S3), kontak, dokumen. Super Admin manages all cabang + akun Admin Wilayah. Isolasi via `region_id`.
*   **FR-25: Absensi GPS + Selfie (Mobile PWA, Geofence Kantor):** Karyawan check-in/out via PWA dengan validasi GPS terhadap **lokasi kantor cabangnya** (lat/lng + radius_m yang di-input admin). Radius configurable per kantor (mis. 100–500m). Selfie upload S3. Records: timestamp, lat/lng, selfie_url, status on-time/late + jarak_m ke kantor (tidak ada out_of_range — di luar radius ditolak). Admin Wilayah views attendance cabang sendiri; Super Admin views all cabang.
*   **FR-26: Cuti Berjenjang (Leave Workflow):** Karyawan ajukan cuti (tanggal, jenis, alasan, dokumen). Approval berjenjang: Atasan Langsung → Admin Wilayah (Cabang) → Super Admin Pusat (Makassar). Status: pending/approved_level1/approved/rejected. Notifikasi via app + email.
*   **FR-27: Pengumuman (Broadcast Pusat + Cabang):** Super Admin Pusat (Makassar) broadcast ke semua cabang; Admin Cabang kirim targeted ke cabangnya sendiri. Karyawan views pengumuman relevant (pusat + cabangnya) in PWA inbox with read status.
*   **FR-28: Region/Cabang Management (Kantor BBWS PJ se-Sulsel):** Super Admin CRUD Kantor (Pusat Makassar + Cabang Kab/Kota se-Sulsel) dengan **form input lokasi kantor (lat, lng via map picker) dan radius absen (meter)**. Admin Wilayah dapat edit lokasi & radius kantornya sendiri (own region) dan view cabang lain read-only. Tiap kantor punya geofence untuk validasi absensi karyawannya. Validasi radius 50–1000m.
*   **FR-29: PWA Mobile Experience:** Employee PWA is installable (manifest + service worker), offline-capable for viewing cached profile/pengumuman, responsive 320px+, touch 44px, push notification ready. Tampilkan jarak ke kantor & status dalam radius saat absen.
*   **FR-30: Jam Kerja Global (Aturan Masuk/Pulang):** Sistem punya **1 aturan jam kerja global** untuk semua Kantor Cabang BBWS Pompengan Jeneberang, dikelola Super Admin via Global Settings. Field: `jam_masuk` (default 07:30 WITA), `jam_pulang` (default 16:00 WITA), `toleransi_late_menit` (default 15), `hari_kerja` (Senin-Jumat). Logic absensi: `timestamp check-in <= jam_masuk + toleransi → on_time`, `> jam_masuk+toleransi → late`, `type=out sebelum jam_pulang → early_leave` (optional). Validasi waktu server-side (WITA, Asia/Makassar). Admin Wilayah & Karyawan read-only.
*   **FR-31: Love System — 4 Hati / Bulan, Fleksibel, Dalam Radius, 1 Level Admin Cabang:** Setiap karyawan punya **4 Love/bulan** (default, reset `1st 00:00 WITA` tiap bulan, `love_sisa = love_max`). **Total Love fleksibel**: `global_settings.love_max_default` (1–10, default 4) diatur Super Admin Pusat (berlaku bulan depan, log). **Aturan pakai Love**: Jika `absensi.status=late` **dan** `dalam radius kantor` (distance ≤ radius_m, selfie valid) maka karyawan bisa **ajukan 1 Love Claim** dalam **hari yang sama** setelah late dengan dokumen/alasan via PWA. **Approval 1 level: Admin Cabang** (admin wilayah own region) approve/reject; approve → `love_sisa-1`, `attendances.status` → `excused_love` (dianggap on_time di rekap, tidak hitung late), reject → tetap `late`. Di luar radius **tidak bisa pakai Love** (absen ditolak 422, tidak tercatat, tidak ada claim). Jika `love_sisa=0` → late tidak bisa di-excuse (tetap late, notifikasi Admin). Tampilan PWA: 4 dot gold `#FCB833` (terisi/abu), sisa `3/4`, tombol `Gunakan Love` saat ada late pending claim, history Love Claims.

## Non-Functional Requirements

| Category | Requirement | Metric / Target |
|:---|:---|:---|
| **Performance** | Fast Page Loads | - LCP < 2.5s, FCP < 1.8s, TTFB < 200ms cached. - PWA Lighthouse >90 mobile, offline cache for karyawan. |
| **Security** | System Integrity | - HTTPS enforced. OWASP Top 10 protection. Admin URL obfuscated. Rate limiting on all logins (admin + karyawan NIK) & contact form. Region isolation middleware (write own region only). |
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
| User Engagement | Average Time on Page | > 90 seconds on key pages (Services, Portfolio). |
| Lead Generation | Contact Form Submissions | ≥ 10 valid submissions per month after launch. |
| Admin Efficiency | Content Update Time | < 5 minutes for a standard content update (e.g., new blog post). |
| Technical Performance | Google PageSpeed Insights | Score > 90 for Mobile on the homepage. |
| Brand Reach | Organic Search Traffic | 20% increase in organic traffic within 6 months post-launch. |

## Risk Analysis & Mitigation

| Risk | Impact | Mitigation Strategy |
|:---|:---|:---|
| **Admin/Karyawan Panel Breach** | High | Unauthorized data access, region leak. | - Strong password (NIK + min 8 chars). 2FA for Super Admin. Region middleware + policy checks. Rate limiting per guard. |
| **Karyawan Data Leak Across Regions** | High | Employee sees other region's data. | - Enforce `region_id` scoping at query + policy layer. Tests for isolation. Audit logs per region. |
| **Fake GPS Absensi** | Medium | Fraudulent attendance. | - Geofence validation server-side, selfie liveness check, timestamp + device info logging. |
| **Data Loss on S3** | High | Loss of all company media assets. | - Enable versioning on the AWS S3 bucket. - Implement a restrictive IAM policy for the application user. - Regularly back up bucket metadata. |
| **Performance Bottlenecks** | Medium | Poor user experience, high bounce rate. | - Implement aggressive caching strategies (page, query, config) using Redis. - Use a Content Delivery Network (CDN) for assets. - Optimize database queries and use eager loading. |
| **Vendor Lock-in (AWS)** | Low | Increased costs or difficulty migrating. | - Use Laravel's abstract `Filesystem` driver. This allows switching the storage provider (e.g., to DigitalOcean Spaces) with minimal code changes. |

## Constraints & Assumptions

*   **Constraint:** Domain BBWS Pompengan Jeneberang: **Pusat di Makassar** + Cabang di Kabupaten/Kota se-Sulsel (24 wilayah). Model multi-tenancy per Kantor Cabang via `region_id`.
*   **Constraint:** RBAC 3 roles: Super Admin Pusat (Makassar), Admin Wilayah/Cabang (per Kantor), Karyawan (NIK login, own-data-only).
*   **Constraint:** Tiap Kantor Cabang wajib punya **lokasi kantor (lat/lng) + radius absen (meter)** yang di-input admin (Super Admin untuk semua, Admin Cabang untuk cabangnya sendiri). Radius default 200m, range 50–1000m, validasi absensi server-side terhadap geofence kantor karyawannya. **Absen di luar radius ditolak (tidak tercatat, tidak bisa pakai Love).**
*   **Constraint:** Love System: 4/bulan default, reset bulanan, total fleksibel Super Admin (1–10), pakai Love hanya untuk late dalam radius + dokumen + approval 1 level Admin Cabang (hari yang sama).
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