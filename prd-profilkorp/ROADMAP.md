# ROADMAP.md: BBWS Pompengan Jeneberang

## Phased Delivery Plan

|:---|:---|:---|:---|

**Timeline Disclaimer:** This roadmap assumes a team of **2 developers** (1 backend-focused, 1 frontend-focused). Adjust phase durations proportionally: for 1 developer, multiply by ~1.8; for 3 developers, multiply by ~0.7. Actual timelines may vary based on design approval cycles, client feedback, and scope changes.

---

## MVP Feature List

### P0: Must Have for Launch

These features are **critical** for the initial release. Without them, the application cannot go live.

|:---|:---|:---|:---|

### P1: Should Have Within 1 Month Post-Launch

These features enhance the admin experience and public engagement but are not blocking for launch.

|:---|:---|:---|:---|

### P2: Nice to Have for Future Releases

These features are valuable but can be deferred to post-launch iterations.

|:---|:---|:---|:---|

---

## Milestones

|:---|:---|:---|:---|

---

## Dependencies

### External Dependencies

These are third-party services, accounts, and credentials required for the project to function.

|:---|:---|:---|:---|

### Internal Dependencies

These are deliverables and artifacts that must be completed before or in parallel with development.

|:---|:---|:---|:---|

---

## Risks & Mitigation

|:---|:---|:---|:---|

---

## Technical Milestones & Deliverables by Phase

### Phase 1: Foundation & HR Core (Weeks 1–4)

**Week 1:**
- Laravel 13 + React 19 + Inertia v2 + Vite 7 environment fully configured (PHP 8.4, Node 22)
- MySQL 8.4 LTS database created with initial schema (regions, employees, public tables) + ERD approved
- Tailwind CSS v4 setup and design system + PWA design tokens established
- Git repository with branching strategy
- Development, staging, and production env vars documented (including SUPER_ADMIN_PATH, WILAYAH_PATH, KARYAWAN_PATH, VAPID keys, S3 paths)

**Week 2–3:**
- Homepage component with hero banner, featured services section, latest blog posts, and testimonials carousel
- About Us page with editable content structure
- Services listing page and individual service detail pages
- Responsive design tested on mobile, tablet, desktop
- Basic SEO structure (meta tags, Open Graph) implemented

**Week 4:**
- All Phase 1 pages deployed to staging environment
- Client review and feedback incorporation
- Performance baseline established (Lighthouse scores)
- Initial documentation (setup guide, component library)

---

### Phase 2: Admin Dashboard & Content Management (Weeks 5–8)

**Week 5:**
- Multi-guard login (Super Admin email + Admin Wilayah email, **Karyawan email** — all email) with Sanctum 4.x (`super_admin`/`wilayah`/`karyawan`), Opsi B `SUPER_ADMIN_PATH`/`WILAYAH_PATH`/`KARYAWAN_PATH`
- **Role-scoped dashboard breakdown per Titik** (`Dashboard.jsx` `countsPerSite` + `totalTanpaTitik` + `siteFilter` `Semua titik`, cards Link `${base}/regions/{id}/sites/{id}`) + `getAdminBase(url)` — Super Admin all, Admin Wilayah own region
- Navigation + layout with role gates, **region + titik** middleware + policies (`office_location_id` scoping)
- Session management + logout per guard + rate limiting per guard 5/15min

**Week 6–7:**
  - **Regions CRUD + N Titik Proyek** (`MAX_SITES=20`, dedicated `GET /regions/{region}/sites/{site}` `Admin/SiteDetail` Leaflet draggable+circle per titik + anggota per titik + kandidat `NULL`) Super Admin all, Admin Wilayah own region + **Employees per Titik** (`office_location_id` 1=1, kolom/filter `Titik Proyek` Link SiteDetail + `__null` tanpa titik) + Admin Wilayah assignment 1 titik per karyawan
  - Employee & attendance management (per-titik)
  - Employee Management (Lengkap HR) — Admin Wilayah CRUD own region (read all indicator) incl. `office_location_id` select, NIK/NIP validation, foto S3, pindah via SiteDetail `Pindah`
- Form validation + **region + titik** isolation + own-data + **`isWithinAssignedSite` vs any-site** policy tests

**Week 8:**
- Admin panel deployed to staging
- Client training: Super Admin vs Admin Wilayah workflows
- Bug fixes + UX refinements + region scoping audit

---

### Phase 3: Advanced Features & Media Handling (Weeks 9–11)

**Week 9:**
- AWS S3 integration with Laravel Storage facade
- Media Library interface for uploading, browsing, and deleting files
- Transactional upload handling with error recovery
- File type validation and size limits

**Week 10:**
- Advanced SEO management editor (meta title, description, Open Graph overrides)
- Content versioning system with version history UI
- Rollback functionality for critical pages
- Global Settings management (company info, logo, social links)

**Week 11:**
- All Phase 3 features deployed to staging
- Performance testing and optimization
- Security audit of S3 integration and file handling

---

### Phase 4: HR Features & Polish (Weeks 12–14)

**Week 12:**
- Portfolio/Projects page with filtering and detail views
- Team page with member profiles and photos
- Blog listing page with pagination and category filtering
- Testimonials section with approval workflow

**Week 13:**
- Contact Us page with interactive map and contact form
- Contact form submission handling with email notifications
- Contact submission viewer in admin panel
- Form validation and CAPTCHA/rate limiting

**Week 14:**
- All public pages deployed to staging
- End-to-end testing across all features
- Performance optimization and Lighthouse score improvements
- Client acceptance testing

---

### Phase 5: Karyawan Mobile PWA (Weeks 12–15)

**Week 12–13:**
- Karyawan PWA: **email** login (NIK→email migrated), bottom nav, profile view/edit + **card titik assigned** (`office_location` 201 ex) or NULL placeholder, foto upload S3 `/attendance/...`, `loadRegions/loadEmployees` `_shared.js` sync
  - Absensi: GPS+selfie capture, **geofence `isWithinAssignedSite` only** (Haversine `dist <= radius_m(assigned)` — bukan nearest dari N) server-side, **`NULL assigned 422` + `!within assigned 422`**, S3 selfie, status on_time/late/early_leave (di luar assigned ditolak 422), **`jarak/radius` + badge `Dalam/Di luar radius titik assigned` + disabled `!inRadius‖tanpaTitik`**, offline queue server re-validasi
- Cuti: form ajukan, list status, detail timeline, berjenjang approval UI per level

**Week 14–15:**
- Pengumuman: Super Admin broadcast + Admin Wilayah targeted per region, karyawan inbox with read/unread + pinned, attachment S3
- **Love 4 hati:** gate `isWithinAssignedSite` (`sisa>0 && Dalam` + bulan sama), tanpa titik disabled, Approve disabled `!inRadius‖sisa==0` — Karyawan `Love.jsx` + Admin `Love.jsx` per Titik (kolom `Titik Proyek` + `jarak/radius` + Link SiteDetail)
- PWA: manifest.json (`start_url=/karyawan`), service-worker.js (cache profile+pengumuman+offline queue), install prompt, VAPID push ready, Lighthouse PWA >90, 44px touch targets — **per-titik headers `Ditugaskan di:` (Dashboard/Absensi/Rekap)**

### Phase 6: Testing, Security Hardening & Deployment (Weeks 16–18)

**Week 16:**
- Unit tests >80% inc. RBAC, **region + titik scoping** (write own 403 + **cross-`office_location_id` 403** + **`isWithinAssignedSite` only vs any-site** + **`NULL 422`** + **`N≤20 last delete 422`**), own-data 403, **geofence assigned-only** (`distance > radius_m(assigned)` 422), NIK+email uniqueness, love 4/month per titik assigned
- Integration tests for all Karyawan PWA flows (**absensi tanpa titik 422 / di luar assigned 422 / Dalam `distance<=radius` OK**, **love bulan sama + `!inRadius` disabled**, cuti berjenjang per-titik, pengumuman, Rekap calendar)
- Frontend PWA tests with React Testing Library + PWA audit (per-titik banner `Ditugaskan di:` + disabled states)
- OWASP Top 10 + **region+titik** isolation audit, **rate limit per guard** 5/15min per IP tests
- Performance load test: 1,000 public + 500 PWA concurrent, TTFB <200ms cached

**Week 17:**
- Production VPS setup (Ubuntu 24.04, PHP 8.4, Node 22, MySQL 8.4, Redis 7)
- DB migration to prod + seed regions + Super Admin
- SSL + HTTPS + HSTS + S3 bucket versioning + CloudFront
- PWA deploy: manifest + SW + VAPID keys

**Week 18:**
- Live deployment + smoke testing all roles
- Monitoring (Sentry, UptimeRobot) + S3 log rotation
- Client handoff: Super Admin, Admin Wilayah per kabupaten, Karyawan PWA install training + docs

---

## Success Criteria & Go-Live Checklist

### Functional Completeness
- [ ] All P0 features inc. Karyawan PWA (absensi, cuti, pengumuman, profile) implemented and tested
- [ ] Karyawan PWA fully responsive mobile-first (320px+) + PWA installable
- [ ] Admin dashboard role-scoped (Super Admin vs Admin Wilayah) with region isolation verified
- [ ] Employee Lengkap HR management (read all, write own region) operational
- [ ] Contact form working with email notifications
- [ ] Media Library + attendance selfie S3 integration operational

### Performance & Security
- [ ] Lighthouse >90 mobile homepage + PWA score >90 for /karyawan
- [ ] TTFB <200ms cached, LCP <2.5s, FCP <1.8s
- [ ] HTTPS + HSTS enforced on all pages + PWA secure context
- [ ] OWASP Top 10 addressed + region isolation + own-data 403 verified
- [ ] Rate limiting terpisah per guard: super-admin (`/super-admin/login` 5/15min), wilayah (`/wilayah/login` 5/15min), karyawan (`/karyawan/login` 5/15min), contact form, absensi
- [ ] Tiga URL obfuscated terpisah (Opsi B): `SUPER_ADMIN_PATH` (bukan `/admin`), `WILAYAH_PATH` (bukan `/admin`), `KARYAWAN_PATH` (bukan `/karyawan` di prod) — tidak cross-login
- [ ] Geofence validation server-side tested (ditolak di luar radius)

### Testing & Quality
- [ ] Backend coverage >80% inc. RBAC, region write 403, karyawan own-data 403, geofence, NIK unique, cuti berjenjang transitions
- [ ] All critical journeys inc. karyawan PWA (absensi GPS+selfie, cuti 3 levels, pengumuman read) tested
- [ ] No critical/high bugs, PWA offline queue tested
- [ ] PSR-12, Code documented, policies reviewed
- [ ] 9 PRD docs synced (PRD, REQUIREMENTS, ARCHITECTURE, DATABASE, API, etc.)

### Deployment & Operations
- [ ] Production environment fully configured
- [ ] Database backups automated and tested
- [ ] Monitoring and alerting active
- [ ] Incident response procedures documented
- [ ] Client training completed
- [ ] Support handoff documentation provided

---

## Post-Launch Roadmap (Future Phases)

### Phase 7: Post-Launch Enhancements (Weeks 19–22)

- Implement 2FA for Super Admin + Admin Wilayah + Karyawan
- Add Google Analytics + attendance analytics per region
- Optimize Redis caching + CDN for PWA assets
- Implement automated DB + S3 backups with restore tests
- Add IP whitelisting for admin wilayah

### Phase 8: Advanced Features (Weeks 23–26)

- Content + announcement scheduling (publish at future dates)
- Advanced analytics + attendance reports per region
- Push notification campaigns for pengumuman
- API for third-party HRIS integration
- Automated CI/CD + PWA auto-update flow

---

## Resource Allocation

|:---|:---|:---|

---

## Communication & Governance

- **Weekly Sprint Meetings:** Every Monday, 10:00 AM. Review progress, blockers, and upcoming tasks.
- **Bi-weekly Client Check-ins:** Every other Wednesday. Demo features, gather feedback, discuss scope changes.
- **Change Control Process:** All scope changes require written approval from client and project manager. Impact on timeline documented.
- **Status Reporting:** Weekly status report to client (progress %, completed tasks, risks, next week's plan).
- **Documentation:** All decisions, architecture choices, and technical specifications documented in shared repository.

---

## Assumptions & Constraints

**Assumptions:**
- Client provides initial content + **24 wilayah** + **N titik proyek** (Bendungan A/Jembatan B) lat/lng/radius per titik + **mapping 1 karyawan=1 titik** by end of Week 1 — at least 1 titik per wilayah, **`MAX_SITES=20`**, `regionId==office_location.region_id`.
- Client provides Lengkap HR employee data **per titik** (`office_location_id` 1=1, `NULL` invalid untuk absen) or admin wilayah input via **SiteDetail dedicated page** + **Employees per Titik** UI.
- AWS account + S3 bucket (with folders for media, **`attendance/{region}/{emp}/{date}/`** selfies, **`love-claims/...`**) configured before Phase 3 — paths per titik assigned.
- Karyawan devices support GPS + camera; modern browser supports PWA install + VAPID push; PWA **320px+** required (absen disabled `!inRadius‖tanpaTitik` via `isWithinAssignedSite`).
- No major scope changes after Phase 1 kickoff (HR fields + **per-titik `office_location_id`** + cuti/love gate `isWithinAssignedSite` + `love_max 1–10 default 4` frozen).

**Constraints:**
- Technology stack (Laravel 13, React 19, Inertia v2 Leaflet 1.9.4, Tailwind v4 (`@theme`), Vite 8.2.2, MySQL 8.4 LTS, AWS S3, PWA `vite-plugin-pwa` 1.3 `start_url=/karyawan`) is fixed — `app-BINGqzgl.js` 519kB gzip 134.89kB.
- RBAC 3 roles required: Super Admin (`super_admin` region NULL) + Admin Wilayah per region (`wilayah` guard own `region_id` + **assign 1 titik per karyawan**) + Karyawan (**email login** own-data-only + **absen/Love hanya titik assigned**, `NULL 422`).
- Multi-tenancy per **Kabupaten/Kota + Titik Proyek** via `region_id` + **`office_location_id` (1 karyawan=1 titik, SetNull on delete titik, N≤20, last delete 422)** — Admin Wilayah write own region+titik only (read all wilayah lain), Karyawan only own `employee_id`+own assigned titik (`isWithinAssignedSite` only, bukan `isWithinAnySite`).
- Opsi B: **3 URL obfuscated pisah** `SUPER_ADMIN_PATH`/`WILAYAH_PATH`/`KARYAWAN_PATH` (`super_admin|admin|wilayah.sites.show` per titik + `getAdminBase(url)`), guard terpisah tidak cross-login; `/admin` legacy alias, prod `/super-admin-<hash>`/`<hash>`.
- Dedicated page per Titik: **`GET /regions/{region}/sites/{site}`** x3 prefix fixed — all existing prefixes verified `route:list` + `curl 200` untuk `101/102/201/202/301`.
- Initial release includes PWA mandatory — **per-titik headers `Ditugaskan di:` + `MOCK_KARYAWAN_ID=1` Andi 201** + `_shared.js` `DUMMY_REGIONS`/`DUMMY_EMPLOYEES` LS `bbws_mock_*_v3`; native app is post-launch.
- Team size 2 devs; timeline scales with size changes.