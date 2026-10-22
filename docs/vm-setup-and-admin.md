# VM Setup and Admin Access

This runbook covers two separate environments:

- **Local test** — uses `.env.local`, a local MySQL database, and no Redis.
- **VM / production** — uses `.env` and the Docker Compose stack.

The current `.env` is the production file. Do not use it for local work.

---

## 1. Staff authentication model

- The admin panel is Filament at `/admin`.
- It uses the `web` guard and the `users` table.
- There is **no public staff registration route**. Staff accounts are created from the CLI with `php artisan app:create-admin`.
- Customer accounts are separate: they use the `customer` guard and the `customers` table, and cannot authenticate to `/admin`.
- The first admin email for this project is **`smtp3dexpert@gmail.com`**.
- The login form accepts either the email address or the username.

Roles:

| Role | Access |
|---|---|
| `superadmin` | Everything, including users and destructive actions |
| `admin` | Content, events, registrations, curriculum, projects, hiring, customers, enrollments, payments, media, settings, audit |
| `lms_admin` | Curriculum, enrollments, assignments, and read-only media |
| `editor` | Content, events, registrations, projects, and read-only media |

---

## 2. Local test setup

The local environment file is `.env.local`. It is ignored by Git.
It uses the local MySQL server, database `3dexpert_laravel`, `root` with an empty password, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=sync`, and Gmail SMTP (`smtp.gmail.com:587`, `MAIL_SCHEME=smtp`).

If `.env.local` does not exist, create it from your local backup or copy `.env` and change at least:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_HOST=127.0.0.1
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=false
CACHE_STORE=database
QUEUE_CONNECTION=sync
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=smtp3dexpert@gmail.com
MAIL_PASSWORD="replace-with-gmail-app-password"
MAIL_FROM_ADDRESS="smtp3dexpert@gmail.com"
```

Generate a local-only key (do not reuse the production key):

```bash
php artisan --env=local key:generate --force
```

Create the database tables:

```bash
php artisan --env=local migrate --force
```

Optional demo content:

```bash
php artisan --env=local db:seed
```

Create the local administrator:

```bash
php artisan --env=local app:create-admin \
  --name="Admin 3D Expert" \
  --username=admin \
  --email=smtp3dexpert@gmail.com \
  --role=superadmin
```

The command prompts for a password. Use at least 12 characters.

Verify the account:

```bash
php artisan --env=local app:list-admins
```

Start the application:

```bash
npm run build
php artisan --env=local serve
```

Open:

```text
http://localhost:8000/admin
```

### Local administrator credential

- Email: `smtp3dexpert@gmail.com`
- Username: `admin`
- Role: `superadmin`
- Password: the temporary password delivered to the owner separately; it is intentionally not written to this repository.

Change the password after the first login. If it is lost, create another superadmin with `app:create-admin` (use a different email) or reset it from the panel while logged in.

### Local password recovery

Because local mail uses Gmail SMTP with `QUEUE_CONNECTION=sync`, reset and verification emails are sent immediately. If delivery fails, check the Gmail app password and the Laravel log. For a local lockout, create another superadmin with the same command.

---

## 3. VM / production setup

### 3.1 Prepare the environment file

On the VM, create `.env` from `.env.example` and set the production values. At minimum:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
DB_CONNECTION=mysql
DB_HOST=mysql
DB_DATABASE=3dexpert_laravel
DB_USERNAME=3dexpert
DB_PASSWORD=replace-with-a-strong-password
DB_ROOT_PASSWORD=replace-with-a-strong-root-password
SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=true
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=smtp3dexpert@gmail.com
MAIL_PASSWORD=replace-with-the-gmail-app-password
MAIL_FROM_ADDRESS="smtp3dexpert@gmail.com"
```

Notes:

- Use a domain with HTTPS when `SESSION_SECURE_COOKIE=true`. A secure cookie will not be sent over plain HTTP, so login will appear to fail.
- For a temporary HTTP-only IP setup, set `SESSION_SECURE_COOKIE=false` until HTTPS is enabled.
- `MAIL_PASSWORD` must be a Google app password, not the normal Gmail password.
- Use `MAIL_SCHEME=smtp` with port `587` for STARTTLS. `MAIL_SCHEME=tls` is not a valid Symfony mailer scheme and fails with `The "tls" scheme is not supported`. Use `smtps` only for implicit TLS on port `465`.
- Never commit `.env`; it is already ignored.
- Keep `APP_KEY` stable. Changing it invalidates encrypted cookies, sessions, and encrypted data.

### 3.2 Build and start the stack

```bash
docker compose up -d --build
docker compose ps
```

The services are `app`, `worker`, `nginx`, `mysql`, and `redis`.

### 3.3 Run migrations

```bash
docker compose exec app php artisan migrate --force
```

### 3.4 Choose the initial data source

For demo data only:

```bash
docker compose exec app php artisan db:seed
```

For the real legacy import, run the report first:

```bash
docker compose exec app php artisan app:migrate-legacy-data --dry-run
docker compose exec app php artisan app:migrate-legacy-data
```

Do not run both the demo seeder and the legacy importer against the same database.

After choosing a data source:

```bash
docker compose exec app php artisan app:import-media-assets
docker compose exec app php artisan app:verify-migration
```

### 3.5 Create the first administrator

Run the command interactively inside the application container:

```bash
docker compose exec app php artisan app:create-admin \
  --name="Admin 3D Expert" \
  --username=admin \
  --email=smtp3dexpert@gmail.com \
  --role=superadmin
```

It prompts for a password of at least 12 characters. Store it in a password manager, not in a file in the repository.

Verify:

```bash
docker compose exec app php artisan app:list-admins
```

### 3.6 Access the panel

Open:

```text
https://your-domain.example/admin
```

Log in with:

- Email: `smtp3dexpert@gmail.com`
- Or username: `admin`
- Password: the one entered in step 3.5

### 3.7 If the admin is locked out

There is no public registration or recovery form that can create staff accounts. Create another superadmin from the CLI:

```bash
docker compose exec app php artisan app:create-admin \
  --name="Recovery Admin" \
  --username=recovery-admin \
  --email=smtp3dexpert+recovery@gmail.com \
  --role=superadmin
```

The email must be unique. If you want to recover the original account instead, use the password-reset flow or reset it from a superadmin session. The CLI command refuses a duplicate email.

You can also reset a password from the panel profile page after logging in, or use the password-reset link. Password reset requires working mail configuration and a running queue worker if the notification is queued.

Check the worker:

```bash
docker compose logs --tail=100 worker
```

### 3.8 Useful admin commands

```bash
# List staff accounts without passwords
docker compose exec app php artisan app:list-admins

# Run pending migrations
docker compose exec app php artisan migrate --force

# Show application configuration summary
docker compose exec app php artisan about

# Clear caches after changing .env
docker compose exec app php artisan optimize:clear
```

After changing `.env`, restart the containers:

```bash
docker compose up -d --force-recreate
```

---

## 4. Access checklist after deployment

- [ ] `https://your-domain.example/admin` loads the login page.
- [ ] `smtp3dexpert@gmail.com` can log in.
- [ ] The dashboard shows the correct widgets for the role.
- [ ] A non-admin cannot reach `/admin`.
- [ ] A customer login cannot reach `/admin`.
- [ ] Password reset email arrives (or the queue worker processes it).
- [ ] `ini_perlu.md` is removed from the repository and the VPS after credentials are rotated.
- [ ] `SESSION_SECURE_COOKIE=true` only when the site is served over HTTPS.
