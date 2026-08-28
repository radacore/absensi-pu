# USERFLOW.md: BBWS Pompengan Jeneberang

## Overview

This document details the primary user flows for BBWS Pompengan Jeneberang, a corporate profile application with a public-facing website, a regional admin dashboard (Super Admin + Admin Wilayah per Kabupaten/Kota), and a mobile PWA for employees. The flows are organized by actor (Public User, Admin Wilayah, Super Admin, Karyawan) and focus on the most critical journeys: public content consumption, regional employee data management, and karyawan HR self-service (absensi GPS+selfie — di luar radius ditolak, Love 4/bulan pakai dokumen dalam radius, cuti berjenjang, pengumuman).  # Public site removed — fokus HR PWA

All flows are built on Laravel 13 (PHP 8.4+) with React 19 and Inertia.js v2 (Vite 7, Tailwind v4, MySQL 8.4 LTS, PWA), ensuring a seamless, modern user experience across public, admin, and karyawan PWA interfaces. Region isolation: Admin Wilayah write own region only (read all), Karyawan own-data-only.

---

## Flow 3: Administrator – Login to Admin Dashboard

### Trigger
The administrator navigates to the obfuscated admin login URL (e.g., `/dashboard-admin-login` or similar).

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
| 1 | Administrator | Navigates to the admin login URL | Laravel renders the login page via Inertia.js. React component displays a login form with email/username and password fields, and a "Login" button. | If the URL is incorrect or the login page is not configured, a 404 error is shown. |
| 2 | Administrator | Enters email and password | React component validates the input on the client side (required fields, email format if applicable). If validation passes, the login button is enabled. | If validation fails, an error message is displayed. The login button remains disabled. |
| 3 | Administrator | Clicks the "Login" button | React component sends a POST request to the `/api/admin/login` endpoint with the credentials and a CSRF token. | If the CSRF token is invalid, a 419 error is returned. |
| 4 | System | Validates credentials on the server side | Laravel queries the `users` table for a user with the provided email/username. If found, the password is verified using bcrypt. If credentials are correct, a session is created via Laravel Sanctum. | If the user is not found or the password is incorrect, a 401 response is returned with a generic error message ("Invalid credentials"). Rate limiting is applied to prevent brute-force attacks (e.g., max 5 attempts per minute per IP). |
| 5 | System | Returns a success response and session token | Laravel returns a 200 response with a session token (stored in a secure, HttpOnly cookie). | N/A |
| 6 | Administrator | Is redirected to the admin dashboard | Inertia.js redirects the user to the `/admin/dashboard` route. React component loads the main dashboard page. | If the redirect fails, the user remains on the login page and an error message is displayed. |

---

## Flow 4: Administrator – Manage Blog Posts (Create & Publish)

### Trigger
The administrator navigates to the Blog Management section and creates a new blog post.

### Pre-conditions
- The administrator is logged in and authenticated.
- The blog management interface is accessible.
- At least one blog category exists in the database (or categories can be created on-the-fly).

### Post-conditions
- A new blog post is created and stored in the database.
- The post is published (or saved as draft, depending on the administrator's choice).
- The post is visible on the public blog page (if published).

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Blog Management" in the admin sidebar | Inertia.js navigates to the blog management page. React component displays a list of all existing blog posts (published and draft) with columns for title, author, publication date, status, and action buttons (Edit, Delete, Preview). | If no blog posts exist, a message states "No blog posts yet" with a button to create a new post. |
| 2 | Administrator | Clicks the "Create New Post" button | Inertia.js navigates to the blog post creation form. React component displays a form with fields: title, slug (auto-generated), content (WYSIWYG editor), featured image, categories/tags, author, publication status (Draft/Published), and meta fields (meta title, meta description, OG tags). | If the form fails to load, an error message is displayed. |
| 3 | Administrator | Enters the blog post title | React component auto-generates a slug based on the title (e.g., "My First Post" → "my-first-post"). The slug can be manually edited. | If the slug is not unique, a warning is displayed and the administrator is prompted to modify it. |
| 4 | Administrator | Clicks in the content editor (WYSIWYG) and writes the blog post content | React component renders a TinyMCE editor. The administrator can format text, add links, insert images, and create lists. Content is auto-saved to the browser's local storage every 30 seconds. | If the editor fails to load, a fallback plain-text editor is provided. |
| 5 | Administrator | Clicks "Upload Featured Image" | React component opens a file picker. The administrator selects an image from their computer. | If the file is not an image (e.g., PDF), an error message is displayed. |
| 6 | System | Uploads the featured image to AWS S3 | Laravel receives the image, validates it (file type, size < 5MB), and uploads it to the S3 bucket under a timestamped folder (e.g., `/uploads/blog/2024-01-15/image.jpg`). The S3 URL is returned to the React component. | If the upload fails (e.g., S3 credentials invalid, bucket full), an error message is displayed to the administrator. The post creation is not blocked; the featured image is optional. |
| 7 | System | Displays the uploaded image preview | React component shows a thumbnail of the uploaded image with an option to remove it. | N/A |
| 8 | Administrator | Selects categories/tags for the post | React component displays a dropdown or multi-select list of existing categories/tags. The administrator can select multiple categories. | If no categories exist, the administrator can create a new category inline by typing and pressing Enter. |
| 9 | Administrator | Sets the publication status to "Published" | React component updates the status field. | If the status is set to "Draft", the post will not be visible on the public blog page. |
| 10 | Administrator | Clicks the "Publish" button | React component sends a POST request to `/api/admin/blog-posts` with all form data (title, slug, content, featured image URL, categories, status, meta fields). | If validation fails (e.g., title is empty, slug is not unique), a 422 response is returned with error messages. React component displays the errors. |
| 11 | System | Validates and stores the blog post | Laravel validates all fields. If validation passes, a new record is created in the `blog_posts` table with the provided data. A record is also created in the `content_versions` table to track the change (for content versioning). | If a database error occurs, a 500 response is returned and an error message is displayed to the administrator. |
| 12 | System | Returns a success response | Laravel returns a 200 response with the newly created post ID and a success message. | N/A |
| 13 | Administrator | Sees a success message and is redirected to the blog post list | Inertia.js redirects to the blog management page. React component displays the newly created post in the list with status "Published". | If the redirect fails, the administrator remains on the creation form and an error message is displayed. |

---

## Flow 5: Administrator – Manage Portfolio Projects (Upload & Organize)

### Trigger
The administrator navigates to the Portfolio Management section and creates a new portfolio project.

### Pre-conditions
- The administrator is logged in and authenticated.
- The portfolio management interface is accessible.
- At least one service category exists in the database.

### Post-conditions
- A new portfolio project is created and stored in the database.
- Associated images/videos are uploaded to AWS S3.
- The project is visible on the public portfolio page.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Portfolio Management" in the admin sidebar | Inertia.js navigates to the portfolio management page. React component displays a list of all portfolio projects with columns for title, client, associated service, date, and action buttons (Edit, Delete, Preview). | If no projects exist, a message states "No projects yet" with a button to create a new project. |
| 2 | Administrator | Clicks the "Create New Project" button | Inertia.js navigates to the project creation form. React component displays a form with fields: title, description, client name, project date, associated service(s), and a media upload section. | If the form fails to load, an error message is displayed. |
| 3 | Administrator | Fills in the project details (title, description, client, date) | React component validates the input on the client side (required fields, date format). | If validation fails, error messages are displayed. |
| 4 | Administrator | Selects one or more associated services | React component displays a multi-select dropdown of all available services. The administrator can select multiple services. | If no services exist, a message prompts the administrator to create services first. |
| 5 | Administrator | Clicks "Add Images/Videos" to upload media | React component opens a file picker allowing multiple file selection. The administrator can select multiple images (JPG, PNG, WebP) and videos (MP4, WebM). | If a file is not a supported format, an error message is displayed. |
| 6 | System | Uploads media files to AWS S3 | Laravel receives the files, validates them (file type, size < 50MB per file), and uploads each to the S3 bucket under a timestamped folder (e.g., `/uploads/portfolio/2024-01-15/project-id/`). The S3 URLs are returned to the React component. | If an upload fails (e.g., S3 error, file too large), an error message is displayed for that file. Other files continue to upload. |
| 7 | System | Displays uploaded media in a gallery preview | React component shows thumbnails of all uploaded images and videos in a sortable gallery. The administrator can drag to reorder or delete individual files. | If a thumbnail fails to load, a placeholder is shown. |
| 8 | Administrator | Reorders the media by dragging | React component updates the order in the UI. The order is stored in the component state. | N/A |
| 9 | Administrator | Clicks the "Save Project" button | React component sends a POST request to `/api/admin/portfolio-projects` with all form data (title, description, client, date, services, media URLs, and media order). | If validation fails, a 422 response is returned with error messages. React component displays the errors. |
| 10 | System | Validates and stores the portfolio project | Laravel validates all fields. If validation passes, a new record is created in the `portfolio_projects` table. For each media file, a record is created in the `portfolio_media` table with the S3 URL and order index. | If a database error occurs, a 500 response is returned. |
| 11 | System | Returns a success response | Laravel returns a 200 response with the newly created project ID and a success message. | N/A |
| 12 | Administrator | Sees a success message and is redirected to the portfolio list | Inertia.js redirects to the portfolio management page. React component displays the newly created project in the list. | If the redirect fails, an error message is displayed. |

---

## Flow 6: Administrator – Manage Global Settings & SEO

### Trigger
The administrator navigates to the Global Settings section to update company information and SEO defaults.

### Pre-conditions
- The administrator is logged in and authenticated.
- The global settings interface is accessible.

### Post-conditions
- Global settings are updated in the database.
- Changes are reflected on the public website (e.g., company name in the footer, logo in the header).
- SEO defaults are applied to all pages that do not have custom meta tags.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Global Settings" in the admin sidebar | Inertia.js navigates to the global settings page. React component displays a form with sections: Company Information, Contact Details, Social Media Links, and SEO Defaults. | If the settings page fails to load, an error message is displayed. |
| 2 | Administrator | Updates the company name field | React component validates the input on the client side (required, max 255 characters). | If validation fails, an error message is displayed. |
| 3 | Administrator | Clicks "Upload Logo" | React component opens a file picker. The administrator selects a logo image (PNG, SVG, JPG). | If the file is not a supported format, an error message is displayed. |
| 4 | System | Uploads the logo to AWS S3 | Laravel receives the logo, validates it (file type, size < 2MB), and uploads it to the S3 bucket under `/uploads/settings/logo/`. The S3 URL is returned to the React component. | If the upload fails, an error message is displayed. |
| 5 | System | Displays the logo preview | React component shows a thumbnail of the uploaded logo with an option to remove it. | N/A |
| 6 | Administrator | Updates contact details (phone, email, address) | React component validates the input on the client side (required fields, email format for email field). | If validation fails, error messages are displayed. |
| 7 | Administrator | Adds social media profile links (Facebook, LinkedIn, Twitter, etc.) | React component displays input fields for each social media platform. The administrator can enter profile URLs. | If a URL is invalid, a warning is displayed. |
| 8 | Administrator | Updates SEO defaults (meta title, meta description, OG image) | React component displays input fields for SEO defaults. These values are used as fallbacks for pages without custom meta tags. | If the meta description exceeds 160 characters, a warning is displayed. |
| 9 | Administrator | Clicks the "Save Settings" button | React component sends a POST request to `/api/admin/global-settings` with all form data. | If validation fails, a 422 response is returned with error messages. React component displays the errors. |
| 10 | System | Validates and updates the global settings | Laravel validates all fields. If validation passes, the `global_settings` table is updated with the new values. If a logo was uploaded, the old logo URL is replaced. | If a database error occurs, a 500 response is returned. |
| 11 | System | Clears the application cache | Laravel clears the cached global settings so that the new values are immediately reflected on the public website. | If cache clearing fails, a log entry is recorded. The settings are still updated in the database. |
| 12 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 13 | Administrator | Sees a success message | React component displays a toast notification ("Settings saved successfully"). | N/A |

---

## Flow 7: Administrator – View & Archive Contact Form Submissions

### Trigger
The administrator navigates to the Contact Submissions section to review messages from the public contact form.

### Pre-conditions
- The administrator is logged in and authenticated.
- At least one contact form submission exists in the database.

### Post-conditions
- The administrator has reviewed the submission(s).
- Submissions can be marked as archived (removed from the "New" list but retained in the database).

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Contact Submissions" in the admin sidebar | Inertia.js navigates to the contact submissions page. React component displays a list of all contact form submissions with columns for sender name, email, subject, submission date, and status (New/Archived). Submissions are sorted by date (newest first). | If no submissions exist, a message states "No submissions yet". |
| 2 | Administrator | Clicks on a submission to view details | Inertia.js navigates to the submission detail page. React component displays the full submission: sender name, email, subject, message, and submission timestamp. | If the submission has been deleted, a 404 page is shown. |
| 3 | Administrator | Reads the message content | React component displays the message text. If the message contains URLs, they are rendered as clickable links. | N/A |
| 4 | Administrator | Clicks the "Archive" button | React component sends a PATCH request to `/api/admin/contact-submissions/{id}` with the status set to "Archived". | If the request fails, an error message is displayed. |
| 5 | System | Updates the submission status | Laravel updates the `contact_submissions` table, setting the status to "Archived" for the specified submission. | If a database error occurs, a 500 response is returned. |
| 6 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 7 | Administrator | Is redirected to the submissions list | Inertia.js redirects to the contact submissions page. React component displays the updated list, with the archived submission no longer visible in the "New" tab (but visible in the "Archived" tab if the administrator filters by status). | If the redirect fails, an error message is displayed. |

---

## Flow 8: Administrator – View Content Versions & Rollback

### Trigger
The administrator navigates to a page editor (e.g., About Us) and clicks "View History" to see past versions.

### Pre-conditions
- The administrator is logged in and authenticated.
- The page editor is open (e.g., About Us page).
- At least one previous version of the page exists in the `content_versions` table.

### Post-conditions
- The administrator can view a list of all previous versions of the page.
- The administrator can select a version and rollback to it (restoring the old content).

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "View History" button in the page editor | React component sends a GET request to `/api/admin/pages/{page-id}/versions` to fetch all versions of the page. | If the request fails, an error message is displayed. |
| 2 | System | Retrieves all versions from the database | Laravel queries the `content_versions` table for all versions of the specified page, ordered by creation date (newest first). Each version includes the content, author, timestamp, and a version number. | If no versions exist, an empty list is returned. |
| 3 | System | Returns the versions list | Laravel returns a 200 response with the versions data. | N/A |
| 4 | React component | Displays a timeline of versions | React component displays a list or timeline of all versions with timestamps, author names, and a "Preview" and "Rollback" button for each version. | If the versions list is empty, a message states "No previous versions available". |
| 5 | Administrator | Clicks "Preview" for a specific version | React component sends a GET request to `/api/admin/pages/{page-id}/versions/{version-id}` to fetch the content of that version. | If the request fails, an error message is displayed. |
| 6 | System | Retrieves the version content | Laravel queries the `content_versions` table and returns the content of the specified version. | If the version does not exist, a 404 response is returned. |
| 7 | React component | Displays the version content in a modal | React component opens a modal window showing the content of the selected version. The administrator can read the old content without modifying it. | N/A |
| 8 | Administrator | Closes the preview modal and clicks "Rollback" for a version | React component sends a POST request to `/api/admin/pages/{page-id}/versions/{version-id}/rollback`. | If the request fails, an error message is displayed. |
| 9 | System | Restores the old version | Laravel retrieves the content from the specified version and updates the current page record in the `pages` table with the old content. A new entry is created in the `content_versions` table to record this rollback action. | If a database error occurs, a 500 response is returned. |
| 10 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 11 | React component | Updates the page editor with the restored content | React component refreshes the page editor form, displaying the restored content. A toast notification confirms the rollback. | N/A |

---

## Flow 9: Administrator – Manage Media Library

### Trigger
The administrator navigates to the Media Library section to browse, organize, and delete uploaded media files.

### Pre-conditions
- The administrator is logged in and authenticated.
- The media library interface is accessible.
- At least one media file has been uploaded to AWS S3 (via blog posts, portfolio projects, or global settings).

### Post-conditions
- The administrator can view all uploaded media files.
- The administrator can delete media files from AWS S3 and the database.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Media Library" in the admin sidebar | Inertia.js navigates to the media library page. React component sends a GET request to `/api/admin/media` to fetch all media files. | If the request fails, an error message is displayed. |
| 2 | System | Retrieves all media files from the database | Laravel queries the `media` table (or similar) and returns a list of all uploaded files with metadata (filename, S3 URL, file type, upload date, file size). | If no media files exist, an empty list is returned. |
| 3 | React component | Displays a grid of media files | React component displays all media files in a grid layout with thumbnails. Each thumbnail shows the file type icon (image, video, document) and the filename. | If a thumbnail fails to load, a placeholder is shown. |
| 4 | Administrator | Clicks on a media file to view details | React component displays a modal or sidebar with file details: filename, S3 URL, file type, upload date, file size, and a "Delete" button. | If the file details fail to load, an error message is displayed. |
| 5 | Administrator | Clicks the "Delete" button | React component displays a confirmation dialog asking "Are you sure you want to delete this file?" | If the administrator clicks "Cancel", the dialog is closed and no action is taken. |
| 6 | Administrator | Confirms the deletion | React component sends a DELETE request to `/api/admin/media/{media-id}`. | If the request fails, an error message is displayed. |
| 7 | System | Deletes the media file from AWS S3 and the database | Laravel deletes the file from the S3 bucket using the AWS SDK. If the deletion from S3 is successful, the corresponding record is deleted from the `media` table. | If the S3 deletion fails (e.g., file not found, S3 error), a log entry is recorded. The database record is still deleted to maintain consistency. An error message is displayed to the administrator. |
| 8 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 9 | React component | Removes the file from the grid | React component removes the deleted file from the media grid and displays a toast notification ("File deleted successfully"). | N/A |

---

## Flow 10: Administrator – Manage Team Members

### Trigger
The administrator navigates to the Team Management section and creates or edits a team member profile.

### Pre-conditions
- The administrator is logged in and authenticated.
- The team management interface is accessible.

### Post-conditions
- A new team member profile is created or an existing profile is updated.
- The team member is visible on the public Team page.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Team Management" in the admin sidebar | Inertia.js navigates to the team management page. React component displays a list of all team members with columns for name, title, photo, and action buttons (Edit, Delete). | If no team members exist, a message states "No team members yet" with a button to create a new member. |
| 2 | Administrator | Clicks the "Add Team Member" button | Inertia.js navigates to the team member creation form. React component displays a form with fields: name, job title, bio (WYSIWYG editor), photo, and optional social media links (LinkedIn, Twitter, etc.). | If the form fails to load, an error message is displayed. |
| 3 | Administrator | Fills in the team member details (name, title, bio) | React component validates the input on the client side (required fields, max lengths). | If validation fails, error messages are displayed. |
| 4 | Administrator | Clicks "Upload Photo" | React component opens a file picker. The administrator selects a photo (JPG, PNG, WebP). | If the file is not a supported format, an error message is displayed. |
| 5 | System | Uploads the photo to AWS S3 | Laravel receives the photo, validates it (file type, size < 5MB), and uploads it to the S3 bucket under `/uploads/team/`. The S3 URL is returned to the React component. | If the upload fails, an error message is displayed. |
| 6 | System | Displays the photo preview | React component shows a thumbnail of the uploaded photo with an option to remove it. | N/A |
| 7 | Administrator | Adds optional social media links | React component displays input fields for social media profiles. The administrator can enter profile URLs. | If a URL is invalid, a warning is displayed. |
| 8 | Administrator | Clicks the "Save Team Member" button | React component sends a POST request to `/api/admin/team-members` with all form data (name, title, bio, photo URL, social links). | If validation fails, a 422 response is returned with error messages. React component displays the errors. |
| 9 | System | Validates and stores the team member | Laravel validates all fields. If validation passes, a new record is created in the `team_members` table. | If a database error occurs, a 500 response is returned. |
| 10 | System | Returns a success response | Laravel returns a 200 response with the newly created team member ID and a success message. | N/A |
| 11 | Administrator | Sees a success message and is redirected to the team list | Inertia.js redirects to the team management page. React component displays the newly created team member in the list. | If the redirect fails, an error message is displayed. |

---

## Flow 11: Administrator – Manage Testimonials (Approval Workflow)

### Trigger
The administrator navigates to the Testimonials Management section to review and approve testimonials.

### Pre-conditions
- The administrator is logged in and authenticated.
- The testimonials management interface is accessible.
- At least one testimonial exists in the database (either pre-created or submitted via a form).

### Post-conditions
- Testimonials can be approved or rejected.
- Only approved testimonials are visible on the public website.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Testimonials Management" in the admin sidebar | Inertia.js navigates to the testimonials management page. React component displays a list of all testimonials with columns for client name, company, quote excerpt, status (Pending/Approved/Rejected), and action buttons (Approve, Reject, Edit, Delete). | If no testimonials exist, a message states "No testimonials yet" with a button to create a new testimonial. |
| 2 | Administrator | Clicks on a testimonial to view full details | React component displays a modal or detail page showing the full testimonial: client name, company, full quote, and current status. | If the testimonial has been deleted, a 404 page is shown. |
| 3 | Administrator | Clicks the "Approve" button | React component sends a PATCH request to `/api/admin/testimonials/{id}` with the status set to "Approved". | If the request fails, an error message is displayed. |
| 4 | System | Updates the testimonial status | Laravel updates the `testimonials` table, setting the status to "Approved" for the specified testimonial. | If a database error occurs, a 500 response is returned. |
| 5 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 6 | React component | Updates the testimonial status in the list | React component updates the testimonial's status to "Approved" and displays a toast notification ("Testimonial approved"). The testimonial is now visible on the public website. | N/A |
| 7 | Administrator | (Alternative) Clicks the "Reject" button | React component sends a PATCH request to `/api/admin/testimonials/{id}` with the status set to "Rejected". | If the request fails, an error message is displayed. |
| 8 | System | Updates the testimonial status to Rejected | Laravel updates the `testimonials` table, setting the status to "Rejected". The testimonial is hidden from the public website. | If a database error occurs, a 500 response is returned. |
| 9 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 10 | React component | Updates the testimonial status in the list | React component updates the testimonial's status to "Rejected" and displays a toast notification ("Testimonial rejected"). | N/A |

---

## Flow 12: Administrator – Edit Page Content (About Us / Services)

### Trigger
The administrator navigates to the Page Content Management section and edits a static page (e.g., About Us or Services overview).

### Pre-conditions
- The administrator is logged in and authenticated.
- The page content management interface is accessible.
- The page exists in the database.

### Post-conditions
- The page content is updated in the database.
- A new version is recorded in the `content_versions` table for audit and rollback purposes.
- The updated content is immediately visible on the public website.

### Flow Table

| No | Actor | Action/Step | System Response | Alternative/Error Path |
|:---|:---|:---|:---|:---|
| 1 | Administrator | Clicks "Page Content Management" in the admin sidebar | Inertia.js navigates to the page content management page. React component displays a list of all editable pages (About Us, Services Overview, etc.) with action buttons (Edit, View History). | If no pages exist, a message states "No pages available". |
| 2 | Administrator | Clicks "Edit" for the About Us page | Inertia.js navigates to the page editor. React component sends a GET request to `/api/admin/pages/about-us` to fetch the current page content. | If the request fails, an error message is displayed. |
| 3 | System | Retrieves the page content | Laravel queries the `pages` table and returns the current content, meta tags, and other metadata. | If the page does not exist, a 404 response is returned. |
| 4 | React component | Displays the page editor form | React component displays a form with fields: page title, slug (read-only), content (WYSIWYG editor), featured image, meta title, meta description, and OG tags. The current content is pre-filled in the form. | If the form fails to load, an error message is displayed. |
| 5 | Administrator | Edits the page content in the WYSIWYG editor | React component renders a TinyMCE editor with the current content. The administrator can format text, add links, insert images, and create lists. Content is auto-saved to the browser's local storage every 30 seconds. | If the editor fails to load, a fallback plain-text editor is provided. |
| 6 | Administrator | Updates the meta title and meta description | React component displays input fields for SEO metadata. The administrator can customize the meta title and description for this specific page. | If the meta description exceeds 160 characters, a warning is displayed. |
| 7 | Administrator | Clicks the "Save Changes" button | React component sends a PATCH request to `/api/admin/pages/about-us` with all form data (title, content, meta tags). | If validation fails, a 422 response is returned with error messages. React component displays the errors. |
| 8 | System | Validates and updates the page | Laravel validates all fields. If validation passes, the `pages` table is updated with the new content and metadata. A new entry is created in the `content_versions` table to record this change (for audit and rollback purposes). | If a database error occurs, a 500 response is returned. |
| 9 | System | Clears the application cache | Laravel clears the cached page content so that the new content is immediately reflected on the public website. | If cache clearing fails, a log entry is recorded. The content is still updated in the database. |
| 10 | System | Returns a success response | Laravel returns a 200 response with a success message. | N/A |
| 11 | Administrator | Sees a success message | React component displays a toast notification ("Page updated successfully"). | N/A |

---

### Additional Flows for Karyawan & Admin Wilayah (NEW)

## Flow 13: Karyawan – Login via NIK (Mobile PWA)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan | Buka `/karyawan/login` (PWA, obfuscated) | Render login NIK+password (React PWA, 320px+) |
| 2 | Karyawan | Input NIK 16 digit + password, tap Login | POST `/api/karyawan/login` with CSRF, rate limit 5/15min per NIK+IP |
| 3 | System | Validate NIK exists, bcrypt check, create Sanctum karyawan session with region_id | Set HttpOnly cookie, return employee + region |
| 4 | System | Redirect to `/karyawan` dashboard (PWA) | Show bottom nav: Home, Absensi, Cuti, Pengumuman, Profil |

## Flow 14: Karyawan – Absensi GPS + Selfie

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan | Tap "Absen Masuk" di PWA | Request GPS + Camera permission (explain UX) |
| 2 | Karyawan | GPS captured + selfie taken | Preview with distance to kantor (computed client-side) |
| 3 | Karyawan | Tap "Kirim Absensi" | POST `/api/karyawan/attendances` multipart (lat,lng,selfie) |
| 4 | System | Validate geofence vs regions (lat/lng/radius_m) server-side, check duplicate in/out per day | Upload selfie to S3 `/attendance/{region}/{employee}/{date}/`, insert attendances with status on_time/late/early_leave (tidak ada out_of_range — di luar radius ditolak 422) |
| 5 | System | Return status + distance_m | PWA shows toast + updates history, caches offline queue if offline |

## Flow 15: Admin Wilayah – Input Karyawan Lengkap HR (Region-Scoped)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Admin Wilayah | Login via ADMIN_PATH, navigate "Kelola Karyawan" | List filtered to own region, toggle "Lihat semua (read-only)" available |
| 2 | Admin | Tap "Tambah Karyawan" | Render form Lengkap HR: NIK, NIP, nama, golongan, jabatan, unit_kerja, status, foto, kontak |
| 3 | Admin | Submit | POST `/api/admin/employees` — middleware injects own region_id, validates NIK unique, uploads foto S3 |
| 4 | System | Insert employees with region_id, audit log | Return 201, PWA/admin list refresh, invalidate cache |

## Flow 16–18: Karyawan – Cuti Berjenjang / Pengumuman

- **Cuti:** Karyawan ajukan (jenis, tgl, alasan, dokumen) → status pending → Admin Wilayah approves level2 → Super Admin final approve → notifications + timeline UI. Any level can reject.
- **Pengumuman:** Super Admin broadcast global OR Admin Wilayah targeted region → Karyawan inbox shows global + own region, read/unread via announcement_reads, mark read on tap, pinned on top.

### Additional Flows for Karyawan & Admin Wilayah (NEW)

## Flow 13: Karyawan – Love Claim (4 Hati, Dalam Radius, 1 Level)

| No | Actor | Action | System Response |
|:---|:---|:---|:---|
| 1 | Karyawan | Late 07:52 (12m dalam radius, status=late), lihat badge late di Rekap | PWA tampilkan 4 dot gold 3/4 sisa, tombol "Gunakan Love" aktif (karena dalam radius) |
| 2 | Karyawan | Tap "Gunakan Love" → isi alasan + upload dokumen, submit | POST /api/karyawan/love-claims (attendance_id, alasan, dokumen) — validasi hari yang sama, love_sisa>0, belum ada claim |
| 3 | System | Validate & upload dokumen S3 /love-claims/... + insert pending | Return pending, love_sisa masih 3 (belum deduct) |
| 4 | Admin Cabang | Lihat queue Love Claims pending own region, review dokumen | Tap Approve → love_sisa 3→2, attendance status late→excused_love, claim approved |
| 5 | System | Notifikasi ke karyawan: Love disetujui, rekap late jadi excused | PWA update dot tetap 2/4 sisa, history claim approved |

> Di luar radius: tombol Gunakan Love disabled, tidak ada claim, absen ditolak 422.

---

## Summary of Key User Flows

BBWS Pompengan Jeneberang supports four primary actor types:

### Super Admin Pusat Flows
- **Manage Regions & Admin Wilayah:** CRUD Kabupaten/Kota + geofence, CRUD admin wilayah accounts, assign region.
- **Global Content & Broadcast:** All content + global pengumuman + all region data + final cuti approval.

### Admin Wilayah (Kabupaten/Kota) Flows
- **Employee Data (Lengkap HR):** CRUD own region employees (read all, write own), foto S3, NIK validation, audit.
- **HR Operations:** View own region attendances, approve cuti level2, kirim pengumuman wilayah.
- **Content:** As per role, manage public content (if allowed).

### Karyawan (Mobile PWA) Flows
- **Auth:** NIK+password login, PWA install, own-data-only.
- **Profile:** View own Lengkap HR, edit foto/kontak/password limited.
- **Absensi:** GPS+selfie geofenced, offline queue, history.
- **Cuti:** Ajukan + track berjenjang (pending → level1 → level2 → approved/rejected).
- **:** View list + detail own-only.
- **Pengumuman:** Inbox global+region, read/unread, attachment.

All flows leverage Inertia.js v2 for seamless navigation, React 19 + Tailwind v4 + PWA (manifest+SW) for mobile-first (320px+, 44px tap), and server-side region isolation + own-data policies for security.