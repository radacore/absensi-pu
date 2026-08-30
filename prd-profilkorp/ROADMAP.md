# ROADMAP.md: BBWS Pompengan Jeneberang

## Phased Delivery Plan

| Phase | Duration | Goals | Key Deliverables |
|:---|:---|:---|:---|
| **Phase 1: Foundation & HR Core** | 4 weeks | Establish project infrastructure, database schema (including regions), and core public pages. | Laravel 13 + React 19 + Inertia v2 setup, Regions scaffolding, Homepage, About Us, Services pages, Tailwind v4 styling. |
| **Phase 2: Admin Dashboard & Content Management** | 4 weeks | Build RBAC auth (Super Admin vs Admin Wilayah) and core CRUD (N titik proyek). | Multi-guard Sanctum, role+region middleware, Admin login, Dashboard role-scoped, Page/Blog/Portfolio/Team CRUD, Regions CRUD **+ N Titik Proyek per wilayah** (Super Admin all, Admin Wilayah own region), WYSIWYG. |
| **Phase 3: Advanced Features & Media Handling** | 3 weeks | Implement AWS S3 integration, SEO management, content versioning. | Media Library S3, Advanced SEO, Content versioning, Global Settings (Super Admin only). |
| **Phase 4: HR Features & Polish** | 3 weeks | Complete remaining public pages, contact form, testimonials. | Portfolio/Projects, Team, Blog listing, Contact form with notifications, Testimonials. |
| **Phase 5: Karyawan Mobile PWA (HR Self-Service)** | 4 weeks | Build employee PWA: auth NIK, profile, absensi, cuti, pengumuman. | Karyawan login (NIK+password), Profile own-data, Absensi GPS+selfie (geofence), Cuti berjenjang (3 levels), Pengumuman inbox, PWA manifest+SW + offline queue, S3 paths. |
| **Phase 6: Testing, Security Hardening & Deployment** | 2 weeks | Comprehensive testing, security audit (RBAC+region isolation), performance, deploy. | Unit & integration tests (>80% inc. region scoping & own-data 403 tests), OWASP Top 10, Rate limit & geofence tests, Load test 1000 public + 500 PWA, Live deployment. |

**Timeline Disclaimer:** This roadmap assumes a team of **2 developers** (1 backend-focused, 1 frontend-focused). Adjust phase durations proportionally: for 1 developer, multiply by ~1.8; for 3 developers, multiply by ~0.7. Actual timelines may vary based on design approval cycles, client feedback, and scope changes.

---

## MVP Feature List

### P0: Must Have for Launch

These features are **critical** for the initial release. Without them, the application cannot go live.

| Feature | Reference | Status | Notes |
|:---|:---|:---|:---|
| RBAC Authentication (Admin+Karyawan — Opsi B Pisah URL) | FR-10/FR-22 | Core | Super Admin (`SUPER_ADMIN_PATH` /super-admin + guard super_admin) + Admin Wilayah (`WILAYAH_PATH` /wilayah + guard wilayah) + Karyawan (`KARYAWAN_PATH` /karyawan) — email login semua role, Sanctum multi-guard terpisah tidak cross-login, rate limiting per guard. |
| Admin Dashboard (Role-Scoped) | FR-11 | Core | Overview scoped by region; Super Admin all regions, Admin Wilayah own region. |
| Region Management — N Titik Proyek | FR-28/FR-29 | Core | Super Admin CRUD Kabupaten/Kota + **N titik proyek per wilayah** (tiap titik nama/lat/lng/radius 50–1000); Admin Wilayah tambah/edit N titik own region (Bendungan A, Jembatan B). |
| Employee Data Management | FR-24 | Core | Admin Wilayah CRUD Lengkap HR per region (read all, write own). |
| Page Content Management | FR-12 | Core | WYSIWYG editor for static pages. |
| Portfolio Management | FR-13 | Core | Full CRUD for projects. |
| Team Management | FR-14 | Core | Full CRUD for team members. |
| Blog Management | FR-15 | Core | Full CRUD for blog posts with categories/tags. |
| Contact Submission Viewer | FR-17 | Core | View and archive contact form submissions. |
| Media Library (AWS S3) | FR-18 | Core | Upload, delete, and browse media on S3. |
| Global Settings | FR-21 | Core | Manage company name, logo, contact info, social links (Super Admin only). |
| HTTPS & Security Basics | NFR-Security | Core | HTTPS, CSRF, rate limiting terpisah per guard (super-admin / wilayah / karyawan), region isolation middleware, tiga URL obfuscated pisah. |
| Karyawan PWA - Auth & Profile | FR-22/FR-23 | Core | Email login (`KARYAWAN_PATH`), view own profile, edit limited fields, PWA installable. Guard terpisah dari super-admin/wilayah. |
| Karyawan PWA - Absensi (Geofence N Titik) | FR-25 | Core | GPS+selfie check-in/out with **geofence ke titik proyek terdekat** (N titik per wilayah, Haversine), S3 selfie + `office_location_id`. |
| Karyawan PWA - Cuti | FR-26 | Core | Ajukan cuti + berjenjang approval (3 levels) with notifications. |
| 
| Karyawan PWA - Pengumuman | FR-28 | Core | Inbox global+region with read status. |
| PWA Infrastructure | FR-30 | Core | Manifest, service worker, offline cache, 44px touch, push ready. |

### P1: Should Have Within 1 Month Post-Launch

These features enhance the admin experience and public engagement but are not blocking for launch.

| Feature | Reference | Status | Notes |
|:---|:---|:---|:---|
| Testimonials Management | FR-16 | Enhancement | Full CRUD with approval status. |
| Advanced SEO Management | FR-19 | Enhancement | Dedicated SEO editor for meta overrides. |
| Content Versioning | FR-20 | Enhancement | Version history and rollback for critical pages. |
| Google Analytics Integration | FR-11 | Enhancement | Embedded analytics widgets on admin dashboard. |
| 2-Factor Authentication (2FA) | Security | Enhancement | Additional security layer for admin login. |
| Performance Optimization | NFR-Performance | Enhancement | CDN integration, Redis caching, query optimization. |

### P2: Nice to Have for Future Releases

These features are valuable but can be deferred to post-launch iterations.

| Feature | Reference | Status | Notes |
|:---|:---|:---|:---|
| Multi-language Support | Out of Scope | Future | Not required for initial release. |
| Email Newsletter System | Out of Scope | Future | Integrated email marketing. |
| Advanced Analytics Dashboard | Enhancement | Future | Custom reports, conversion tracking. |
| IP Whitelisting for Admin | Security | Future | Restrict admin access by IP address. |
| Automated Backups | Infrastructure | Future | Automated database and S3 backups. |
| API for Third-Party Integration | Enhancement | Future | RESTful API for external systems. |

---

## Milestones

| Milestone | Phase | Target Date | Deliverables |
|:---|:---|:---|:---|
| **Project Setup & Architecture** | 1 | Week 1 | Laravel 13 + React 19 + Inertia v2 + Vite 7 + Tailwind v4, database schema (MySQL 8.4) with regions/employees, Git repo, workflow documented. |
| **HR Core Foundation** | 1 | Week 2–3 | Karyawan PWA core (Login, Dashboard) with responsive design. Tailwind styling complete. |
| **Admin RBAC & Dashboard** | 2 | Week 5 | Multi-guard Sanctum, Super Admin vs Admin Wilayah, region middleware, role-scoped dashboard. |
| **Core Content Management** | 2 | Week 6–7 | WYSIWYG, Page/Blog/Portfolio/Team CRUD, Regions CRUD (Super Admin). |
| **AWS S3 Integration & Media Library** | 3 | Week 8 | Media Library S3, transactional uploads, browsing. |
| **SEO & Content Versioning** | 3 | Week 9 | Advanced SEO editor, version history with rollback. |
| **HR Features Complete** | 4 | Week 10–11 | Absensi, Cuti, Rekap, Pengumuman, Profil, Love. |
| **Karyawan PWA Core** | 5 | Week 12–13 | NIK login, profile own-data, absensi GPS+selfie + geofence, cuti form. |
| **Karyawan PWA Extended** | 5 | Week 14–15 | pengumuman inbox, PWA manifest/SW/offline queue. |
| **Testing & Security Audit** | 6 | Week 16 | Unit tests (>80% inc. region/own-data tests), integration, OWASP, geofence & rate limit tests, load test 1000+500. |
| **Production Deployment** | 6 | Week 17 | VPS deploy, DNS, SSL, monitoring, PWA Lighthouse >90. |
| **Post-Launch Stabilization** | 6 | Week 18 | Bug fixes, tuning, client training (Admin Wilayah + Karyawan PWA). |

---

## Dependencies

### External Dependencies

These are third-party services, accounts, and credentials required for the project to function.

| Dependency | Purpose | Status | Notes |
|:---|:---|:---|:---|
| **AWS Account & S3 Bucket** | Cloud storage for all media assets. | Required | Client must provide AWS credentials. IAM policy must restrict access to the specific S3 bucket only. |
| **AWS IAM User Credentials** | Programmatic access for the Laravel application. | Required | Create a dedicated IAM user with S3-only permissions. Store credentials securely in `.env`. |
| **Google Analytics Account** | Analytics data for admin dashboard. | Required | Client must provide GA property ID and API credentials for dashboard integration. |
| **SMTP Email Service** | Transactional emails (contact form notifications, admin alerts). | Required | Configure via Laravel Mail (e.g., Mailgun, SendGrid, or client's own SMTP server). |
| **Domain Name & DNS** | Public website URL and email domain. | Required | Client must own and manage DNS records. Point to VPS IP address. |
| **SSL Certificate** | HTTPS encryption for all pages. | Required | Use Let's Encrypt (free) or client-provided certificate. Auto-renewal recommended. |
| **VPS Hosting** | Server infrastructure for Laravel application. | Required | Linode, DigitalOcean, AWS EC2, or equivalent. Minimum: 2GB RAM, 2 vCPU, 50GB SSD. |

### Internal Dependencies

These are deliverables and artifacts that must be completed before or in parallel with development.

| Dependency | Owner | Deadline | Notes |
|:---|:---|:---|:---|
| **Design Mockups & Wireframes** | Design/Client | Week 1 | High-fidelity mockups for all public pages and admin dashboard. Approved by client before development. |
| **Content Audit & Initial Copy** | Client | Week 1 | All text content for Homepage, About Us, Services, Team bios, and sample blog posts. |
| **Brand Assets** | Client | Week 1 | Logo (multiple formats), favicon, color palette, typography guidelines, sample images. |
| **Database Schema Specification** | Backend Lead | Week 1 | Entity-relationship diagram (ERD) and table definitions. Reviewed and approved by team. |
| **API Specification Document** | Backend Lead | Week 2 | Detailed API endpoints, request/response formats, error handling. Used by frontend team. |
| **React Component Library Plan** | Frontend Lead | Week 2 | Component architecture, naming conventions, reusable component list. |
| **SEO Strategy Document** | Client/Marketing | Week 2 | Target keywords, meta tag strategy, URL structure guidelines. |
| **Security & Compliance Checklist** | DevOps/Backend Lead | Week 3 | OWASP Top 10 review, data protection requirements, compliance standards (GDPR if applicable). |
| **Testing Plan & Test Cases** | QA Lead | Week 4 | Unit test coverage targets, integration test scenarios, manual test cases. |
| **Deployment Runbook** | DevOps Lead | Week 11 | Step-by-step deployment procedure, rollback plan, monitoring setup. |

---

## Risks & Mitigation

| Risk | Impact | Probability | Mitigation Strategy |
|:---|:---|:---|:---|
| **Admin Panel Unauthorized Access** | High | Medium | Enforce strong password policy (min 12 chars, complexity). Implement 2FA in Phase 1 post-launch. Use rate limiting on login endpoint (max 5 attempts/15 min). Consider IP whitelisting if client network is stable. Monitor admin activity logs. |
| **AWS S3 Data Loss or Corruption** | High | Low | Enable versioning on S3 bucket. Implement transactional uploads with rollback on failure. Use restrictive IAM policy (least privilege). Perform monthly backup of bucket metadata to separate storage. Test restore procedure quarterly. |
| **Performance Degradation Under Load** | Medium | Medium | Implement Redis caching for queries, page fragments, and config. Use CDN (CloudFront) for static assets. Optimize database queries with eager loading and indexing. Load test with 1,000 concurrent users in Phase 5. Monitor TTFB, LCP, and FCP continuously. |
| **Scope Creep & Timeline Slippage** | Medium | High | Enforce strict change control process. Document all feature requests in a backlog. Prioritize using MoSCoW method (Must/Should/Could/Won't). Weekly sprint reviews with client. Communicate timeline impact of any scope changes immediately. |
| **Third-Party Service Outage (AWS, Email)** | Medium | Low | Implement graceful degradation for S3 failures (queue uploads, retry logic). Use multiple SMTP providers or fallback email service. Monitor service health dashboards. Document incident response procedures. Maintain contact list for vendor support escalation. |
| **Security Vulnerability in Dependencies** | High | Medium | Use `composer audit` and `npm audit` weekly to identify vulnerable packages. Subscribe to Laravel security advisories. Implement automated dependency updates with testing. Conduct security code review before Phase 5 deployment. Maintain a vulnerability disclosure policy. |
| **Client Content Not Ready on Time** | Medium | Medium | Establish content deadline 2 weeks before Phase 1 completion. Create placeholder content for development/testing. Use staging environment for client review and feedback. Implement content management workflow with version control. |
| **Inertia.js / React Compatibility Issues** | Low | Low | Use stable, well-tested versions (React 19+, Inertia 2.x). Follow official Inertia + Laravel 13 documentation. Conduct spike testing in Week 1 for complex interactions. Maintain active monitoring of package updates and breaking changes. |
| **Database Performance Bottlenecks** | Medium | Medium | Design schema with proper indexing from the start. Use query profiling tools (Laravel Debugbar, MySQL EXPLAIN). Implement pagination for all list views. Monitor slow query logs in production. Plan for database optimization in Phase 5. |
| **Admin User Forgets Login Credentials** | Low | Medium | Implement password reset via Admin (Admin Wilayah own region / Super Admin) (secure token-based). Provide admin with backup recovery codes during onboarding. Document password recovery procedure. Consider storing recovery codes in a secure location (client's password manager). |

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
- Multi-guard login (Super Admin email + Admin Wilayah email, Karyawan NIK) with Sanctum 4.x
- Role-scoped dashboard (Super Admin all regions, Admin Wilayah own region stats)
- Navigation + layout with role gates, region middleware + policies
- Session management + logout per guard + rate limiting

**Week 6–7:**
 - Regions CRUD **+ N Titik Proyek per wilayah** (Super Admin all, Admin Wilayah own region — Leaflet picker per titik) + Admin Wilayah assignment
 - Page content management with TinyMCE, Blog/Portfolio/Team CRUD
 - Employee Management (Lengkap HR) — Admin Wilayah CRUD own region (read all indicator), NIK/NIP validation, foto S3
- Form validation + region isolation + own-data policy tests

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
- Karyawan PWA: NIK login, bottom nav, profile view/edit (limited fields), foto upload S3
 - Absensi: GPS+selfie capture, **geofence N titik proyek** Haversine ke titik terdekat server-side, S3 selfie, status on_time/late/early_leave (di luar semua titik ditolak 422), distance + nama titik UI, offline queue
- Cuti: form ajukan, list status, detail timeline, berjenjang approval UI per level

**Week 14–15:**
- Pengumuman: Super Admin broadcast + Admin Wilayah targeted, karyawan inbox with read/unread + pinned, attachment S3
- PWA: manifest.json, service-worker.js (cache profile+pengumuman), install prompt, VAPID push ready, Lighthouse PWA >90, 44px touch targets

### Phase 6: Testing, Security Hardening & Deployment (Weeks 16–18)

**Week 16:**
- Unit tests >80% inc. RBAC, region scoping (write own 403), own-data 403, geofence validation, NIK uniqueness
- Integration tests for all Karyawan PWA flows (absensi, cuti berjenjang, pengumuman)
- Frontend PWA tests with React Testing Library + PWA audit
- OWASP Top 10 + region isolation audit, rate limit tests
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

| Role | Allocation | Responsibilities |
|:---|:---|:---|
| **Backend Developer** | 100% | Laravel architecture, database design, API endpoints, AWS S3 integration, authentication, testing. |
| **Frontend Developer** | 100% | React components, Inertia integration, Tailwind CSS styling, admin UI, responsive design. |
| **DevOps/Infrastructure** | 30% | VPS setup, database configuration, SSL, monitoring, deployment automation. |
| **QA/Tester** | 20% | Test planning, manual testing, bug reporting, performance testing. |
| **Project Manager** | 50% | Timeline tracking, stakeholder communication, change management, risk mitigation. |
| **Designer** | 40% | Mockups, wireframes, design system, brand guidelines, UI/UX review. |

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
- Client provides initial content + region list (Kabupaten/Kota) + geofence lat/lng/radius by end of Week 1.
- Client provides Lengkap HR employee data per region or admin wilayah will input via UI.
- AWS account + S3 bucket (with folders for media, attendance selfies) configured before Phase 3.
- Karyawan devices support GPS + camera; modern browser supports PWA install + VAPID push.
- No major scope changes after Phase 1 kickoff (HR fields + cuti flow frozen).

**Constraints:**
- Technology stack (Laravel 13, React 19, Inertia v2, Tailwind v4, Vite 7, MySQL 8.4 LTS, AWS S3, PWA) is fixed — all latest stable.
- RBAC 3 roles required: Super Admin, Admin Wilayah per region, Karyawan (NIK login own-data-only).
- Multi-tenancy per Kabupaten/Kota via region_id — Admin Wilayah write own region only (read all), Karyawan only own data.
- Initial release includes PWA mandatory; native app is post-launch.
- Team size 2 devs; timeline scales with size changes.