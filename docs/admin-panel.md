# Admin Panel Guide

The staff panel is a Filament application at `/admin`. It manages the public site, the LMS, customers, payments, hiring, media, and audit history.

- **Local URL:** `http://localhost:8000/admin`
- **Production URL:** `https://your-domain.example/admin`
- **Local administrator:** `smtp3dexpert@gmail.com` / username `admin` / role `superadmin`
- **Password:** delivered separately; never commit it to the repository
- **Login:** email address or username plus password
- **Public staff registration:** disabled
- **Customer accounts:** separate `customers` guard; they cannot enter `/admin`

The step-by-step local and VM setup is in [vm-setup-and-admin.md](vm-setup-and-admin.md).

---

## 1. Roles and access

| Area | Editor | Admin LMS | Admin | Superadmin |
|---|---:|---:|---:|---:|
| Landing content, services, portfolios, FAQs, testimonials | Yes | No | Yes | Yes |
| Hero slides, design reviews, value propositions | Yes | No | Yes | Yes |
| Events and event registrations | Yes | No | Yes | Yes |
| Projects | Yes | No | Yes | Yes |
| Media library | View | View | View | View |
| Media deletion | No | No | No | Yes |
| Courses and curriculum | No | Yes | Yes | Yes |
| Student progress and enrollments | No | Yes | Yes | Yes |
| Assignments and grading | No | Yes | Yes | Yes |
| Customers | No | No | Yes | Yes |
| Payments and refunds | No | No | Yes | Yes |
| Hiring: vacancies and applications | No | No | Yes | Yes |
| Site settings | No | No | Yes | Yes |
| Staff users | No | No | No | Yes |
| Audit logs | No | No | Yes | Yes |

Only an active account can enter the panel. Deactivating a user blocks the panel even if an old session still exists.

A superadmin cannot demote or deactivate themselves. The last active superadmin cannot be demoted or deactivated.

---

## 2. Navigation

The sidebar is grouped as follows.

### Konten

- Hero slides
- Services
- Portfolios and portfolio images
- FAQs
- Testimonials
- Design reviews
- Value propositions
- Media library

### Program

- Courses
- Course sections/lessons (inside a course)
- Events
- Event registrations
- Assignments
- Payments

### Karier

- Job vacancies
- Job applications

### Pelanggan

- Customers
- Students and progress (`Siswa & Progress`)
- Projects

### Sistem

- Site settings
- Staff users (`Pengguna Staf`) — superadmin only
- Audit logs

A role only sees the groups it can access. Editors and LMS admins never see finance, hiring, or user-management links.

---

## 3. Common admin workflows

### 3.1 Create or edit landing content

1. Open the relevant **Konten** resource.
2. Fill the Indonesian fields. The admin panel is Indonesian-only; the per-field English inputs have been removed and no automatic translation runs on save.
3. Public `/en` pages still exist for compatibility. They use the existing English labels and any legacy English content that is already stored; new Indonesian-only content falls back to Indonesian there. Automatic browser translation is deferred (see `track.md`).
4. Set **Aktif** / **Terbit** as needed. New events default to **Terbit**; turn it off only when the event is still a draft.
5. Save and check the public page.

If a saved event does not appear on `/acara`:

- **Terbit** must be on.
- **Mulai** must be in the future, or **Selesai** must still be in the future for an ongoing event. An event whose start and end are both in the past is hidden from the list.
- **Harga** may be left blank; blank is saved as `0` (free). Negative prices are rejected.
- **Selesai** must be after **Mulai**. The form now rejects an end time before the start time.
- If the form times out after an upload failure, check `storage/logs/laravel.log` for `price cannot be null` or `UnableToRetrieveMetadata`. The event was not saved in that case; submit it again after correcting the field error.

Deleting content is a destructive action. Prefer unpublishing when the content may be referenced elsewhere.

### 3.2 Manage staff accounts (superadmin only)

Open **Sistem → Pengguna Staf**.

- Create a user with name, optional username, unique email, role, and an active toggle.
- The password is required on create and optional on edit. Passwords must be at least 12 characters.
- Use **Deactivate** instead of deleting an account so audit history keeps its actor.
- A superadmin can send a password-reset link from the user table.
- A user can change their own name, email, and password from **Profile**.

If the last superadmin is locked out, repair the original account with the
idempotent initializer:

```bash
php artisan --env=local app:init-admin \
  --name="Admin 3D Expert" \
  --username=admin \
  --email=smtp3dexpert@gmail.com \
  --force
```

Add `--password` (or set `ADMIN_PASSWORD`) to reset the password; without it the
current password is kept. The command refuses to overwrite a superadmin unless
`--force` is explicit. To create a separate recovery account instead:

```bash
php artisan --env=local app:create-admin \
  --name="Recovery Admin" \
  --username=recovery-admin \
  --email=smtp3dexpert+recovery@gmail.com \
  --role=superadmin
```

On the VM, run either command through `docker compose exec app`.

### 3.3 Courses and curriculum

1. Open **Program → Kursus**.
2. Create the course, set title, slug, cover, description, level, and status.
3. Only admin/superadmin can change **price** or **WhatsApp CTA**.
4. Only admin/superadmin can delete a course.
5. Open the course and manage its sections and lessons. The course edit page
   has a **Kurikulum** relation manager with **Tambah bab** and **Kelola materi**.
6. From the course list, use **Kelola kurikulum** to open the same curriculum.
   The **Daftar kurikulum** header action opens the cross-course overview.
7. The dedicated **Program → Kurikulum** menu lists every section grouped by
   course. Use **Kelola materi** to add lessons, and **Buka kursus** to return to
   the course.
8. A lesson can contain video/content and an optional assignment.

The panel warns when a course has no curriculum. Publish only after sections and lessons exist.

### 3.4 Students and enrollment

Open **Pelanggan → Siswa & Progress**.

- This resource lists `CourseEnrollment` rows, not customer accounts.
- It shows student name, email, phone, course, status, progress percentage, and dates.
- Use **Beri akses** to grant course access without creating a payment.
- Use revoke/disable when access must end.
- LMS admins see only enrollment data; they cannot edit the underlying customer account, payments, or refunds.

A refund should revoke the related enrollment. Do not grant manual access to bypass a failed payment unless an admin has approved it.

Course enrollment emails are automatic:

- A paid checkout sends **registration received** with the order ID, total, and a
  payment-instruction link.
- Free registration, successful payment, and staff-granted access send an
  **access activated** email.
- Granting access again to an already-active enrollment does not send a
  duplicate activation email.
- The activation link opens the course player. If mail delivery fails, check the
  queue worker and `storage/logs/laravel.log`.

### 3.5 Payments and refunds

Open **Program → Pembayaran** (admin/superadmin only).

- Payments are read-only in the panel.
- The list covers **both course enrollments and paid event registrations**. The **Jenis**
  column says `Kursus` or `Acara`; **Item** shows the course/event and **Referensi** shows
  the enrollment number or event ticket code.
- A pending order has a 15-minute payment window. For paid events that window also holds
  the seat. When it closes, the scheduled `payments:expire` command cancels the pending
  registration and returns the seat; a pending course enrollment becomes `revoked`.
- Midtrans QR codes are issued on the customer's payment page the first time it is opened.
  The panel stores the gateway payload, order ID, transaction ID, status, and timestamps.
- Refunds are available only for settled payments.
- A refund revokes the related course entitlement or event registration and records an
  audit entry. Revoking a paid event also releases its seat.
- LMS admins cannot see payments or customer financial data.

If a customer paid after the local 15-minute window had already expired, the notification
is stored with a `late_settlement` marker and does **not** restore the seat or course
access. Reconcile or refund that payment from this screen.

### 3.6 Assignments and grading

Open **Program → Tugas**, or open a lesson and use the submissions relation.

- Students upload submissions from the course player.
- Files are stored on the private `submissions` disk.
- Owners can download their own files; staff can download only with the appropriate area access.
- Staff grade a submission with score and feedback.
- Grading records an audit entry and notifies the student.
- An assignment with submissions cannot be hard-deleted by an LMS admin. Unpublish it instead.
- Only a student with active course access can submit.

### 3.7 Media library

Open **Konten → Media**.

- Media rows are reusable and can be referenced by content.
- Uploads are validated by MIME type and extension.
- The library shows where a media item is used.
- Deletion is superadmin-only and is blocked while the item is referenced.
- There is no bulk delete.

### 3.8 Hiring

Open **Karier → Lowongan Kerja** and use **Tambah lowongan** to create a
vacancy.

- Title, requirement, department, location, and employment type are required.
  The admin form is Indonesian-only; legacy English values are still readable
  by the public `/en` routes.
- Leave **Slug** blank to generate it from the title.
- Turn on **Tampilkan di situs publik** and make sure the closing date is in the
  future before publishing.
- Changing an application status sends the applicant an email. If the linked
  customer account was deleted, the email uses the application's snapshot
  address. Selecting the same status again sends nothing.

Open **Karier → Lamaran Kerja** to review applications.

- Vacancies and applications are admin/superadmin only.
- Applicant documents are private and access is logged.
- Editors and LMS admins receive 403 responses, even if they know the URL.
- The applicant may add an optional **Link portofolio** after the cover letter. It is
  validated as an `http`/`https` URL and appears as a clickable link in the application
  detail. The value is optional, so older applications simply show “Tidak dicantumkan.”

### 3.9 Site settings

Open **Sistem → Pengaturan Situs** (admin/superadmin only).

Settings cover company details, contact links, homepage copy, SEO defaults, and video URLs. Changes are audited.

The edit form also accepts older rows whose translation value is SQL `NULL`, JSON `null`,
a plain string from before the ID/EN map existed, or an empty map. These rows open normally
instead of producing a server error; saving converts them to the current translation map.

### 3.10 Audit logs

Open **Sistem → Log Audit** (admin/superadmin only).

The log records staff logins/logouts, role changes, user lifecycle events, content publication changes, authorization denials, media deletions, grading, and downloads.

Sensitive values such as passwords, tokens, payment payloads, applicant messages, and assignment text are redacted.

---

## 4. Dashboard

The dashboard is role-aware:

- **Admin/superadmin:** content, customers, enrollments, revenue, payments, applications, registrations, and submissions.
- **LMS admin:** courses, enrollments, progress, and pending submissions.
- **Editor:** content and event/registration activity.

The latest registration, application, and submission tables are lazy-loaded. The main statistics are rendered with the initial page so they are available immediately.

---

## 5. Login and account troubleshooting

### Login returns to the login page

Check `APP_URL` and `SESSION_SECURE_COOKIE`.

- If the site is served over **HTTPS**, set `APP_URL=https://...` and `SESSION_SECURE_COOKIE=true`.
- If the site is served over plain **HTTP** for temporary testing, set `SESSION_SECURE_COOKIE=false`.
- After changing `.env`, clear the config cache and restart the containers:

```bash
php artisan optimize:clear
docker compose up -d --force-recreate
```

### Password reset email does not arrive

1. Confirm `MAIL_MAILER=smtp`, `MAIL_HOST=smtp.gmail.com`, `MAIL_PORT=587`, `MAIL_SCHEME=smtp` (STARTTLS). Do not use `MAIL_SCHEME=tls`; Symfony rejects it.
2. Confirm `MAIL_USERNAME` and `MAIL_FROM_ADDRESS` are `smtp3dexpert@gmail.com`.
3. `MAIL_PASSWORD` must be a Google **app password**, not the normal account password.
4. Check the worker if the queue connection is Redis:

```bash
docker compose logs --tail=100 worker
```

5. For local testing, `.env.local` uses Gmail SMTP with `QUEUE_CONNECTION=sync`, so the reset link is sent immediately. If delivery fails, check `storage/logs/laravel.log`; `MAIL_MAILER=log` is no longer the local default.

### Account exists but cannot enter the panel

- Confirm the `role` is one of `superadmin`, `admin`, `lms_admin`, or `editor`.
- Confirm `is_active = 1`.
- Confirm the account is in `users`, not `customers`.
- Run `php artisan app:list-admins` to inspect the account without exposing the password.

### Database connection refused on the VM

- Inside Docker, `DB_HOST` must be `mysql`, not `127.0.0.1`.
- Outside Docker, use `127.0.0.1`.
- Verify the container is healthy:

```bash
docker compose ps
docker compose logs --tail=100 mysql
```

### Local Artisan commands hit the production database

Use `--env=local`:

```bash
php artisan --env=local migrate
php artisan --env=local app:list-admins
```

Without `--env=local`, Laravel loads `.env` (production).

---

## 6. Security reminders

- Do not commit `.env`, `.env.local`, `.env.local.backup`, or `ini_perlu.md`.
- Rotate the credentials that were previously stored in plaintext.
- Use a password manager for the admin password.
- Use HTTPS before enabling secure session cookies.
- Do not create staff accounts through a public form; use `app:create-admin`.
- Do not reuse customer accounts for staff access; the guards are separate by design.
- Keep the queue worker running when `QUEUE_CONNECTION=redis`.
- Review audit logs after role changes or unexpected access denials.
