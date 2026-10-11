# Deployment Readiness Todo

Tanggal audit: 2026-10-11

Dokumen ini membandingkan kondisi repository dengan acceptance criteria pada
`docs/plans/O1` sampai `docs/plans/O7`. Status `partial` berarti sebagian
implementasi sudah ada, tetapi belum ada bukti operasional atau pengukuran yang
cukup untuk menyatakan phase selesai.

## Ringkasan status

| Phase | Status | Kesimpulan |
|---|---|---|
| O1 - Measurement Baseline | Belum terpenuhi | Belum ada keputusan hosting target, environment staging yang setara, load-test reproducible, atau baseline 50/100/250/500 |
| O2 - Request-Path Cost | Partial | Vite build tersedia dan framework cache commands terdokumentasi, tetapi `BCRYPT_ROUNDS` masih 12 dan konfigurasi production belum diverifikasi |
| O3 - Media & Static Asset Delivery | Belum terpenuhi | Media masih di-stream melalui `MediaController`; belum ada pemisahan public/private dan verifikasi disk/hash/304 |
| O4 - Query & Data Access | Partial | Fallback image sudah di-eager-load, tetapi query portfolio homepage masih tidak dibatasi dan belum ada query-count/EXPLAIN evidence |
| O5 - Caching Strategy | Partial | Event-list cache dan invalidation sudah ada; homepage belum ditulis ke cache dan stampede/hit-rate belum diuji |
| O6 - Launch Burst Handling | Belum terpenuhi | Fitur registration/event/queue ada, tetapi rate limit provider, keputusan verification gate, concurrency test, dan queue health belum dibuktikan |
| O7 - Launch Rehearsal & Sign-off | Belum terpenuhi | Belum ada rehearsal production-equivalent, monitoring/alerting, rollback drill, dan sign-off |

**Deployment decision saat ini: HOLD.** Kode dapat dipersiapkan untuk deploy,
tetapi belum layak dinyatakan siap launch pada profil 500 concurrent users.

## Implementasi tahap 1 - Redis dan queue worker

Target VPS yang dipakai:

- Ubuntu 24.04
- 2 CPU core
- 2 GB RAM
- 20 GB storage
- Nginx dan MySQL

Perubahan repository yang sudah diterapkan:

- [x] Template production `.env.example` memakai Redis untuk `SESSION_DRIVER`,
      `CACHE_STORE`, dan `QUEUE_CONNECTION`.
- [x] Redis cache memakai database Redis 1, sedangkan queue/session memakai
      database Redis 0 untuk mengurangi collision key.
- [x] Redis queue menggunakan `after_commit=true`, sehingga notification tidak
      dikonsumsi worker sebelum transaksi registrasi selesai.
- [x] Queue Redis menggunakan `block_for=5` dan `retry_after=120`.
- [x] Verification email ditempatkan pada queue `mail`, diutamakan sebelum
      queue `default`.
- [x] Unit systemd worker tersedia di
      `deploy/3dexpert-queue-worker.service`, dengan satu worker ringan,
      `--memory=128`, `--timeout=90`, dan automatic restart.
- [x] Test registrasi dan email verification: 25 test passed.

Langkah yang harus dijalankan di VPS, setelah source sudah berada di
`/var/www/3dexpert-web`:

```bash
sudo apt update
sudo apt install -y redis-server php8.4-redis
sudo systemctl enable --now redis-server
redis-cli ping
```

Salin `.env.example` menjadi `.env`, lalu isi hanya secret production di server:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.example
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE=default
REDIS_QUEUE_RETRY_AFTER=120
```

Jalankan instalasi dependency, migration, dan cache setelah `.env` valid:

## Docker dan GitHub CI

- [x] Docker image PHP 8.4-FPM dengan dependency Composer dan asset Vite.
- [x] Compose service untuk PHP-FPM, Nginx, MySQL, Redis, dan queue worker Laravel.
- [x] Health check MySQL dan Redis serta satu worker ringan untuk VPS 2 CPU/2 GB.
- [x] GitHub Actions CI untuk Composer, build frontend, dan test Laravel.
- [ ] Salin `.env.example` ke server sebagai `.env` dan isi secret hanya di server.
- [ ] Pastikan Docker Engine dan Docker Compose tersedia di VPS.
- [ ] Build dan smoke-test stack Compose di staging sebelum production.
- [ ] Tentukan registry atau strategi build jika VPS harus menarik image prebuilt.

Compose membutuhkan `DB_ROOT_PASSWORD` untuk inisialisasi MySQL. Nilai ini
harus diisi di `.env` server dan tidak boleh dikomit ke repository.
Build lokal belum dapat dijalankan karena Docker Desktop Linux engine tidak
sedang aktif pada mesin pengembangan; konfigurasi Compose tetap tervalidasi
secara statis dan harus dibuild setelah Docker Engine tersedia.

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force  # hanya jika APP_KEY belum ada
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Aktifkan worker:

```bash
sudo install -o root -g root -m 0644 deploy/3dexpert-queue-worker.service \
  /etc/systemd/system/3dexpert-queue-worker.service
sudo systemctl daemon-reload
sudo systemctl enable --now 3dexpert-queue-worker
sudo systemctl status 3dexpert-queue-worker --no-pager
```

Uji jalur queue end-to-end:

```bash
php artisan tinker --execute \
  'App\Jobs\VerifyQueuePipeline::dispatch();'
php artisan queue:failed
journalctl -u 3dexpert-queue-worker -n 100 --no-pager
```

Catatan kapasitas: dengan 2 CPU/2 GB, mulai dari **satu worker process**.
Jangan langsung menjalankan 4–8 worker seperti target VPS yang lebih besar.
Tambah worker hanya setelah p95, CPU, memory, dan queue depth terukur.
`deploy/3dexpert-queue-worker.service` memakai `--max-jobs` dan `--max-time`
agar worker direcycle secara berkala.

## O1 - Measurement Baseline

### Sudah ada

- [x] Scope dan alur launch sudah didefinisikan di dokumen O1:
      homepage -> register -> verify -> login -> browse events -> enroll.
- [x] Aplikasi memiliki seeders untuk data landing page dan feature data.
- [x] `preventLazyLoading` dan `preventAccessingMissingAttributes` aktif di
      non-production sebagai guard development.
- [x] Laravel test suite dan test untuk beberapa boundary penting tersedia.

### Belum terpenuhi

- [ ] Tetapkan hosting production: compute, web server, PHP-FPM, database,
      Redis, dan worker.
- [ ] Sediakan staging yang semirip mungkin dengan production. Jangan memakai
      Windows development machine sebagai baseline.
- [ ] Siapkan representative dataset: portfolio count aktual, portfolio tanpa
      cover, courses/lessons, events, vacancies, dan sekitar 500 customers.
- [ ] Tambahkan atau commit load-test harness yang reproducible (k6 atau
      Artillery).
- [ ] Jalankan run concurrency 50, 100, 250, dan 500.
- [ ] Jalankan shared-IP registration run untuk memvalidasi skenario CGNAT/Wi-Fi.
- [ ] Catat p50/p95/p99, status distribution, error rate, throughput, CPU,
      memory, PHP-FPM, MySQL, Redis, queue depth, dan failed jobs ke `notes.md`.
- [ ] Profile `app:import-media-assets`, termasuk peak memory, chunking, dan
      `max_allowed_packet`.

## O2 - Request-Path Cost

### Sudah ada

- [x] `composer.json` memiliki production dependency dan script frontend
      `npm run build`.
- [x] Dokumen deployment plan sudah menentukan urutan cache:
      `config:cache`, `route:cache`, `view:cache`, dan `event:cache`.
- [x] O2 sudah mengidentifikasi bahwa nilai bcrypt saat ini adalah 12.

### Belum terpenuhi

- [ ] Putuskan dan dokumentasikan target production. Jika mengikuti D15,
      ubah `BCRYPT_ROUNDS` menjadi 10 di environment production dan
      `.env.example` setelah approval operasional.
- [ ] Ukur waktu hash sebelum dan sesudah.
- [ ] Verifikasi login account lama cost 12 dan pembentukan hash baru setelah
      `needsRehash`.
- [ ] Verifikasi opcache: enabled, memory minimal 256 MB,
      `max_accelerated_files >= 20000`, dan `validate_timestamps=0` hanya di
      production.
- [ ] Audit `env()` di luar `config/`; semua hasil harus aman untuk
      `config:cache`.
- [ ] Jalankan `composer install --no-dev --optimize-autoloader`.
- [ ] Jalankan dan verifikasi seluruh framework cache setelah deploy.
- [ ] Tentukan `PHP_FPM pm.max_children`, `pm.max_requests`, timeout, dan
      MySQL `max_connections` dari hasil measurement, bukan perkiraan.
- [ ] Aktifkan gzip atau Brotli untuk HTML/CSS/JS/JSON/SVG, tanpa mengompres
      ulang image/PDF.
- [ ] Jalankan `npm run build` dan pastikan `public/build/manifest.json` ada.
- [ ] Dokumentasikan restart PHP-FPM setelah deploy jika opcache timestamp
      validation dimatikan.

## O3 - Media & Static Asset Delivery

### Sudah ada

- [x] Media memiliki metadata `media_assets`, `storage_path`, dan `sha256`.
- [x] Ada command `php artisan app:import-media-assets`.
- [x] `MediaController` memiliki extension/MIME allowlist,
      `nosniff`, dan cache-control 30 hari.
- [x] Applicant documents menggunakan jalur private yang terpisah.

### Belum terpenuhi

- [ ] Jalankan import media dan pastikan semua file memiliki record metadata.
- [ ] Klasifikasikan asset public (hero, portfolio, logo, icon) dan private
      (CV/applicant documents).
- [ ] Pindahkan asset public ke path yang dilayani web server langsung, tanpa
      PHP-FPM dan query MySQL.
- [ ] Pertahankan `MediaController` hanya untuk media private.
- [ ] Untuk private media, gunakan web-server handoff (`X-Accel-Redirect` atau
      `X-Sendfile`) bila hosting mendukungnya.
- [ ] Tambahkan `ETag` dari `sha256` dan `Last-Modified` dari `updated_at`.
- [ ] Verifikasi request conditional menghasilkan `304` tanpa body.
- [ ] Isi dan hash-verify `storage_path` untuk setiap asset.
- [ ] Hanya setelah verifikasi dan backup database, kosongkan BLOB `content`
      sebagai migration terpisah dan reversible secara teknis.
- [ ] Audit canonical URL sebelum menghapus duplicate asset.
- [ ] Uji access log untuk memastikan public image tidak masuk PHP-FPM.

## O4 - Query & Data Access

### Sudah ada

- [x] Fallback portfolio image sudah memuat `coverImage` dan `images`
      sehingga tidak lagi bergantung pada lazy loading untuk kasus tanpa cover.
- [x] `portfolioCount` menggunakan query count terpisah.
- [x] `preventLazyLoading` tidak dimatikan untuk menyembunyikan bug.

### Belum terpenuhi

- [ ] Ukur jumlah published portfolio aktual dari dataset representative.
- [ ] Batasi query portfolio homepage sesuai hasil O1:
      `limit`, load-more endpoint, atau pagination.
- [ ] Pastikan pembatasan tidak menghilangkan record yang memang harus
      tersedia di UX.
- [ ] Tambahkan guard test untuk homepage dengan portfolio tanpa cover dan
      pastikan query count tidak bertambah per row.
- [ ] Jalankan `EXPLAIN` pada query homepage, entitlement LMS, event,
      registration, dan application.
- [ ] Verifikasi composite index berdasarkan filter + sort yang benar-benar
      digunakan.
- [ ] Re-run query counts dan load test lalu catat delta terhadap O1.

## O5 - Caching Strategy

### Sudah ada

- [x] `HomeController::cacheKey()` dan invalidation observer menggunakan key
      per locale yang sama.
- [x] Event listing memakai cache plain-data per locale.
- [x] Event cache sengaja tidak menyimpan `capacity` dan `seats_taken`; nilai
      availability dibaca live.
- [x] `EventObserver` dan `CourseContentObserver` memiliki invalidation hook.
- [x] Settings memiliki memoization per request dan flush hook.

### Belum terpenuhi

- [ ] Sediakan Redis production yang benar-benar aktif. `.env.example` saat ini
      sudah diarahkan ke Redis; item ini tetap pending sampai Redis diverifikasi
      aktif di VPS dengan `redis-cli ping`.
- [ ] Implementasikan homepage cache write sesuai keputusan O5; jangan hanya
      memiliki key dan invalidation tanpa cache entry.
- [ ] Pastikan cache menyimpan plain data atau rendered output yang aman,
      bukan state Eloquent yang dapat menjadi incomplete object.
- [ ] Tambahkan stampede protection (`Cache::flexible` atau `Cache::lock`) pada
      semua cache yang invalidated oleh observer.
- [ ] Audit cache course catalog, vacancy list, event list, dan progress.
- [ ] Pastikan tidak ada availability atau entitlement-varying lesson payload
      yang masuk shared cache.
- [ ] Tentukan TTL, Redis memory, eviction policy, dan pemisahan cache/session/
      queue bila diperlukan.
- [ ] Ukur hit rate, eviction, memory, dan regeneration count setelah
      invalidation.
- [ ] Tambahkan/temukan test untuk homepage cache, event cache, dan entitlement.

## O6 - Launch Burst Handling

### Sudah ada

- [x] Registration rate-limit test tersedia dan desain tidak boleh membatasi
      seluruh user hanya berdasarkan IP.
- [x] Event seat claim memakai conditional atomic update.
- [x] Notifikasi event sudah memakai notification class.
- [x] Customer dan staff authentication dipisahkan.
- [x] Unique constraint dan model flow untuk registration/customer tersedia.

### Belum terpenuhi

- [ ] Pilih mail provider dan baca send-rate limit aktualnya.
- [ ] Dokumentasikan A20: verification wajib sebelum enrollment atau
      enrollment free event boleh lebih dahulu.
- [ ] Konfirmasi A21: launch event gratis atau berbayar. Jika berbayar,
      tambahkan rencana Snap concurrency/webhook burst.
- [x] Mail job tidak lagi memakai `sync` pada template production; verification
      mail memiliki `$tries`, `$backoff`, dan queue `mail`.
- [ ] Tambahkan rate limiting job, failed-job alert, dan verifikasi bahwa queue
      separation berjalan di VPS.
- [ ] Jalankan 500 concurrent enrollment ke satu event dengan capacity
      terbatas; assert tidak oversell dan rejection bersih.
- [ ] Ulangi untuk event unlimited (`capacity = null`).
- [ ] Pastikan transaction hanya berisi seat claim dan registration insert.
- [ ] Pastikan notification dikirim after-commit, tanpa media write, settings
      read, atau external HTTP call di dalam transaction.
- [ ] Ukur queue depth, failed jobs, MySQL connections, dan provider throttle.

## O7 - Launch Rehearsal & Sign-off

### Sudah ada

- [x] Checklist correctness dan security boundary sudah didefinisikan di O7.
- [x] Ada test untuk customer guard isolation, registration rate limit, dan
      route integrity.

### Belum terpenuhi

- [ ] Jalankan full-path rehearsal di production-equivalent environment pada
      concurrency 50/100/250/500.
- [ ] Sertakan shared-IP registration dan single-event enrollment hotspot.
- [ ] Re-run entitlement, guard isolation, route integrity, rate-limit,
      progress negative response, private applicant document, dan webhook
      idempotency tests setelah optimasi.
- [ ] Manual-check locked lesson sebagai guest dan baca page source.
- [ ] Pastikan `APP_DEBUG=false`, HTTPS/cookie settings benar, dan tidak ada
      secret legacy database di production environment.
- [ ] Aktifkan alerting untuk exception/error rate, p95 latency, queue depth,
      failed jobs, CPU, memory, disk, MySQL connections/slow queries, Redis
      memory/evictions, dan mail bounce/complaint rate.
- [ ] Tulis dan uji rollback procedure.
- [ ] Tulis incident runbook untuk queue backlog, mail throttle, enrollment
      failure/oversell, maintenance mode, dan worker scaling.
- [ ] Tentukan siapa yang memantau launch dan berapa lama.
- [ ] Hindari deploy perubahan yang tidak perlu pada hari launch.
- [ ] Update `notes.md` dengan angka before/after dan measured ceiling.
- [ ] Update status O1–O7 di `docs/plans/README.md` hanya berdasarkan bukti.

## Pre-deploy gate

Sebelum deploy ke production, semua item berikut wajib selesai atau memiliki
keputusan tertulis yang disetujui:

- [ ] Production hosting dan rollback target sudah jelas.
- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY`, database, mail,
      session, cache, queue, dan media disk sudah dikonfigurasi tanpa secret
      di repository.
- [ ] Rotasi semua credential yang pernah tersimpan di `.env` lokal sebelum
      dipakai di VPS; hanya masukkan credential baru ke `.env` production.
- [ ] Migrations berhasil pada database target atau staging clone.
- [ ] `composer install --no-dev --optimize-autoloader` berhasil.
- [ ] `npm run build` berhasil dan manifest tersedia.
- [ ] `php artisan config:cache`, `route:cache`, `view:cache`, dan
      `event:cache` berhasil.
- [ ] Seed/migration media sudah diverifikasi; URL image yang diperlukan tidak
      404.
- [ ] Queue worker/scheduler/systemd atau managed equivalent sudah aktif di VPS.
- [ ] Monitoring dan alerting aktif sebelum traffic diarahkan.
- [ ] Smoke test homepage, course, career, login, event, dan private document
      access berhasil.
- [ ] Backup database dan procedure rollback sudah diuji.
- [ ] O7 sign-off ditulis berdasarkan hasil pengukuran nyata, bukan asumsi.
