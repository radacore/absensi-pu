# DESIGN.md: BBWS Pompengan Jeneberang

## Brand & Visual Identity

BBWS Pompengan Jeneberang is a corporate profile application designed to project professionalism, trustworthiness, and modern sophistication. The visual identity emphasizes clean lines, generous whitespace, and a refined color palette that conveys stability and innovation. The design tone is corporate yet approachable, balancing formal business aesthetics with contemporary web standards to appeal to potential clients, partners, and investors.

## User Experience Goals

1. **Effortless Content Discovery** — Public visitors should locate key company information (services, portfolio, team, contact) within 2 clicks from the homepage, with a target task completion rate of 95%.

2. **Admin Efficiency & Speed** — The administrator should complete standard content updates (create/edit/publish a blog post or portfolio item) in under 5 minutes, with intuitive WYSIWYG editors and minimal form friction.

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

### CSS Custom Properties Block

```css
:root {
  /* Primary */
  --color-primary: #1E3A8A;
  --color-primary-dark: #0F172A;
  --color-accent: #0D9488;
  
  /* Secondary */
  --color-secondary-light: #E0F2FE;
  --color-neutral-light: #F3F4F6;
  --color-success: #10B981;
  
  /* Neutral */
  --color-text-primary: #1F2937;
  --color-text-secondary: #6B7280;
  --color-text-tertiary: #D1D5DB;
  --color-border: #E5E7EB;
  --color-error: #EF4444;
  --color-warning: #F59E0B;
  --color-white: #FFFFFF;
  
  /* Semantic */
  --color-bg-primary: #FFFFFF;
  --color-bg-secondary: #F9FAFB;
  --color-bg-tertiary: #F3F4F6;
}
```

### Tailwind Configuration Snippet (v4 - CSS-first)

```css
/* app.css - Tailwind v4 */
@import "tailwindcss";

@theme {
  --color-primary: #1E3A8A;
  --color-primary-dark: #0F172A;
  --color-accent: #0D9488;
  --color-secondary-light: #E0F2FE;
  --color-neutral-light: #F3F4F6;
  --color-success: #10B981;
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

### Public-Facing Screens (Visitor Priority Order)

1. **Homepage** — Hero banner, featured services, testimonials, CTA. Highest traffic, first impression.
2. **Services Page** — Service listing with descriptions and CTAs. High conversion potential.
3. **Portfolio/Projects Page** — Filterable gallery of completed work. Demonstrates capability.
4. **Contact Us Page** — Contact form, map, company details. Lead generation critical.
5. **About Us Page** — Company history, mission, values. Trust-building content.
6. **Team Page** — Team member profiles with photos and bios. Humanizes the company.
7. **Blog/News Listing** — Article list with pagination and search. SEO and engagement.
8. **Blog/Article Detail** — Individual article view with related posts. Content depth.
9. **Service Detail Page** — In-depth service information with case studies. Conversion support.

### Admin Dashboard Screens (Administrator Priority Order — Super Admin & Admin Wilayah)

1. **Admin Login** — Email+password, role-scoped, obfuscated path. Fast + rate-limited.
2. **Dashboard Overview (Role-Scoped)** — Super Admin: all regions stats; Admin Wilayah: own region (employee count, attendance today, pending cuti) + read-only global.
3. **Region Management (Super Admin Only)** — CRUD Kabupaten/Kota + geofence (lat/lng/radius) + assign admin wilayah.
4. **Karyawan Management (Lengkap HR)** — List filtered own region (toggle read all), Create/Edit with NIK/NIP/golongan/jabatan/unit/status, foto S3, region auto-scoped.
5. **Attendance Viewer** — List per region with date/status filter, detail with selfie + map + distance.
6. **Leave Requests (Berjenjang)** — Queue per level, approve/reject with notes, timeline UI, region-scoped.
7. **Love Claims (1 Level)** — Queue pending Love Claims own region (late dalam radius + dokumen/alasan), Approve/Reject 1 level Admin Cabang, notifikasi karyawan, update love_sisa & attendance excused_love.
7. **Announcement Management** — Create broadcast (global) or region-targeted, attachment S3, pinned handling.
9. **Blog Management** — Create, edit, publish articles. Frequent content updates.
10. **Portfolio Management** — Manage projects, upload media. Regular content maintenance.
11. **Contact Submissions Viewer** — View and archive form submissions. Lead management.
12. **Page Content Editor** — Edit About, Services, static pages. Periodic updates.
13. **Team Management** — Add/edit team member profiles (public team display).
14. **Testimonial Management** — Approve and manage testimonials. Periodic moderation.
15. **Media Library** — Browse, upload, delete media from S3. Utility function.
16. **SEO Management** — Override meta tags, manage Open Graph. Periodic optimization.
17. **Global Settings (Super Admin Only)** — Company info, logo, contact details, social links. Admin Wilayah read-only.
18. **Content Versioning** — View and rollback page history. Emergency/audit function.

### Karyawan PWA Screens (Mobile-First, 320px+, Installable — Priority Order)

1. **Karyawan Login (NIK + Password)** — Mobile-first 320px, numeric NIK input, show/hide password, rate-limit error UX, PWA install banner.
2. **Dashboard Home** — Greeting + today attendance status (on_time/late/belum absen), pending cuti count, unread pengumuman count, quick actions (Absen, Ajukan Cuti).
3. **Absensi (GPS + Selfie)** — Big "Absen Masuk/Pulang" button 44px+, GPS permission UX, camera capture, preview selfie + distance to kantor, status badge (on_time/late — tidak ada out_of_range, di luar radius ditolak), history list (paginated), offline queue indicator.
4. **Cuti (Berjenjang)** — Form ajukan (jenis, tgl mulai/selesai, alasan, dokumen), list own requests with status timeline (pending → level1 → level2 → approved/rejected), detail with approver notes.
5. **Pengumuman Inbox** — Combined global+region, pinned first, unread dot, tap to read (marks read), attachment download, pull-to-refresh.
6. **Love (4 Hati)** — 4 dot gold #FCB833 (terisi) / #E2E8F0 (kosong), text "Sisa toleransi: 3/4", gold CTA "Gunakan Love" saat late dalam radius, history claim (pending/approved/rejected), reset info "Reset 1 Agu".
7. **Profil Saya** — View Lengkap HR (NIK,NIP,golongan,jabatan,unit,status,region, foto), edit limited (foto, phone, email, password), region read-only badge.
7. **Love Detail in Rekap** — Rekap kalender gold dot hadir, detail late dengan opsi pakai Love jika dalam radius.
8. **Bottom Navigation** — 5 tabs: Home, Absensi, Cuti, Info, Profil; 44px tap targets, safe-area-inset-bottom (tanpa Slip). Love tidak di bottom nav, via Dashboard & Rekap.

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

**Stack:** Laravel 13 + React 19 + Inertia v2 + Tailwind v4 + Vite 7 + MySQL 8.4 LTS + PWA  
**Roles:** Super Admin Pusat, Admin Wilayah (per Kabupaten/Kota, write own region), Karyawan (NIK login, own-data-only, mobile PWA)  
**Document Version:** 3.0 (added karyawan PWA + regional multi-tenancy)  
**Last Updated:** 2026-08-24  
**Status:** Ready for Development