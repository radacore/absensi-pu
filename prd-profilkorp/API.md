# API.md: BBWS Pompengan Jeneberang

## Authentication & Authorization — Opsi B: Pisah URL (Super Admin / Wilayah / Karyawan)

BBWS Pompengan Jeneberang uses **Laravel Sanctum 4.x** with **multi-guard** session-based authentication. **Tiga guard/frontend terpisah** (Opsi B): `super_admin` via `SUPER_ADMIN_PATH`, `wilayah` via `WILAYAH_PATH`, `karyawan` via `KARYAWAN_PATH`. Tidak ada cross-login — session super-admin tidak valid di wilayah path dan sebaliknya.

**Web Paths (Inertia):**
- Super Admin: `SUPER_ADMIN_PATH` → dev `/super-admin` (`/super-admin/login`, `/super-admin`, `/super-admin/regions`, …) — guard `auth:super_admin` + `role:super_admin` + `region_id=null`.
- Admin Wilayah: `WILAYAH_PATH` → dev `/wilayah` (`/wilayah/login`, `/wilayah`, `/wilayah/employees`, …) — guard `auth:wilayah` (atau `auth:admin` dengan role check) + `role:admin_wilayah` + `region_id=FK`.
- Karyawan: `KARYAWAN_PATH` → dev `/karyawan` (`/karyawan/login`, `/karyawan`, …) — guard `auth:karyawan`, own-data-only.

**API Paths (split prefix, same resource shape):**
- Super Admin: `/api/super-admin/*` — `auth:super_admin` + `role:super_admin` (akses semua region).
- Admin Wilayah: `/api/wilayah/*` — `auth:wilayah` + `role:admin_wilayah` + region scoping (write own, read all via `?all_regions=1`).
- Karyawan: `/api/karyawan/*` — `auth:karyawan`.
- Legacy `/api/admin/*` dipertahankan sebagai alias ke `/api/super-admin/*` untuk backward compat, tapi docs baru pakai `/api/super-admin/*` & `/api/wilayah/*`. Semua contoh di bawah tulis sebagai `/api/{super-admin|wilayah}/*` — ganti prefix sesuai role.

**Session Header Format:**
```
Cookie: XSRF-TOKEN=<token>; laravel_session=<session_id>
X-CSRF-TOKEN: <token>
```

**Authorization Levels:**
- **Super Admin:** Requires `auth:super_admin` session. Role `super_admin`, `region_id=null`, unscoped all regions. Hanya via `SUPER_ADMIN_PATH` / `/api/super-admin/*`.
- **Admin Wilayah:** Requires `auth:wilayah` session. Role `admin_wilayah`, `region_id=FK`, scoped write own region only (read all).
- **Karyawan:** Requires `auth:karyawan` session via email + password. Own-data-only: can only access own employee_id data. Rate-limited per email + IP.

## Standard Response & Pagination Formats

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": { /* resource or array of resources */ },
  "message": "Operation completed successfully"
}
```

**Error Response (4xx/5xx):**
```json
{
  "success": false,
  "error": "Error code or message",
  "message": "Human-readable error description"
}
```

**Pagination Format (for list endpoints):**
```json
{
  "success": true,
  "data": [ /* array of items */ ],
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 120,
    "last_page": 8,
    "from": 1,
    "to": 15
  }
}
```

## Admin API Endpoints — Prefix Split: `/api/super-admin/*` vs `/api/wilayah/*`

> **Konvensi Opsi B:** Semua endpoint admin di bawah ini tersedia di **dua prefix** dengan guard berbeda:
> - **Super Admin:** `/api/super-admin/*` (`auth:super_admin`, `role:super_admin`, unscoped)
> - **Admin Wilayah:** `/api/wilayah/*` (`auth:wilayah` + `role:admin_wilayah`, scoped own region)
> Contoh `GET /api/super-admin/regions` dan `GET /api/wilayah/regions` bentuk response sama; bedanya adalah scoping & 403 rules. Di tabel rate-limit, `super-admin` & `wilayah` dihitung terpisah.
> Dokumen ini menulis path sebagai `/api/{super-admin|wilayah}/*` di ringkasan; detail per endpoint tetap tulis `/api/admin/*` sebagai alias legacy — implementasi baru pakai dua prefix.

### Authentication

#### Admin Login — Pisah Guard (Super Admin vs Wilayah)
- **Method:** `POST`
- **Path:** `/api/super-admin/login` (Super Admin) & `/api/wilayah/login` (Admin Wilayah) — legacy alias `/api/admin/login` tetap ada tapi deprecated
- **Description:** Authenticate the administrator and establish a session. Rate-limited to prevent brute-force attacks.
- **Auth Level:** Public
- **Rate Limit:** 5 failed attempts per 15 minutes per IP
- **Request Body:**
```json
{
  "email": "string (required, valid email)",
  "password": "string (required, min 8)"
}
```
- **Response Body (Success):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": "integer",
      "name": "string",
      "email": "string"
    }
  },
  "message": "Login successful"
}
```
- **Response Body (Error):**
```json
{
  "success": false,
  "error": "invalid_credentials",
  "message": "The provided credentials are invalid."
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 429 (Too Many Requests), 500 (Server Error)

#### Admin Logout
- **Method:** `POST`
- **Path:** `/api/admin/logout`
- **Description:** Terminate the admin session.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Current Admin User
- **Method:** `GET`
- **Path:** `/api/admin/user`
- **Description:** Retrieve the currently authenticated admin user information (includes role + region).
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "email": "string",
    "role": "string (super_admin|admin_wilayah)",
    "region_id": "integer|null",
    "region": { "id": "integer", "name": "string", "slug": "string" },
    "created_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### List Regions / Kantor Wilayah (BBWS Pompengan Jeneberang) — with N Titik Proyek
- **Method:** `GET`
- **Path:** `/api/{super-admin|wilayah}/regions` (legacy `/api/admin/regions`)
- **Description:** Retrieve all kantor (Kantor Pusat + Wilayah Kab/Kota se-Sulsel) **with N titik proyek per wilayah** (`office_locations`). Admin Wilayah can view all, but edit only own.
- **Auth Level:** Admin (super_admin unscoped, admin_wilayah: write own)
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string (e.g., Kabupaten Gowa)",
      "slug": "string",
      "kantor_name": "string (e.g., Kantor BBWS PJ Kab. Gowa)",
      "tipe": "string (pusat|cabang)",
      "address": "string (alamat kantor induk)",
      "is_active": "boolean",
      "is_writable": "boolean (true if own region or super_admin)",
      "office_locations": [
        { "id": "integer", "nama_lokasi": "string (Bendungan A)", "lat": "decimal", "lng": "decimal", "radius_m": "integer 50–1000", "address": "string", "is_active": "boolean" },
        { "id": "integer", "nama_lokasi": "string (Jembatan B)", "lat": "decimal", "lng": "decimal", "radius_m": "integer", "address": "string", "is_active": "boolean" }
      ]
    }
  ]
}
```
- **Status Codes:** 200 (OK), 401/403, 500

#### Create Kantor/Wilayah (Super Admin Only)
- **Method:** `POST`
- **Path:** `/api/super-admin/regions` (admin_wilayah 403)
- **Description:** Create wilayah baru + **titik proyek awal** (minimal 1 titik). Only Super Admin. Input tiap titik via map picker Leaflet + radius fleksibel.
- **Auth Level:** Admin (super_admin)
- **Request Body:**
```json
{
  "name": "string (required, e.g., Kabupaten Gowa)",
  "kantor_name": "string (required, e.g., Kantor BBWS PJ Kab. Gowa)",
  "tipe": "string (optional: pusat|cabang, default cabang)",
  "address": "string (optional, alamat kantor induk)",
  "is_active": "boolean (optional, default true)",
  "office_locations": [{ "nama_lokasi": "string (required, ex Bendungan A)", "lat": "decimal (required, -90 to 90)", "lng": "decimal (required, -180 to 180)", "radius_m": "integer (optional 50–1000 default 200)", "address": "string (optional)" }]
}
```
- **Status Codes:** 201 (Created), 403 (if admin_wilayah), 422, 500

#### Update Kantor Lokasi & Radius
- **Method:** `PUT`
- **Path:** `/api/{super-admin|wilayah}/regions/{id}`
- **Description:** Update kantor metadata atau titik proyek. Super Admin can update any kantor + N titik; **Admin Wilayah can update only own wilayah** (own region_id) — untuk **tambah/edit/hapus N titik proyek di wilayahnya sendiri** (Bendungan A, Jembatan B). Others 403.
- **Auth Level:** Admin (super_admin: any, admin_wilayah: own only)
- **Request Body:** Same as create (all optional), plus per titik `id` for update; `radius_m` 50–1000 per titik; supports `office_locations` array upsert.
- **Status Codes:** 200, 403 (other region), 404, 422, 500

#### Titik Proyek (Office Locations) — CRUD per Wilayah

- **Method:** `GET` — **Path:** `/api/{super-admin|wilayah}/regions/{regionId}/office-locations` — List N titik proyek di wilayah. Super Admin all; Admin Wilayah own read, other read-only. **Status Codes:** 200, 403
- **Method:** `POST` — **Path:** `/api/{super-admin|wilayah}/regions/{regionId}/office-locations` — Tambah titik proyek (nama_lokasi, lat, lng, radius_m 50–1000, address). Super Admin any region; Admin Wilayah only own region — contoh tambah **Jembatan B** di wilayahnya yang sudah punya Bendungan A. **Body:** `{nama_lokasi, lat, lng, radius_m, address}` **Status Codes:** 201, 403, 422
- **Method:** `PUT` — **Path:** `/api/{super-admin|wilayah}/regions/{regionId}/office-locations/{id}` — Update titik (nama/radius/lat/lng). Scope own region untuk Admin Wilayah. **Status Codes:** 200, 403, 404
- **Method:** `DELETE` — **Path:** `/api/{super-admin|wilayah}/regions/{regionId}/office-locations/{id}` — Hapus titik (blocked jika last titik — minimal 1 per wilayah). **Status Codes:** 200, 403, 404, 422 (last site)

#### Delete Region (Super Admin Only)
- **Method:** `DELETE`
- **Path:** `/api/admin/regions/{id}`
- **Description:** Delete kantor. Only Super Admin. Blocked if employees exist in region.
- **Auth Level:** Admin (super_admin)
- **Status Codes:** 200, 403, 404, 500

#### List Admin Wilayah Accounts (Super Admin)
- **Method:** `GET`
- **Path:** `/api/admin/admin-users`
- **Description:** List admin wilayah accounts with region assignment.
- **Auth Level:** Admin (super_admin)
- **Response Body:** `data: [{id, name, email, role, region, created_at}]`
- **Status Codes:** 200, 403, 500

#### Create/Update Admin Wilayah (Super Admin)
- **Method:** `POST /api/admin/admin-users`, `PUT /api/admin/admin-users/{id}`
- **Description:** Create/update admin wilayah and assign region_id. Super Admin only.
- **Auth Level:** Admin (super_admin)
- **Request Body:**
```json
{
  "name": "string (required)",
  "email": "string (required, unique)",
  "password": "string (required, min 8)",
  "region_id": "integer (required, must exist)"
}
```
- **Status Codes:** 201/200, 403, 422, 500

### Admin Dashboard

#### Get Dashboard Overview
- **Method:** `GET`
- **Path:** `/api/admin/dashboard`
- **Description:** Retrieve dashboard overview data including analytics summary, recent contact submissions, and quick stats.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_visitors_this_month": "integer",
      "total_contact_submissions": "integer",
      "published_posts": "integer",
      "total_projects": "integer"
    },
    "recent_submissions": [
      {
        "id": "integer",
        "name": "string",
        "email": "string",
        "subject": "string",
        "submitted_at": "datetime",
        "read": "boolean"
      }
    ],
    "top_pages": [
      {
        "page": "string",
        "views": "integer"
      }
    ]
  }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

### Global Settings Management

#### Get Global Settings
- **Method:** `GET`
- **Path:** `/api/admin/settings`
- **Description:** Retrieve all site-wide settings for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "company_name": "string",
    "logo_url": "string",
    "favicon_url": "string",
    "contact_email": "string",
    "contact_phone": "string",
    "address": "string",
    "social_media": { "facebook": "string", "twitter": "string", "linkedin": "string", "instagram": "string" },
    "jam_kerja": { "jam_masuk": "07:30", "jam_pulang": "16:00", "toleransi_late_menit": 15, "hari_kerja": ["Senin","Selasa","Rabu","Kamis","Jumat"], "timezone": "Asia/Makassar" },
    "love_max_default": "integer (1-10, default 4)",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Update Global Settings
- **Method:** `PUT`
- **Path:** `/api/admin/settings`
- **Description:** Update site-wide settings.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "company_name": "string (optional)",
  "contact_email": "string (optional, valid email)",
  "contact_phone": "string (optional)",
  "address": "string (optional)",
  "social_media": { "facebook": "string (optional)", "twitter": "string (optional)", "linkedin": "string (optional)", "instagram": "string (optional)" },
  "jam_masuk": "string (optional, HH:MM)",
  "jam_pulang": "string (optional, HH:MM)",
  "toleransi_late_menit": "integer (optional, 0-60)",
  "hari_kerja": "array (optional)",
  "love_max_default": "integer (optional, 1-10)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated settings */ },
  "message": "Settings updated successfully"
}
```
- **Status Codes:** 200 (OK), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Upload Logo
- **Method:** `POST`
- **Path:** `/api/admin/settings/logo`
- **Description:** Upload a new company logo to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `logo` file (image, max 5MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "logo_url": "string (S3 URL)"
  },
  "message": "Logo uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Upload Favicon
- **Method:** `POST`
- **Path:** `/api/admin/settings/favicon`
- **Description:** Upload a new favicon to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `favicon` file (image, max 1MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "favicon_url": "string (S3 URL)"
  },
  "message": "Favicon uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

### Page Content Management

#### Get About Us Page
- **Method:** `GET`
- **Path:** `/api/admin/pages/about`
- **Description:** Retrieve the About Us page content for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "title": "string",
    "content": "string (HTML)",
    "mission": "string",
    "vision": "string",
    "values": [
      {
        "id": "integer",
        "title": "string",
        "description": "string"
      }
    ],
    "meta_title": "string",
    "meta_description": "string",
    "og_image_url": "string",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Update About Us Page
- **Method:** `PUT`
- **Path:** `/api/admin/pages/about`
- **Description:** Update the About Us page content. Automatically creates a version history entry.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (optional)",
  "content": "string (optional, HTML)",
  "mission": "string (optional)",
  "vision": "string (optional)",
  "values": [
    {
      "title": "string",
      "description": "string"
    }
  ],
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated page */ },
  "message": "About Us page updated successfully"
}
```
- **Status Codes:** 200 (OK), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Get Page Version History
- **Method:** `GET`
- **Path:** `/api/admin/pages/{page_type}/versions`
- **Description:** Retrieve all saved versions of a specific page (e.g., `about`, `services`).
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=20`
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "version_number": "integer",
      "title": "string",
      "created_at": "datetime",
      "created_by": "string"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Specific Page Version
- **Method:** `GET`
- **Path:** `/api/admin/pages/{page_type}/versions/{version_id}`
- **Description:** Retrieve the content of a specific page version.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "version_number": "integer",
    "title": "string",
    "content": "string (HTML)",
    "created_at": "datetime",
    "created_by": "string"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Rollback Page to Version
- **Method:** `POST`
- **Path:** `/api/admin/pages/{page_type}/versions/{version_id}/rollback`
- **Description:** Restore a page to a previous version. Creates a new version entry for the rollback action.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": { /* restored page */ },
  "message": "Page rolled back to version X successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

### Services Management

#### List Services (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/services`
- **Description:** Retrieve a paginated list of all services for admin management.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string",
      "slug": "string",
      "description": "string",
      "icon_url": "string",
      "image_url": "string",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Service Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/services/{id}`
- **Description:** Retrieve full details of a specific service for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "slug": "string",
    "description": "string (HTML)",
    "icon_url": "string",
    "image_url": "string",
    "meta_title": "string",
    "meta_description": "string",
    "og_image_url": "string",
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Create Service
- **Method:** `POST`
- **Path:** `/api/admin/services`
- **Description:** Create a new service.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (required, max 255)",
  "description": "string (required, HTML)",
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* created service */ },
  "message": "Service created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Update Service
- **Method:** `PUT`
- **Path:** `/api/admin/services/{id}`
- **Description:** Update an existing service.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (optional)",
  "description": "string (optional, HTML)",
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated service */ },
  "message": "Service updated successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Service
- **Method:** `DELETE`
- **Path:** `/api/admin/services/{id}`
- **Description:** Delete a service. Associated projects are not deleted but lose the service reference.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Service deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Upload Service Icon
- **Method:** `POST`
- **Path:** `/api/admin/services/{id}/icon`
- **Description:** Upload an icon image for a service to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `icon` file (image, max 2MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "icon_url": "string (S3 URL)"
  },
  "message": "Icon uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Upload Service Image
- **Method:** `POST`
- **Path:** `/api/admin/services/{id}/image`
- **Description:** Upload a featured image for a service to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `image` file (image, max 5MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "image_url": "string (S3 URL)"
  },
  "message": "Image uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

### Portfolio / Projects Management

#### List Projects (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/projects`
- **Description:** Retrieve a paginated list of all projects for admin management.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "title": "string",
      "slug": "string",
      "client": "string",
      "project_date": "date",
      "service_id": "integer",
      "thumbnail_url": "string",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Project Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/projects/{id}`
- **Description:** Retrieve full details of a specific project for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "title": "string",
    "slug": "string",
    "description": "string (HTML)",
    "client": "string",
    "project_date": "date",
    "service_id": "integer",
    "thumbnail_url": "string",
    "gallery": [
      {
        "id": "integer",
        "image_url": "string",
        "caption": "string",
        "order": "integer"
      }
    ],
    "meta_title": "string",
    "meta_description": "string",
    "og_image_url": "string",
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Create Project
- **Method:** `POST`
- **Path:** `/api/admin/projects`
- **Description:** Create a new project.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (required, max 255)",
  "description": "string (required, HTML)",
  "client": "string (required, max 255)",
  "project_date": "date (required)",
  "service_id": "integer (optional)",
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* created project */ },
  "message": "Project created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Update Project
- **Method:** `PUT`
- **Path:** `/api/admin/projects/{id}`
- **Description:** Update an existing project.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (optional)",
  "description": "string (optional, HTML)",
  "client": "string (optional)",
  "project_date": "date (optional)",
  "service_id": "integer (optional)",
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated project */ },
  "message": "Project updated successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Project
- **Method:** `DELETE`
- **Path:** `/api/admin/projects/{id}`
- **Description:** Delete a project and all associated gallery images.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Project deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Upload Project Thumbnail
- **Method:** `POST`
- **Path:** `/api/admin/projects/{id}/thumbnail`
- **Description:** Upload a thumbnail image for a project to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `thumbnail` file (image, max 5MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "thumbnail_url": "string (S3 URL)"
  },
  "message": "Thumbnail uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Add Gallery Image to Project
- **Method:** `POST`
- **Path:** `/api/admin/projects/{id}/gallery`
- **Description:** Add an image to a project's gallery on AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `image` file (image, max 5MB) and optional `caption` (string)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "image_url": "string (S3 URL)",
    "caption": "string"
  },
  "message": "Gallery image added successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Gallery Image
- **Method:** `DELETE`
- **Path:** `/api/admin/projects/{project_id}/gallery/{gallery_id}`
- **Description:** Remove an image from a project's gallery and delete it from AWS S3.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Gallery image deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Reorder Gallery Images
- **Method:** `POST`
- **Path:** `/api/admin/projects/{id}/gallery/reorder`
- **Description:** Update the display order of gallery images.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "gallery_order": [
    {
      "id": "integer",
      "order": "integer"
    }
  ]
}
```
- **Response Body:**
```json
{
  "success": true,
  "message": "Gallery order updated successfully"
}
```
- **Status Codes:** 200 (OK), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

### Team Management

#### List Team Members (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/team`
- **Description:** Retrieve a paginated list of all team members for admin management.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string",
      "title": "string",
      "email": "string",
      "photo_url": "string",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Team Member Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/team/{id}`
- **Description:** Retrieve full details of a specific team member for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "title": "string",
    "bio": "string (HTML)",
    "email": "string",
    "phone": "string",
    "photo_url": "string",
    "social_media": {
      "linkedin": "string",
      "twitter": "string"
    },
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Create Team Member
- **Method:** `POST`
- **Path:** `/api/admin/team`
- **Description:** Create a new team member profile.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (required, max 255)",
  "title": "string (required, max 255)",
  "bio": "string (optional, HTML)",
  "email": "string (required, valid email)",
  "phone": "string (optional)",
  "social_media": {
    "linkedin": "string (optional, valid URL)",
    "twitter": "string (optional, valid URL)"
  }
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* created team member */ },
  "message": "Team member created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Update Team Member
- **Method:** `PUT`
- **Path:** `/api/admin/team/{id}`
- **Description:** Update an existing team member profile.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (optional)",
  "title": "string (optional)",
  "bio": "string (optional, HTML)",
  "email": "string (optional, valid email)",
  "phone": "string (optional)",
  "social_media": {
    "linkedin": "string (optional, valid URL)",
    "twitter": "string (optional, valid URL)"
  }
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated team member */ },
  "message": "Team member updated successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Team Member
- **Method:** `DELETE`
- **Path:** `/api/admin/team/{id}`
- **Description:** Delete a team member profile.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Team member deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Upload Team Member Photo
- **Method:** `POST`
- **Path:** `/api/admin/team/{id}/photo`
- **Description:** Upload a profile photo for a team member to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `photo` file (image, max 5MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "photo_url": "string (S3 URL)"
  },
  "message": "Photo uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

### Blog Management

#### List Blog Posts (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/blog`
- **Description:** Retrieve a paginated list of all blog posts (including drafts) for admin management.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `status=null` (optional: `draft`, `published`, `archived`)
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "title": "string",
      "slug": "string",
      "excerpt": "string",
      "author": "string",
      "status": "string (draft|published|archived)",
      "published_at": "datetime|null",
      "featured_image_url": "string",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Blog Post Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/blog/{id}`
- **Description:** Retrieve full details of a specific blog post for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "title": "string",
    "slug": "string",
    "content": "string (HTML)",
    "excerpt": "string",
    "author": "string",
    "status": "string (draft|published|archived)",
    "published_at": "datetime|null",
    "featured_image_url": "string",
    "category_id": "integer|null",
    "tags": [
      {
        "id": "integer",
        "name": "string"
      }
    ],
    "meta_title": "string",
    "meta_description": "string",
    "og_image_url": "string",
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Create Blog Post
- **Method:** `POST`
- **Path:** `/api/admin/blog`
- **Description:** Create a new blog post.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (required, max 255)",
  "content": "string (required, HTML)",
  "excerpt": "string (required, max 500)",
  "author": "string (required, max 255)",
  "status": "string (required: draft|published)",
  "category_id": "integer (optional)",
  "tags": [
    {
      "id": "integer (optional, for existing tags)",
      "name": "string (optional, for new tags)"
    }
  ],
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* created blog post */ },
  "message": "Blog post created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Update Blog Post
- **Method:** `PUT`
- **Path:** `/api/admin/blog/{id}`
- **Description:** Update an existing blog post.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (optional)",
  "content": "string (optional, HTML)",
  "excerpt": "string (optional, max 500)",
  "author": "string (optional)",
  "status": "string (optional: draft|published|archived)",
  "category_id": "integer (optional)",
  "tags": [
    {
      "id": "integer (optional)",
      "name": "string (optional)"
    }
  ],
  "meta_title": "string (optional)",
  "meta_description": "string (optional, max 160)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated blog post */ },
  "message": "Blog post updated successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Blog Post
- **Method:** `DELETE`
- **Path:** `/api/admin/blog/{id}`
- **Description:** Delete a blog post permanently.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Blog post deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Upload Blog Featured Image
- **Method:** `POST`
- **Path:** `/api/admin/blog/{id}/featured-image`
- **Description:** Upload a featured image for a blog post to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `image` file (image, max 5MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "featured_image_url": "string (S3 URL)"
  },
  "message": "Featured image uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### List Blog Categories
- **Method:** `GET`
- **Path:** `/api/admin/blog/categories`
- **Description:** Retrieve all available blog post categories.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string",
      "slug": "string",
      "post_count": "integer"
    }
  ]
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Create Blog Category
- **Method:** `POST`
- **Path:** `/api/admin/blog/categories`
- **Description:** Create a new blog post category.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (required, max 255)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "slug": "string"
  },
  "message": "Category created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Blog Category
- **Method:** `DELETE`
- **Path:** `/api/admin/blog/categories/{id}`
- **Description:** Delete a blog post category. Posts in this category lose the category reference.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Category deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### List Blog Tags
- **Method:** `GET`
- **Path:** `/api/admin/blog/tags`
- **Description:** Retrieve all available blog post tags.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string",
      "slug": "string",
      "post_count": "integer"
    }
  ]
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Create Blog Tag
- **Method:** `POST`
- **Path:** `/api/admin/blog/tags`
- **Description:** Create a new blog post tag.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "name": "string (required, max 255)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "slug": "string"
  },
  "message": "Tag created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Blog Tag
- **Method:** `DELETE`
- **Path:** `/api/admin/blog/tags/{id}`
- **Description:** Delete a blog post tag. Posts with this tag lose the tag reference.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Tag deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

### Testimonials Management

#### List Testimonials (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/testimonials`
- **Description:** Retrieve a paginated list of all testimonials (including unapproved) for admin management.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `status=null` (optional: `approved`, `pending`)
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "content": "string",
      "client_name": "string",
      "client_company": "string",
      "rating": "integer (1-5)",
      "status": "string (approved|pending)",
      "created_at": "datetime",
      "updated_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Testimonial Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/testimonials/{id}`
- **Description:** Retrieve full details of a specific testimonial for editing.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "content": "string",
    "client_name": "string",
    "client_company": "string",
    "client_title": "string",
    "rating": "integer (1-5)",
    "image_url": "string",
    "status": "string (approved|pending)",
    "created_at": "datetime",
    "updated_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Create Testimonial
- **Method:** `POST`
- **Path:** `/api/admin/testimonials`
- **Description:** Create a new testimonial.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "content": "string (required, max 1000)",
  "client_name": "string (required, max 255)",
  "client_company": "string (required, max 255)",
  "client_title": "string (optional, max 255)",
  "rating": "integer (required, 1-5)",
  "status": "string (required: approved|pending)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* created testimonial */ },
  "message": "Testimonial created successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Update Testimonial
- **Method:** `PUT`
- **Path:** `/api/admin/testimonials/{id}`
- **Description:** Update an existing testimonial.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "content": "string (optional)",
  "client_name": "string (optional)",
  "client_company": "string (optional)",
  "client_title": "string (optional)",
  "rating": "integer (optional, 1-5)",
  "status": "string (optional: approved|pending)"
}
```
- **Response Body:**
```json
{
  "success": true,
  "data": { /* updated testimonial */ },
  "message": "Testimonial updated successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Testimonial
- **Method:** `DELETE`
- **Path:** `/api/admin/testimonials/{id}`
- **Description:** Delete a testimonial permanently.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Testimonial deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Upload Testimonial Image
- **Method:** `POST`
- **Path:** `/api/admin/testimonials/{id}/image`
- **Description:** Upload a client image for a testimonial to AWS S3.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `image` file (image, max 3MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "image_url": "string (S3 URL)"
  },
  "message": "Image uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

### Contact Submissions Management

#### List Contact Submissions
- **Method:** `GET`
- **Path:** `/api/admin/contact-submissions`
- **Description:** Retrieve a paginated list of all contact form submissions.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=20`, `status=null` (optional: `unread`, `read`, `archived`)
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "name": "string",
      "email": "string",
      "subject": "string",
      "message": "string",
      "status": "string (unread|read|archived)",
      "submitted_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Get Contact Submission Detail
- **Method:** `GET`
- **Path:** `/api/admin/contact-submissions/{id}`
- **Description:** Retrieve full details of a specific contact submission. Automatically marks as read.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "name": "string",
    "email": "string",
    "subject": "string",
    "message": "string",
    "status": "string (unread|read|archived)",
    "submitted_at": "datetime"
  }
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Archive Contact Submission
- **Method:** `PUT`
- **Path:** `/api/admin/contact-submissions/{id}/archive`
- **Description:** Mark a contact submission as archived.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Submission archived successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

#### Delete Contact Submission
- **Method:** `DELETE`
- **Path:** `/api/admin/contact-submissions/{id}`
- **Description:** Delete a contact submission permanently.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "Submission deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

### Media Library Management

#### List Media Files
- **Method:** `GET`
- **Path:** `/api/admin/media`
- **Description:** Retrieve a paginated list of all uploaded media files from AWS S3.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=20`, `type=null` (optional: `image`, `video`)
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "filename": "string",
      "url": "string (S3 URL)",
      "type": "string (image|video)",
      "size": "integer (bytes)",
      "uploaded_at": "datetime"
    }
  ],
  "pagination": { /* standard pagination */ }
}
```
- **Status Codes:** 200 (OK), 401 (Unauthorized), 500 (Server Error)

#### Upload Media File
- **Method:** `POST`
- **Path:** `/api/admin/media/upload`
- **Description:** Upload a media file to AWS S3. Supports images and videos.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `file` (image or video, max 50MB)
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "filename": "string",
    "url": "string (S3 URL)",
    "type": "string (image|video)",
    "size": "integer (bytes)"
  },
  "message": "File uploaded successfully"
}
```
- **Status Codes:** 201 (Created), 422 (Unprocessable Entity), 401 (Unauthorized), 500 (Server Error)

#### Delete Media File
- **Method:** `DELETE`
- **Path:** `/api/admin/media/{id}`
- **Description:** Delete a media file from AWS S3 and the database.
- **Auth Level:** Admin
- **Request Body:** None
- **Response Body:**
```json
{
  "success": true,
  "message": "File deleted successfully"
}
```
- **Status Codes:** 200 (OK), 404 (Not Found), 401 (Unauthorized), 500 (Server Error)

### Employee Management (Lengkap HR, Region-Scoped)

#### List Employees (Admin — Per Titik)
- **Method:** `GET`
- **Path:** `/api/admin/employees` (alias `/api/super-admin/employees` & `/api/wilayah/employees`)
- **Description:** Retrieve paginated employees **per titik** (`office_location_id`). Admin Wilayah: filtered to own region by default (+ filter `Titik Proyek` per site + `__null` tanpa titik), can `?all_regions=1` to view read-only others. Super Admin sees all. **1 karyawan = 1 titik** (`office_location_id` FK, NULL = belum assign).
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `region_id=null`, `office_location_id=null (FK titik, filter per titik)`, `status=null`, `search=null` (NIK/name)
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "nik": "string",
      "nip": "string",
      "name": "string",
      "golongan": "string",
      "jabatan": "string",
      "unit_kerja": "string",
      "status_kepegawaian": "string",
      "region": { "id": "integer", "name": "string" },
      "office_location": { "id": "integer", "nama_lokasi": "string (Bendungan Bili-Bili)", "lat": "decimal", "lng": "decimal", "radius_m": "integer" } ,
      "office_location_id": "integer|null (NULL=belum di-assign, tidak bisa absen 422)",
      "foto_url": "string",
      "is_writable": "boolean (true if own region)"
    }
  ],
  "pagination": { "current_page": 1, "per_page": 15, "total": 120, "last_page": 8 }
}
```
- **Status Codes:** 200 (OK), 401/403, 500

#### Assign/Pindah Titik Proyek Karyawan (1 Karyawan = 1 Titik)
- **Method:** `POST` (atau `PUT`) — dedicated via SiteDetail per titik
- **Path:** `POST /api/{super-admin|wilayah}/regions/{regionId}/office-locations/{siteId}/assign` + `POST .../move`
- **Description:** Assign/pindah karyawan ke 1 titik proyek (1 karyawan = 1 `office_location_id`). **Kandidat tambah** hanya karyawan `regionId==region.id && office_location_id==null` (tanpa titik). Pindah set `office_location_id` ke `siteId` baru (validasi own region untuk `wilayah` guard). Mengosongkan titik (unassign) set `NULL` — karyawan tanpa titik tidak bisa absen/Love 422. Invalidate geofence cache per titik. Frontend: `Admin/SiteDetail.jsx` aksi `Tambah` / `Pindah` per titik.
- **Request Body:** `{ "employee_id": "integer (required)" }` untuk assign; `{ "employee_id": "integer", "target_office_location_id": "integer" }` untuk pindah; validasi `region_id` match & bukan cross-region.
- **Status Codes:** 200, 403 (wrong region), 404 (employee/site not found), 422 (already assigned / last-site guard), 500

#### Dedicated Page Per Titik (Inertia — Bukan API JSON)
- **Method:** `GET` (Inertia)
- **Path:** `/regions/{region}/sites/{site}` — **3 prefix**: `super_admin.sites.show` (`/super-admin/...`), `admin.sites.show` (`/admin/...` legacy), `wilayah.sites.show` (`/wilayah/...`) — dikonfirmasi `route:list` & `curl 200` untuk `101/102/201/202/301` + employees/karyawan.
- **Description:** Dedicated page per titik proyek (`Admin/SiteDetail`) — Leaflet draggable marker + circle per titik, form edit titik, anggota per titik saja, kandidat tanpa titik, N≤20, hapus last site 422. Link dari Regions `Kelola → ${base}/regions/${r.id}/sites/${s.id}`, Attendances/Cuti/Love kolom `Titik Proyek` → site.
- **Auth:** `auth:super_admin` + `role:super_admin` untuk `super-admin`, `auth:wilayah` + `role:admin_wilayah` untuk `wilayah`/`admin` (via `getAdminBase(url)` helper).

#### Get Employee Detail (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/employees/{id}`
- **Description:** Retrieve full employee detail. Admin Wilayah can view all but `is_writable` indicates if editable.
- **Auth Level:** Admin
- **Status Codes:** 200, 404, 401/403, 500

#### Create Employee (Region-Scoped, 1 Karyawan = 1 Titik)
- **Method:** `POST`
- **Path:** `/api/admin/employees` (alias `/api/super-admin/employees` & `/api/wilayah/employees`)
- **Description:** Create employee **+ optional assign 1 titik**. Admin Wilayah auto-sets region_id to own region; Super Admin must specify region_id. **Jika `office_location_id` disertakan, validasi: titik `region_id` == employee `region_id` (tidak cross-region), 1 karyawan =1 titik.** Kandidat kosong (`NULL`) → tanpa titik (tidak bisa absen 422 sampai di-assign via SiteDetail). emails unique, NIP unique if provided.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "nik": "string (required, 16 digits, unique)",
  "nip": "string (optional, unique)",
  "name": "string (required)",
  "golongan": "string (optional)",
  "jabatan": "string (required)",
  "unit_kerja": "string (required)",
  "status_kepegawaian": "string (required: PNS|PPPK|Kontrak|Honorer)",
  "email": "string (optional, email)",
  "phone": "string (optional)",
  "region_id": "integer (required for super_admin, ignored for admin_wilayah)",
  "office_location_id": "integer|null (optional, FK titik di wilayah yang sama; NULL=tanpa titik, contoh 201 Bendungan Bili-Bili — Gowa)",
  "password": "string (required, min 8)"
}
```
- **Status Codes:** 201 (Created), 403 (if trying to create outside own region / cross-region titik), 422, 500

#### Update Employee (Region-Scoped, Per Titik)
- **Method:** `PUT`
- **Path:** `/api/admin/employees/{id}` (alias super-admin/wilayah)
- **Description:** Update employee termasuk **`office_location_id`** (pindah titik: validasi titik `region_id`==employee `region_id`, 1 karyawan=1 titik; `NULL` = tanpa titik). Admin Wilayah 403 if `employee.region_id != own region_id` or target titik `region_id` mismatch. **Pindah via SiteDetail `Pindah` lebih explicit** (dedicated assign/move).
- **Auth Level:** Admin
- **Request Body (partial):** `{ "office_location_id": "integer|null", /* plus HR fields */ }`
- **Status Codes:** 200, 403, 404, 422, 500

#### Delete Employee (Region-Scoped)
- **Method:** `DELETE`
- **Path:** `/api/admin/employees/{id}`
- **Description:** Delete employee (cascades attendances/leaves or blocks if has records — configurable). Region check applies.
- **Auth Level:** Admin
- **Status Codes:** 200, 403, 404, 500

#### Upload Employee Photo
- **Method:** `POST`
- **Path:** `/api/admin/employees/{id}/photo`
- **Description:** Upload foto to S3 (`/employees/{region_id}/{employee_id}/`). Region check.
- **Auth Level:** Admin
- **Request Body:** `multipart/form-data` with `photo` (image, max 5MB)
- **Status Codes:** 201, 403, 422, 500

### Attendance Management — Validasi Titik Assigned (1 Karyawan = 1 Titik)

#### List Attendances (Admin, Region-Scoped, Per Titik)
- **Method:** `GET`
- **Path:** `/api/admin/attendances` (alias `/api/super-admin/attendances` & `/api/wilayah/attendances`)
- **Description:** List attendances **per titik** (`office_location_id`). Admin Wilayah sees own region only (default filtered `region_id==own`, plus **filter `Titik Proyek` per site incl. `__null` tanpa titik**); Super Admin can filter by `region_id` + `office_location_id` per titik. Frontend: `Attendances.jsx` `siteById()` helper, filters `Wilayah→Titik Proyek→Status`, kolom `Titik Proyek` link `GET /regions/{region}/sites/{site}`, badge `Dalam/Di luar` (`jarak <= radius(assigned)`) + `Jarak` kolom, drawer detail link titik + `S3 /attendance/{region}/{employee}/{date}` + jam `07:30–16:00` WITA.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=20`, `region_id=null`, `office_location_id=null (FK titik, `__null`=tanpa titik)`, `employee_id=null`, `date_from=null`, `date_to=null`, `status=null (on_time|late|early_leave|excused_love)`
- **Status Codes:** 200, 401/403, 500

#### Dashboard breakdown per Titik (Admin)
- **Method:** `GET` (Inertia) — `Dashboard.jsx`
- **Path:** `/super-admin` & `/wilayah` (via `getAdminBase(url)` + `OWN_REGION` fallback)
- **Description:** Dashboard menampilkan **breakdown per Titik** untuk `activeRegion`: `countsPerSite` (`employees.filter(office_location_id==s.id)`), `totalTanpaTitik` (`office_location_id==null`), header select `Semua titik` + filter `Wilayah/Titik` sync focus/visibility, cards Link `${base}/regions/${region.id}/sites/${site.id}` dengan `anggota`, `lat/lng • radius`, `Kelola`.
- **Auth:** `auth:super_admin`/`wilayah` + role

#### List Attendances (Karyawan, Own-Only, Per Titik Assigned)
- **Method:** `GET`
- **Path:** `/api/karyawan/attendances`
- **Description:** Karyawan view own history paginated — **hanya titik assigned** (1 karyawan=1 titik). Tanpa titik → kosong + note "Belum di-assign titik — hubungi Admin Wilayah". Riwayat tampil `jarak/radius` per assigned.
- **Auth Level:** Karyawan
- **Status Codes:** 200, 401, 500

#### Check-in/out Attendance (Karyawan PWA — Hanya Ke Titik Assigned)
- **Method:** `POST`
- **Path:** `/api/karyawan/attendances`
- **Description:** GPS+selfie check-in/out. **Validasi `GeofenceService::isWithinAssignedSite(lat,lng, assigned OfficeLocation)` only** — bukan `isWithinAnySite`. Jika `employee.office_location_id IS NULL` → **422 "Belum di-assign titik"**; jika `distance > radius_m(assigned)` → **422 out_of_radius assigned (tidak tercatat)**. Jam global `07:30–16:00` WITA `toleransi 15m` Senin-Jumat server-side.
- **Auth Level:** Karyawan
- **Rate Limit:** 10 requests per hour per employee (prevent spam)
- **Request Body:** `multipart/form-data` with `type` (in|out), `lat` (decimal), `lng` (decimal), `selfie` (image, max 5MB, required), `timestamp` (optional, server time used if missing), `device_info` (optional string)
- **Response Body (Success — Dalam Radius Assigned):**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "type": "string",
    "timestamp": "datetime",
    "status": "string (on_time|late|early_leave)",
    "distance_m": "integer (distance ke titik assigned)",
    "radius_m": "integer (radius assigned, ex 200)",
    "office_location_id": "integer (assigned site, ex 201)",
    "selfie_url": "string (S3 URL /attendance/{region_id}/{employee_id}/{date}/)"
  },
  "message": "Absensi berhasil — Dalam radius titik assigned"
}
```
- **Response Body (Rejected - Tanpa Titik):** `422` with `error: no_assigned_site`, `message: "Belum di-assign titik — hubungi Admin Wilayah"` (assigned `NULL`).
- **Response Body (Rejected - Di Luar Radius Assigned):** `422` with `error: out_of_radius`, `message: "Di luar radius titik assigned, tidak dapat absen"` — **cek terhadap `office_location_id` assigned saja**, tidak ada status out_of_range, langsung ditolak 422.
- **Status Codes:** 201 (Created), 422 (validation/geofence — tanpa titik / di luar radius assigned ditolak), 429, 500

#### Karyawan PWA UI (Per Titik) — Sync `_shared.js`
- **Assign sync:** `loadRegions()` + `loadEmployees()` via `_shared.js` (`DUMMY_REGIONS` 24 regions `101/102/201/202/301...` + `DUMMY_EMPLOYEES` `regionId + office_location_id` ex Andi 201, Siti 101, Budi 202, Rina null), `MOCK_KARYAWAN_ID=1` → `me` + `assigned={site,region}`; `tanpaTitik` branch warning + disabled absen/Love.
- **Absensi UI:** Banner `Ditugaskan di: {nama_lokasi} — {regionName} • {lat},{lng} • radius {radius_m}m` (atau tanpa titik 422), preview `jarak/radius` + badge `Dalam/Di luar radius titik assigned`, buttons disabled `!inRadius || tanpaTitik`.
- **Dashboard/Love/Profil/Rekap UI:** Dashboard badge titik, Love `sisa/max` + gate `jarak<=radius(assigned)` else disabled + `Tanpa titik` block, Profil header+card titik, Rekap header+calendar note `Di luar {radius}m tidak tercatat 422`.

### Love Claim Management (4 Hati — Dalam Radius Titik Assigned, 1 Level Admin Wilayah)

#### List Love Claims (Admin — Own Region, Per Titik)
- **Method:** `GET`
- **Path:** `/api/admin/love-claims` (alias `/api/super-admin/love-claims` & `/api/wilayah/love-claims`)
- **Description:** List Love Claims pending **per titik**. Admin Wilayah sees own region only + **filter `Titik Proyek` per site incl. `__null` tanpa titik**, kolom `Titik Proyek` link `GET /regions/{region}/sites/{site}`, badge `Dalam/Di luar` (`jarak <= radius_m(assigned)`) + Approve disabled `sisaLove==0 || !inRadius`. Tanpa titik / di luar assigned tidak bisa claim (absen ditolak 422 → tidak ada claim). Super Admin sees all.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `status=null` (pending|approved|rejected), `region_id=null`, `office_location_id=null (FK titik, per-titik filter)`, `jarak` filter implicit via assigned
- **Status Codes:** 200, 401/403, 500
- **Admin UI:** `Cuti.jsx`/`Love.jsx` per titik — `siteById`, `siteFilter` `__null`, `sitesForWilayah` from `regionsData._shared`, kolom/link `Titik Proyek` `${base}/regions/.../sites/...`, `jarak/radius Dalam/Di luar`, `Approve` disabled `sisaLove 0 || !inRadius`.

#### List Own Love Claims (Karyawan)
- **Method:** `GET`
- **Path:** `/api/karyawan/love-claims`
- **Description:** Karyawan list own claims (pending/approved/rejected) with love balance.
- **Auth Level:** Karyawan
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "attendance": { "id": "integer", "timestamp": "datetime", "status": "late", "distance_m": "integer" },
      "alasan": "string",
      "status": "string",
      "created_at": "datetime"
    }
  ],
  "love_balance": { "period": "YYYY-MM", "sisa": "integer", "max": "integer", "reset_at": "datetime" }
}
```
- **Status Codes:** 200, 401, 500

#### Get Love Balance (Karyawan)
- **Method:** `GET`
- **Path:** `/api/karyawan/love-balance`
- **Description:** Get current month love balance (sisa/max, reset_at). Also returned in dashboard.
- **Auth Level:** Karyawan
- **Status Codes:** 200, 401, 500

#### Create Love Claim (Karyawan — Dalam Radius Titik Assigned, Bulan Yang Sama, 1 Karyawan = 1 Titik)
- **Method:** `POST`
- **Path:** `/api/karyawan/love-claims`
- **Description:** Ajukan 1 Love untuk 1 late **dalam radius titik assigned** (1 karyawan=1 titik). Validasi: `employee.office_location_id != null` (tanpa titik → 422 `no_assigned_site`); attendance milik sendiri, `status=late`, `isWithinAssignedSite(distance <= radius_m(assigned))` (**bukan `isWithinAnySite` — cek terhadap titik assigned saja**), belum ada claim untuk attendance tersebut, **bulan yang sama** (YYYY-MM sama, hari beda boleh — bukan harus hari yang sama 00:00–23:59 WITA), `love_sisa>0`.
- **Auth Level:** Karyawan
- **Rate Limit:** 4 claims per month per employee (max love default 4)
- **Request Body:** `multipart/form-data` with `attendance_id` (integer, required), `alasan` (string, required, max 500), `dokumen` (file, required, PDF/image, max 5MB)
- **Response Body (Success — Dalam Assigned):**
```json
{
  "success": true,
  "data": { "id": "integer", "status": "pending", "love_sisa": "integer", "office_location_id": "integer (assigned)" },
  "message": "Love claim diajukan, menunggu approval Admin Wilayah"
}
```
- **Response Body (Rejected — Tanpa Titik):** `422` `{ "success": false, "error": "no_assigned_site", "message": "Belum di-assign titik — hubungi Admin Wilayah" }`
- **Response Body (Rejected — Di Luar Radius Assigned):** `422` `{ "success": false, "error": "out_of_radius", "message": "Di luar radius titik assigned tidak dapat pakai Love" }` (cek `distance > radius_m(assigned)` only)
- **Response Body (No Love):** `422` `{ "error": "no_love", "message": "Sisa Love 0, tidak dapat ajukan" }`
- **Status Codes:** 201 (Created), 422 (validation/no_assigned_site/out_of_radius/no_love/duplicate/bulan-beda), 429, 500
- **PWA Gate:** Button `Gunakan Love` disabled if `tanpaTitik || !inRadius || sisa===0`; preview `jarak/radius` + badge `Dalam/Di luar radius titik assigned`.

#### Approve/Reject Love Claim (Admin Wilayah — 1 Level, Cek Titik Assigned)
- **Method:** `POST`
- **Path:** `/api/admin/love-claims/{id}/approve` and `/api/admin/love-claims/{id}/reject` (alias `/api/wilayah/...` own region)
- **Description:** 1 level approval by Admin Wilayah own region. Checks: `claim.region_id == admin.region_id`, `claim.status=pending`, `attendance.status==late`, **`isWithinAssignedSite(distance <= radius_m(assigned))`** (assigned site 1=1 — approval blocked `!inRadius`); tanpa titik tidak ada claim. On approve: `love_sisa-1`, `attendance.status → excused_love`, `claim.status=approved`. On reject: `claim.status=rejected`, love not deducted. Notifications to karyawan.
- **Auth Level:** Admin (admin_wilayah own region; super_admin can also approve any but primary is admin cabang)
- **Request Body (optional):** `{ "notes": "string (optional, max 500)" }`
- **Status Codes:** 200, 403 (wrong region / !inRadius assigned), 404, 409 (already processed), 500

### Leave Management (Cuti Berjenjang, Per Titik)

#### List Leave Requests (Admin, Region-Scoped, Per Titik)
- **Method:** `GET`
- **Path:** `/api/admin/leave-requests` (alias super-admin/wilayah)
- **Description:** List cuti **per titik**. Admin Wilayah sees own region pending + **filter `Titik Proyek` per site incl. `__null` tanpa titik**, kolom Link `Titik Proyek` `GET /regions/{region}/sites/{site}`. Super Admin sees all.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `status=null`, `level=null` (1|2|3), `region_id=null`, `office_location_id=null (FK titik per-titik filter)`
- **Status Codes:** 200, 401/403, 500
- **Admin UI:** `Cuti.jsx` per titik — `siteById`, `siteFilter` `__null`, `sitesForWilayah`, kolom/link `Titik Proyek` `${base}/regions/.../sites/...`.

#### List Own Leave Requests (Karyawan)
- **Method:** `GET`
- **Path:** `/api/karyawan/leave-requests`
- **Description:** Karyawan list own requests.
- **Auth Level:** Karyawan
- **Status Codes:** 200, 401, 500

#### Create Leave Request (Karyawan)
- **Method:** `POST`
- **Path:** `/api/karyawan/leave-requests`
- **Description:** Submit cuti. Auto sets region_id from karyawan.
- **Auth Level:** Karyawan
- **Request Body:**
```json
{
  "jenis": "string (required: tahunan|sakit|besar|lainnya)",
  "tgl_mulai": "date (required, Y-m-d, not past)",
  "tgl_selesai": "date (required, after tgl_mulai)",
  "alasan": "string (required, max 1000)",
  "dokumen": "file (optional, pdf/image, max 5MB)"
}
```
- **Status Codes:** 201, 422, 401, 500

#### Approve/Reject Leave Request (Berjenjang)
- **Method:** `POST`
- **Path:** `/api/admin/leave-requests/{id}/approve` and `/api/admin/leave-requests/{id}/reject`
- **Description:** Berjenjang approval. Level 1 = Atasan Langsung (could be Admin Wilayah or designated employee), Level 2 = Admin Wilayah, Level 3 = Super Admin. System checks `current_approver_level` and auth role. On approve, increments level unless final; on reject, sets status=rejected terminal. Sends notification.
- **Auth Level:** Admin (level-dependent)
- **Request Body (optional):** `{ "notes": "string (optional, max 500)" }`
- **Response Body:** `{ "success": true, "data": { "status": "approved_level1|approved|rejected", "current_approver_level": 2 }, "message": "Cuti di-approve" }`
- **Status Codes:** 200, 403 (wrong level/region), 404, 422, 500

#### Get Leave Request Detail
- **Method:** `GET`
- **Path:** `/api/admin/leave-requests/{id}` and `/api/karyawan/leave-requests/{id}`
- **Description:** Detail with approval timeline. Karyawan can only view own.
- **Auth Level:** Admin (region check) | Karyawan (own)
- **Status Codes:** 200, 403, 404, 500

#### Reset Password Admin Wilayah (Super Admin)
- **Method:** `POST`
- **Path:** `/api/admin/admin-users/{id}/reset-password`
- **Description:** Reset password Admin Wilayah — hanya Super Admin.
- **Auth Level:** Admin (super_admin)
- **Status Codes:** 200, 403, 404, 500

#### Reset via Admin — Karyawan & Admin Wilayah (Tanpa Self-Service)
- Karyawan & Admin Wilayah **tidak punya** self-service forgot-password. Reset dilakukan admin via:
  - `POST /api/super-admin/employees/{id}/reset-password` atau `POST /api/wilayah/employees/{id}/reset-password` (Karyawan — Admin Wilayah own region; Super Admin via super-admin prefix)
  - `POST /api/super-admin/admin-users/{id}/reset-password` (Admin Wilayah — hanya Super Admin)
- Legacy `POST /api/karyawan/forgot-password` (self-service) **dihapus** (FR-32).

### Announcement Management (Pengumuman)

#### List Announcements (Admin)
- **Method:** `GET`
- **Path:** `/api/admin/announcements`
- **Description:** List all announcements. Admin Wilayah sees all but can only edit own region + global read-only. Super Admin all.
- **Auth Level:** Admin
- **Query Parameters:** `page=1`, `per_page=15`, `scope=null` (global|region), `region_id=null`
- **Status Codes:** 200, 401, 500

#### Create Announcement (Scope-Checked)
- **Method:** `POST`
- **Path:** `/api/admin/announcements`
- **Description:** Create pengumuman. Super Admin can create global or any region; Admin Wilayah can only create with scope=region and region_id=own region.
- **Auth Level:** Admin
- **Request Body:**
```json
{
  "title": "string (required, max 255)",
  "content": "string (required, HTML)",
  "scope": "string (required: global|region)",
  "region_id": "integer (required if scope=region, must be own region for admin_wilayah)",
  "attachment": "file (optional, max 10MB)",
  "is_pinned": "boolean (optional)",
  "published_at": "datetime (optional, default now)"
}
```
- **Status Codes:** 201, 403, 422, 500

#### Update/Delete Announcement (Owner Check)
- **Method:** `PUT /api/admin/announcements/{id}`, `DELETE /api/admin/announcements/{id}`
- **Description:** Only creator region or Super Admin can edit/delete. Admin Wilayah 403 on global or other region.
- **Auth Level:** Admin
- **Status Codes:** 200, 403, 404, 500

#### List Announcements (Karyawan Inbox)
- **Method:** `GET`
- **Path:** `/api/karyawan/announcements`
- **Description:** Inbox: combined global + own region announcements, sorted pinned then newest, with read status.
- **Auth Level:** Karyawan
- **Response Body:**
```json
{
  "success": true,
  "data": [
    {
      "id": "integer",
      "title": "string",
      "content": "string (HTML)",
      "scope": "string",
      "is_pinned": "boolean",
      "published_at": "datetime",
      "is_read": "boolean",
      "attachment_url": "string|null"
    }
  ],
  "pagination": { "current_page": 1, "per_page": 15, "total": 30 }
}
```
- **Status Codes:** 200, 401, 500

#### Mark Announcement as Read (Karyawan)
- **Method:** `POST`
- **Path:** `/api/karyawan/announcements/{id}/read`
- **Description:** Mark as read (idempotent, creates announcement_reads).
- **Auth Level:** Karyawan
- **Status Codes:** 200, 404, 401, 500

### Karyawan PWA Auth & Profile

#### Karyawan Login (Email + Password)
- **Method:** `POST`
- **Path:** `/api/karyawan/login`
- **Description:** Login via email untuk semua role. Rate-limited per email + IP. Returns session cookie (Sanctum karyawan guard) + karyawan data.
- **Auth Level:** Public
- **Rate Limit:** 5 failed attempts per 15 minutes per IP + per email
- **Request Body:**
```json
{
  "email": "string (required, valid email)",
  "password": "string (required, min 8)"
}
```
- **Response Body (Success):**
```json
{
  "success": true,
  "data": {
    "employee": {
      "id": "integer",
      "nik": "string",
      "name": "string",
      "region": { "id": "integer", "name": "string" },
      "jabatan": "string"
    }
  },
  "message": "Login berhasil"
}
```
- **Status Codes:** 200, 401, 429, 500

#### Karyawan Logout
- **Method:** `POST`
- **Path:** `/api/karyawan/logout`
- **Description:** Terminate karyawan session.
- **Auth Level:** Karyawan
- **Status Codes:** 200, 401, 500

#### Get Own Profile (Karyawan — Include Titik Assigned)
- **Method:** `GET`
- **Path:** `/api/karyawan/me`
- **Description:** Retrieve own Lengkap HR profile **+ titik assigned** (`office_location` 1 karyawan=1 titik, NULL=belum assign). PWA header/badge titik + card titik detail.
- **Auth Level:** Karyawan
- **Response Body:**
```json
{
  "success": true,
  "data": {
    "id": "integer",
    "nik": "string",
    "nip": "string",
    "name": "string",
    "golongan": "string",
    "jabatan": "string",
    "unit_kerja": "string",
    "status_kepegawaian": "string",
    "region": { "id": "integer", "name": "string" },
    "office_location": { "id": "integer|null", "nama_lokasi": "string (ex Bendungan Bili-Bili)", "lat": "decimal", "lng": "decimal", "radius_m": "integer", "address": "string" },
    "office_location_id": "integer|null (NULL=Belum di-assign titik — hubungi Admin Wilayah, tidak bisa absen/Love 422)",
    "foto_url": "string",
    "email": "string",
    "phone": "string"
  }
}
```
- **Status Codes:** 200, 401, 500

#### Update Own Profile (Limited Fields)
- **Method:** `PUT`
- **Path:** `/api/karyawan/me`
- **Description:** Update limited fields: foto, phone, email, password. Cannot change NIK/NIP/golongan/jabatan/unit/status/region.
- **Auth Level:** Karyawan
- **Request Body:**
```json
{
  "phone": "string (optional)",
  "email": "string (optional, email)",
  "password": "string (optional, min 8, requires current_password)",
  "current_password": "string (required if changing password)"
}
```
- **Status Codes:** 200, 422, 401, 500

#### Upload Own Photo (Karyawan)
- **Method:** `POST`
- **Path:** `/api/karyawan/me/photo`
- **Description:** Upload own foto to S3.
- **Auth Level:** Karyawan
- **Request Body:** `multipart/form-data` with `photo` (image, max 5MB)
- **Status Codes:** 201, 422, 401, 500

---

## Error Handling & Status Codes

All endpoints follow standard HTTP status codes:

| Code | Meaning | Example Scenario |
|:---|:---|:---|
| **200** | OK | Successful GET, PUT, or DELETE request. |
| **201** | Created | Successful POST request creating a new resource. |
| **400** | Bad Request | Malformed request syntax or invalid parameters. |
| **401** | Unauthorized | Missing or invalid authentication credentials. |
| **403** | Forbidden | Authenticated but lacks permission (region mismatch, own-data violation, wrong approval level). |
| **404** | Not Found | Requested resource does not exist. |
| **422** | Unprocessable Entity | Request validation failed (e.g., invalid email format). |
| **429** | Too Many Requests | Rate limit exceeded (login, contact form). |
| **500** | Server Error | Internal server error or unexpected exception. |

---

## Rate Limiting

The following endpoints are rate-limited to prevent abuse:

| Endpoint | Limit | Window |
|:---|:---|:---|
| `POST /api/super-admin/login` | 5 failed attempts | 15 minutes per IP (guard super_admin) |
| `POST /api/wilayah/login` | 5 failed attempts | 15 minutes per IP (guard wilayah) |
| `POST /api/admin/login` (alias legacy) | 5 failed attempts | 15 minutes per IP (deprecated, maps to super-admin) |
| `POST /api/karyawan/login` | 5 failed attempts | 15 minutes per IP + per email |
| `POST /api/karyawan/attendances` | 10 requests | 1 hour per employee |
| `POST /api/karyawan/love-claims` | 4 requests | 1 month per employee (max love) |
| `POST /api/public/contact` | 5 requests | 1 hour per IP |

Rate limit information is included in response headers:
```
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 3
X-RateLimit-Reset: 1234567890
```

---

## CORS & Security Headers

All API responses include the following security headers:

```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
Content-Security-Policy: default-src 'self'
```

CORS is configured to allow requests only from the same origin (same-site requests only).

---

## File Upload Specifications

All file uploads are stored on AWS S3 with the following specifications:

| File Type | Max Size | Allowed Formats | S3 Path |
|:---|:---|:---|:---|
| **Images** | 5 MB | JPG, PNG, WebP | `/uploads/images/{year}/{month}/{filename}` |
| **Videos** | 50 MB | MP4, WebM | `/uploads/videos/{year}/{month}/{filename}` |
| **Logo/Favicon** | 1-5 MB | PNG, ICO, SVG | `/uploads/branding/{filename}` |
| **Absensi Selfie** | 5 MB | JPG, PNG, WebP | `/attendance/{region_id}/{employee_id}/{date}/{uuid}.jpg` |
| **Employee Foto** | 5 MB | JPG, PNG, WebP | `/employees/{region_id}/{employee_id}/{uuid}.jpg` |
| **Cuti Dokumen** | 5 MB | PDF, JPG, PNG | `/leaves/{region_id}/{employee_id}/{uuid}.pdf` |
| **Love Dokumen** | 5 MB | PDF, JPG, PNG | `/love-claims/{region_id}/{employee_id}/{uuid}.pdf` |
| **Announcement Attachment** | 10 MB | PDF, JPG, PNG, DOCX | `/announcements/{scope}/{id}/{filename}` |

All files are served via CloudFront CDN for optimal performance. File names are sanitized and renamed with a UUID prefix to prevent conflicts.