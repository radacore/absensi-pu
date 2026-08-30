# DESIGN.md: BBWS Pompengan Jeneberang

## Brand & Visual Identity

BBWS Pompengan Jeneberang is a corporate profile application designed to project professionalism, trustworthiness, and modern sophistication. The visual identity emphasizes clean lines, generous whitespace, and a refined color palette that conveys stability and innovation. The design tone is corporate yet approachable, balancing formal business aesthetics with contemporary web standards to appeal to potential clients, partners, and investors.

## User Experience Goals

1. **Effortless Content Discovery** — Public visitors should locate key company information (services, portfolio, team, contact) within 2 clicks from the homepage, with a target task completion rate of 95%.

2. **Admin Efficiency & Speed** — The administrator should complete standard content updates (create/edit/publish a blog post or portfolio item) in under 5 minutes, with intuitive forms and minimal friction.

3. **Mobile-First Responsiveness** — All pages must deliver an optimal experience across mobile (320px+), tablet (768px+), and desktop (1024px+) viewports, with no horizontal scrolling and touch-friendly interactive elements (minimum 44px tap targets).

## Color Palette

### Primary Colors
| Color | Hex | CSS Variable | Usage |
|:---|:---|:---|:---|
| Corporate Blue | `#1E3A8A` | `--color-primary` | Headers, CTAs, primary buttons, links |
| Professional Slate | `#0F172A` | `--color-primary-dark` | Navigation, footer, dark backgrounds |
| Accent Teal | `#0D9488` | `--color-accent` | Hover states, highlights, secondary CTAs |

### Secondary Colors
| Color | Hex | CSS Variable | Usage |
|:---|:---|:---|:---|
| Light Sky | `#E0F2FE` | `--color-secondary-light` | Backgrounds, card accents |
| Warm Gray | `#F3F4F6` | `--color-neutral-light` | Section backgrounds, borders |
| Success Green | `#10B981` | `--color-success` | Form validation, success messages |
| **Love Gold** | `#FCB833` | `--color-love-gold` | Love 4 hati dot terisi, badge sisa toleransi, gold CTA — 1 karyawan=1 titik, reset 00:00 WITA 1st |

### Neutral & Semantic Colors
| Color | Hex | CSS Variable | Usage |
|:---|:---|:---|:---|
| Text Dark | `#1F2937` | `--color-text-primary` | Body text, primary content |
| Text Medium | `#6B7280` | `--color-text-secondary` | Secondary text, metadata |
| Text Light | `#D1D5DB` | `--color-text-tertiary` | Disabled text, placeholders |
| Border Gray | `#E5E7EB` | `--color-border` | Dividers, input borders |
| Error Red | `#EF4444` | `--color-error` | Error messages, validation |
| Warning Amber | `#F59E0B` | `--color-warning` | Warning alerts, caution states |
| White | `#FFFFFF` | `--color-white` | Card backgrounds, overlays |
| Love Empty | `#E2E8F0` | `--color-love-empty` | Love dot kosong 4 hati |
| Love Disabled | `#94A3B8` | `--color-love-disabled` | Love CTA disabled saat `!inRadius` / `tanpaTitik` (`isWithinAssignedSite`) |

### CSS Custom Properties Block

```css
:root {
  /* Primary — navy #0F172A / blue #1E3A8A */
  --color-primary: #1E3A8A;
  --color-primary-dark: #0F172A;
  --color-accent: #0D9488;
  
  /* Secondary */
  --color-secondary-light: #E0F2FE;
  --color-neutral-light: #F3F4F6;
  --color-success: #10B981;
  --color-love-gold: #FCB833;  /* Love 4 hati terisi — 1 karyawan=1 titik */
  --color-love-empty: #E2E8F0; /* Love dot kosong */
  
  /* Neutral */
  --color-text-primary: #1F2937;
  --color-text-secondary: #6B7280;
  --color-text-tertiary: #D1D5DB;
  --color-border: #E5E7EB;
  --color-error: #EF4444;
  --color-warning: #F59E0B;
  --color-white: #FFFFFF;
  --color-love-disabled: #94A3B8; /* disabled !inRadius/tanpaTitik */
  
  /* Semantic — 1 karyawan=1 titik (office_location_id) */
  --color-bg-primary: #FFFFFF;
  --color-bg-secondary: #F9FAFB;
  --color-bg-tertiary: #F3F4F6;
}
```

### Tailwind Configuration Snippet (v4 - CSS-first)

```css
/* app.css - Tailwind v4 — 1 karyawan=1 titik, gold #FCB833 navy #0F172A */
@import "tailwindcss";

@theme {
  --color-primary: #1E3A8A;
  --color-primary-dark: #0F172A;
  --color-accent: #0D9488;
  --color-secondary-light: #E0F2FE;
  --color-neutral-light: #F3F4F6;
  --color-success: #10B981;
  --color-love-gold: #FCB833;  /* 4 hati terisi — isWithinAssignedSite only */
  --color-love-empty: #E2E8F0;
  --color-love-disabled: #94A3B8;
  --color-error: #EF4444;
  --color-warning: #F59E0B;
  --color-white: #FFFFFF;
  --color-text-primary: #1F2937;
  --color-text-secondary: #6B7280;
  --color-text-tertiary: #D1D5DB;
  --color-border: #E5E7EB;
}
```

> Tailwind v4 tidak lagi memakai `tailwind.config.js` untuk warna — gunakan `@theme` di CSS. Config JS hanya untuk plugin kompleks bila diperlukan.

## Typography

### Font Families

| Usage | Font Family | Google Fonts Link | Fallback |
|:---|:---|:---|:---|
| Headings (H1–H6) | Inter | [Inter](https://fonts.google.com/specimen/Inter) | -apple-system, BlinkMacSystemFont, sans-serif |
| Body & UI | Inter | [Inter](https://fonts.google.com/specimen/Inter) | -apple-system, BlinkMacSystemFont, sans-serif |
| Monospace (Code) | JetBrains Mono | [JetBrains Mono](https://fonts.google.com/specimen/JetBrains+Mono) | Courier New, monospace |

### Font Size Scale

| Element | Size | Line Height | Weight | Usage |
|:---|:---|:---|:---|:---|
| H1 (Hero) | 48px / 3rem | 1.2 | 700 | Page hero titles, main headings |
| H2 (Section) | 36px / 2.25rem | 1.3 | 700 | Section headings, major divisions |
| H3 (Subsection) | 28px / 1.75rem | 1.4 | 600 | Subsection titles, card headers |
| H4 (Minor) | 24px / 1.5rem | 1.4 | 600 | Minor headings, form labels |
| Body Large | 18px / 1.125rem | 1.6 | 400 | Lead paragraphs, introductory text |
| Body Regular | 16px / 1rem | 1.6 | 400 | Standard body text, descriptions |
| Body Small | 14px / 0.875rem | 1.5 | 400 | Secondary text, metadata, captions |
| Caption | 12px / 0.75rem | 1.5 | 500 | Timestamps, helper text, badges |
| Code | 14px / 1.5 | 1.5 | 500 | Inline code, code blocks |

### Font Weights

| Weight | Value | Usage |
|:---|:---|:---|
| Regular | 400 | Body text, standard content |
| Medium | 500 | Labels, badges, secondary emphasis |
| Semibold | 600 | Subheadings, strong emphasis |
| Bold | 700 | Headings, primary emphasis |

### Implementation

```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap');

body {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  font-size: 16px;
  line-height: 1.6;
  color: var(--color-text-primary);
}

h1 { font-size: 48px; font-weight: 700; line-height: 1.2; }
h2 { font-size: 36px; font-weight: 700; line-height: 1.3; }
h3 { font-size: 28px; font-weight: 600; line-height: 1.4; }
h4 { font-size: 24px; font-weight: 600; line-height: 1.4; }

.text-lg { font-size: 18px; line-height: 1.6; }
.text-sm { font-size: 14px; line-height: 1.5; }
.text-xs { font-size: 12px; line-height: 1.5; }

code, pre {
  font-family: 'JetBrains Mono', monospace;
  font-size: 14px;
  font-weight: 500;
}
```

## UI Components & Spacing

### Grid & Spacing Unit

**Base Unit:** 8px (Tailwind default)

All spacing, padding, margins, and sizing should be multiples of 8px to maintain visual consistency and rhythm.

| Spacing Token | Value | Tailwind Class | Usage |
|:---|:---|:---|:---|
| xs | 4px | `p-1` | Minimal spacing, tight layouts |
| sm | 8px | `p-2` | Small gaps, compact components |
| md | 16px | `p-4` | Standard padding, default spacing |
| lg | 24px | `p-6` | Generous spacing, section separation |
| xl | 32px | `p-8` | Large gaps, major section breaks |
| 2xl | 48px | `p-12` | Extra-large spacing, hero sections |
| 3xl | 64px | `p-16` | Maximum spacing, full-width sections |

### Border Radius Scale

| Size | Value | Tailwind Class | Usage |
|:---|:---|:---|:---|
| None | 0px | `rounded-none` | Sharp corners, borders |
| Small | 4px | `rounded-sm` | Subtle rounding, inputs |
| Medium | 8px | `rounded-md` | Standard rounding, cards, buttons |
| Large | 12px | `rounded-lg` | Prominent rounding, modals, large cards |
| Extra Large | 16px | `rounded-xl` | Soft rounding, hero sections |
| Full | 9999px | `rounded-full` | Fully rounded, avatars, badges |

### Standard Component Spacing

| Component | Padding | Border Radius | Min Height |
|:---|:---|:---|:---|
| Button (Primary) | 12px 24px | 8px | 44px |
| Button (Secondary) | 10px 20px | 8px | 40px |
| Input Field | 12px 16px | 8px | 44px |
| Card | 24px | 12px | — |
| Modal | 32px | 16px | — |
| Navigation Item | 12px 16px | 4px | 44px |
| Avatar (Small) | — | 50% | 32px |
| Avatar (Medium) | — | 50% | 48px |
| Avatar (Large) | — | 50% | 64px |

### Responsive Breakpoints

| Breakpoint | Width | Tailwind Prefix | Usage |
|:---|:---|:---|:---|
| Mobile | 320px–767px | (none) | Default, mobile-first |
| Tablet | 768px–1023px | `md:` | Tablets, small laptops |
| Desktop | 1024px+ | `lg:` | Desktops, large screens |

## Screen Priorities

### Admin Dashboard Screens (Administrator Priority Order — Super Admin & Admin Wilayah — 1 Karyawan=1 Titik)

1. **Admin Login (Opsi B Pisah URL)** — **Super Admin** `SUPER_ADMIN_PATH/login` vs **Admin Wilayah** `WILAYAH_PATH/login` (dev `/super-admin/login` / `/wilayah/login`), email+password, guard terpisah tidak cross-login, obfuscated + rate-limited per guard.
2. **Dashboard Overview (Role-Scoped, Per Titik)** — Super Admin: all regions stats **breakdown per Titik** (cards `anggota`/`lat/lng•radius` Link `${base}/regions/{id}/sites/{id}`); Admin Wilayah: **own region breakdown per Titik** + `siteFilter` `Semua titik` + `totalTanpaTitik` (`office_location_id IS NULL`). Source `_shared.js` `DUMMY_REGIONS` 24 (`101/102/201/202/301...`) + `DUMMY_EMPLOYEES` 1=1 (Andi 201, Siti 101, Budi 202, Rina null).
3. **Region Management + N Titik Proyek + Dedicated Page per Titik (Super Admin / Admin Wilayah own)** — CRUD Kabupaten/Kota + **N titik proyek per wilayah** (`MAX_SITES=20`, hapus last 422) tiap titik: nama ex Bendungan Bili-Bili/Jembatan Pampang, lat/lng **Leaflet draggable+circle**, radius 50–1000 — Super Admin all wilayah, Admin Wilayah tambah/edit N titik di wilayahnya sendiri (read-only wilayah lain) + assign admin wilayah. **Dedicated `GET /regions/{region}/sites/{site}`** (`Admin/SiteDetail`) x3 prefix `super_admin|admin|wilayah.sites.show` + helper `getAdminBase(url)` — Leaflet edit + anggota per titik saja + kandidat `office_location_id IS NULL`.
4. **Karyawan Management (Lengkap HR, 1 Karyawan=1 Titik)** — List **filtered own region + filter `Titik Proyek` (`__null` tanpa titik)** incl. `Wilayah→Titik Proyek→Status`, kolom **Titik Proyek** Link SiteDetail + badge status, Create/Edit with NIK/NIP/golongan/jabatan/unit/status + **`office_location_id` select 1=1 titik** (NULL=belum assign 422), foto S3, region auto-scoped, pindah via SiteDetail `Pindah`.
5. **Attendance Viewer (Per Titik)** — List **filter `Wilayah→Titik Proyek→Status`** incl. `__null`, kolom **Titik Proyek** Link `GET /regions/.../sites/...`, badge **`Dalam/Di luar` (`distance <= radius_m(assigned)` via `isWithinAssignedSite`)** + `Jarak` kolom, detail drawer selfie + map Leaflet + `distance_m/radius_m` + S3 path.
6. **Leave Requests (Berjenjang, Per Titik)** — Queue **filter `Titik Proyek` + `__null`**, kolom **Titik Proyek** Link SiteDetail, approve/reject with notes per level, timeline UI, region-scoped.
7. **Love Claims (1 Level, Per Titik Assigned Only)** — Queue **filtered per Titik own region** (`office_location_id` + `__null`), kolom **Titik Proyek** Link, badge **`jarak/radius Dalam/Di luar` (assigned)**, Approve **disabled `sisaLove==0 || !hit`** (`isWithinAssignedSite` only, bukan `isWithinAnySite`), `tanpaTitik` tidak ada claim.
8. **Announcement Management** — Create broadcast (global) or region-targeted, attachment S3, pinned handling.
9. **Global Settings (Super Admin Only)** — Company info, logo, contact details, social links + `global_settings.love_max_default` 1–10. Admin Wilayah read-only.

### Karyawan PWA Screens (Mobile-First, 320px+, Installable — Priority Order — 1 Karyawan=1 Titik)

1. **Karyawan Login (Email + Password, KARYAWAN_PATH)** — Mobile-first 320px, `KARYAWAN_PATH/login` (dev `/karyawan/login`, obfuscated di prod), email input, show/hide password, rate-limit error UX (5/15min per IP+email), PWA install banner. Tidak cross-login dengan `/super-admin` / `/wilayah` (guard `karyawan`).
2. **Dashboard Home (Per Titik Assigned)** — Greeting + **badge `Ditugaskan di: Bendungan Bili-Bili — Kab. Gowa lat/lng • radius 200m`** (via `loadRegions/loadEmployees` `_shared.js` `MOCK_KARYAWAN_ID=1` 201) atau warning `Belum di-assign titik — hubungi Admin Wilayah` jika `office_location_id IS NULL`, today status (on_time/late/belum absen + `distance/radius` assigned), pending cuti, unread pengumuman, quick actions (Absen, Ajukan Cuti). Love sisa/max dot gold `#FCB833`.
3. **Absensi (GPS + Selfie — Hanya Titik Assigned)** — Banner **titik assigned** `lat/lng • radius` (bukan nearest dari N), Big "Absen Masuk/Pulang" button 44px+, GPS+camera capture, preview selfie + **`jarak/radius` ex `48/200m` + badge `Dalam/Di luar radius titik assigned` (`isWithinAssignedSite` only)**, status on_time/late (**di luar assigned atau `NULL` ditolak 422 tidak tercatat**), buttons **disabled `!inRadius || tanpaTitik`**, history `jarak/radius` per assigned, offline queue (server re-validasi `isWithinAssignedSite` + jam `07:30–16:00`).
4. **Cuti (Berjenjang, Per Titik)** — Form ajukan (jenis, tgl, alasan, dokumen) dengan konteks titik assigned read-only, list own requests timeline (pending→level1→level2→approved/rejected) incl. titik link, detail with approver notes.
5. **Pengumuman Inbox** — Combined global+region, pinned first, unread dot, tap to read (marks read), attachment S3 download, pull-to-refresh.
6. **Love (4 Hati, Dalam Radius Titik Assigned)** — 4 dot **gold `#FCB833` terisi / `#E2E8F0` kosong**, text `Sisa toleransi: 3/4` (`--color-love-gold/empty`), gold CTA **"Gunakan Love" aktif hanya `inRadius(assigned) && sisa>0 && !tanpaTitik`**, disabled state `--color-love-disabled`, badge `jarak/radius Dalam/Di luar`, history claim (pending/approved/rejected), reset `1st 00:00 WITA` + bulan sama.
7. **Profil Saya (Per Titik)** — View Lengkap HR (NIK,NIP,golongan,jabatan,unit,status,region, foto) + **card titik assigned** (nama `Bendungan Bili-Bili`, lat/lng, radius, alamat) atau placeholder `— Belum di-assign titik`, edit limited (foto, phone, email, password), region read-only badge.
8. **Rekap (Per Titik)** — Kalender gold dot hadir + note **`Di luar {radius}m tidak tercatat 422`** (`Karyawan/Rekap.jsx` header `Ditugaskan di:`), detail late dengan opsi Love jika `Dalam radius titik assigned` (bukan any site).
9. **Bottom Navigation** — 5 tabs: Home, Absensi, Cuti, Info, Profil; 44px tap targets, safe-area-inset-bottom (tanpa Slip). Love tidak di bottom nav, via Dashboard & Rekap.

## Interaction & Motion

### Hover States

| Element | Hover Effect | Transition | Usage |
|:---|:---|:---|:---|
| Primary Button | Background: `#1E3A8A` → `#1E40AF`, shadow lift | 200ms ease-out | CTAs, form submission |
| Secondary Button | Background: `#F3F4F6` → `#E5E7EB`, text: `#1F2937` → `#0F172A` | 200ms ease-out | Secondary actions |
| Link (Text) | Color: `#1E3A8A` → `#0D9488`, underline appear | 150ms ease-out | Navigation, inline links |
| Card | Shadow: `0 1px 3px` → `0 10px 25px`, transform: scale(1.02) | 250ms ease-out | Portfolio items, testimonials |
| Navigation Item | Background: transparent → `#E0F2FE`, color: `#1F2937` → `#1E3A8A` | 150ms ease-out | Nav menu items |
| Form Input (Focus) | Border: `#E5E7EB` → `#1E3A8A`, shadow: `0 0 0 3px rgba(30, 58, 138, 0.1)` | 150ms ease-out | Text inputs, textareas |
| Icon Button | Color: `#6B7280` → `#1E3A8A`, transform: scale(1.1) | 150ms ease-out | Close, menu, action icons |

### Transitions & Animations

| Animation | Duration | Easing | Trigger | Usage |
|:---|:---|:---|:---|:---|
| Page Fade-In | 300ms | ease-out | Page load, route change | Smooth content appearance |
| Slide-Up (Hero) | 600ms | cubic-bezier(0.34, 1.56, 0.64, 1) | Page load | Hero section entrance |
| Fade-In Stagger | 150ms per item | ease-out | List render | Testimonials, team cards |
| Modal Slide-In | 250ms | ease-out | Modal open | Admin forms, dialogs |
| Loading Spinner | 1s | linear | Data fetch | Infinite rotation |
| Toast Notification | 300ms (in), 300ms (out) | ease-out | Form submission, alerts | Slide-in from top-right |
| Dropdown Menu | 150ms | ease-out | Menu toggle | Smooth expand/collapse |
| Skeleton Loader | 1.5s | ease-in-out | Content loading | Shimmer effect |

### Motion Principles

- **Entrance:** All new content should fade or slide in smoothly (200–300ms) to guide user attention.
- **Feedback:** Interactive elements (buttons, links, inputs) must provide immediate visual feedback on hover/focus (150–200ms).
- **Transitions:** Page and route transitions should be subtle (300ms) to avoid distraction.
- **Loading States:** Spinners and skeleton screens should animate continuously to indicate active processing.
- **Accessibility:** All animations must respect `prefers-reduced-motion` media query; provide instant alternatives for users who disable animations.

### CSS Motion Implementation

```css
/* Transition Utilities */
.transition-fast { transition: all 150ms ease-out; }
.transition-base { transition: all 200ms ease-out; }
.transition-slow { transition: all 300ms ease-out; }

/* Animations */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes slideUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

.animate-fade-in { animation: fadeIn 300ms ease-out; }
.animate-slide-up { animation: slideUp 600ms cubic-bezier(0.34, 1.56, 0.64, 1); }
.animate-spin { animation: spin 1s linear infinite; }

/* Respect Reduced Motion */
@media (prefers-reduced-motion: reduce) {
  * { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
}
```

## Accessibility

### Contrast Ratios

All text and interactive elements must meet WCAG 2.1 Level AA standards (minimum 4.5:1 for normal text, 3:1 for large text).

| Color Pair | Contrast Ratio | WCAG Level | Usage |
|:---|:---|:---|:---|
| Text Dark (#1F2937) on White (#FFFFFF) | 12.6:1 | AAA | Primary body text |
| Text Medium (#6B7280) on White (#FFFFFF) | 7.0:1 | AAA | Secondary text |
| Primary Blue (#1E3A8A) on White (#FFFFFF) | 8.5:1 | AAA | Links, primary buttons |
| Accent Teal (#0D9488) on White (#FFFFFF) | 5.2:1 | AA | Accent elements |
| White (#FFFFFF) on Primary Blue (#1E3A8A) | 8.5:1 | AAA | Button text, inverted |
| Error Red (#EF4444) on White (#FFFFFF) | 4.6:1 | AA | Error messages |
| Success Green (#10B981) on White (#FFFFFF) | 5.5:1 | AA | Success messages |

### Keyboard Navigation Essentials

- **Tab Order:** All interactive elements (buttons, links, inputs, modals) must be keyboard-accessible in a logical, left-to-right, top-to-bottom order.
- **Focus Indicators:** All focusable elements must display a visible focus ring (minimum 2px, color: `#1E3A8A` with 3px offset).
- **Skip Links:** A "Skip to Main Content" link must be present on all pages, visible on focus, to bypass repetitive navigation.
- **Form Labels:** All form inputs must have associated `<label>` elements with `for` attributes; use `aria-label` or `aria-labelledby` for unlabeled inputs.
- **ARIA Landmarks:** Use semantic HTML (`<header>`, `<nav>`, `<main>`, `<footer>`) and ARIA roles (`role="region"`, `role="navigation"`) to define page structure.
- **Modal Dialogs:** Modals must trap focus within the dialog, with a close button and keyboard escape support.
- **Error Handling:** Form validation errors must be announced to screen readers via `aria-live="polite"` and linked to inputs via `aria-describedby`.
- **Images & Icons:** All images must have descriptive `alt` text; decorative icons should use `aria-hidden="true"`.
- **Headings:** Use semantic heading hierarchy (H1 → H2 → H3); avoid skipping levels.
- **Color Alone:** Never convey information using color alone; use text labels, icons, or patterns in addition to color.

### Accessibility Checklist for Developers

- [ ] All pages pass WAVE or Axe accessibility audit with zero errors.
- [ ] Minimum contrast ratio of 4.5:1 for all text on backgrounds.
- [ ] All form inputs have associated labels and error messages.
- [ ] Focus indicators are visible and meet 2px minimum width.
- [ ] Page structure uses semantic HTML and ARIA landmarks.
- [ ] All images have descriptive alt text (or `aria-hidden` if decorative).
- [ ] Modals trap focus and support keyboard escape.
- [ ] Animations respect `prefers-reduced-motion` preference.
- [ ] Mobile touch targets are minimum 44px × 44px.
- [ ] Page is fully navigable using keyboard alone (Tab, Enter, Escape, Arrow keys).

---

**Stack:** Laravel 13 + React 19 + Inertia v2 + Tailwind v4 + Vite 7 + MySQL 8.4 LTS + PWA — **1 karyawan=1 titik (office_location_id), Geofence `isWithinAssignedSite` only, NULL 422, N≤20**  
**Roles:** Super Admin Pusat, Admin Wilayah (per Kab/Kota, write own `region_id` + `office_location_id` 1=1 assign/pindah per titik), Karyawan (email login, own-data-only, **absen/Love hanya titik assigned**, mobile PWA banner titik)  
**Document Version:** 3.1 (per-titik strict — 1 karyawan=1 titik, dedicated `GET /regions/{region}/sites/{site}`, PWA assigned-only)  
**Last Updated:** 2026-08-30  
**Status:** Synced per-titik strict — Ready for Development