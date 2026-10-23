# Panduan Deployment 3D Expert Web ke VPS

Dokumen ini adalah panduan deployment untuk project Laravel pada repository
GitHub `MCat-arch/web-3dexpert`.

Deployment dibagi menjadi dua tahap:

1. **Tahap 1 - Manual deployment:** VPS melakukan clone dari GitHub, lalu
   Docker membangun dan menjalankan aplikasi.
2. **Tahap 2 - GitHub Actions:** setiap push yang sudah disetujui dapat
   menjalankan deployment ke VPS secara otomatis melalui SSH.

Target VPS:

- Ubuntu 24.04
- 2 CPU core
- RAM 2 GB
- Storage 20 GB
- MySQL dan Redis berjalan sebagai service Docker
- Nginx berjalan sebagai container Docker
- Laravel queue worker menggunakan Redis

> Jangan menaruh `.env`, password database, `APP_KEY`, password SMTP, atau
> private SSH key di repository GitHub.

---

## Tahap 0 - Prasyarat sebelum menyentuh VPS

Pastikan hal berikut sudah benar:

- Repository sudah berhasil di-push ke GitHub.
- Branch deployment adalah `main`.
- File berikut ada di repository:
  - `Dockerfile`
  - `docker-compose.yml`
  - `docker/nginx/Dockerfile`
  - `docker/nginx/default.conf`
  - `docker/php/production.ini`
  - `.dockerignore`
  - `.env.example`
  - `.github/workflows/ci.yml`
- File `.env` tidak dilacak Git.
- Archive besar seperti `.rar` dan `.zip` tidak berada di history aktif.
- Docker image dapat dibuild di mesin lokal, atau siap dibuild langsung di VPS.

Dari komputer lokal, validasi:

```powershell
git status
git ls-remote --heads origin main
git ls-files .env
git ls-files | Select-String "\.rar$|\.zip$"
```

`git ls-files .env` harus tidak menghasilkan output.

---

# Tahap 1 - Manual deployment dari GitHub ke VPS

## 1. Persiapan VPS dan login SSH

Gunakan terminal PowerShell, Windows Terminal, atau terminal Linux:

```bash
ssh username@IP_VPS
```

Ganti:

- `username` dengan user Ubuntu VPS.
- `IP_VPS` dengan IP publik VPS.

Jika provider memberi user `root`, sebaiknya buat user deployment biasa setelah
login dan gunakan `sudo` untuk operasi administratif.

Periksa sistem:

```bash
cat /etc/os-release
free -h
df -h
nproc
```

Pastikan OS Ubuntu 24.04 dan ruang disk cukup. VPS hanya memiliki RAM 2 GB,
jadi jangan menjalankan build Docker bersamaan dengan proses berat lain.

## 2. Update sistem dan firewall

```bash
sudo apt update
sudo apt upgrade -y
sudo apt install -y ca-certificates curl git ufw
```

Buka hanya port yang diperlukan:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose
```

Jangan membuka port MySQL `3306` ke internet. MySQL hanya perlu diakses oleh
container Laravel pada network Docker internal.

## 3. Instal Docker Engine dan Compose plugin

Hapus paket Docker lama jika ada:

```bash
sudo apt remove -y docker.io docker-compose docker-doc podman-docker containerd runc || true
```

Pasang repository resmi Docker:

```bash
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
  -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] \
  https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt update
sudo apt install -y docker-ce docker-ce-cli containerd.io \
  docker-buildx-plugin docker-compose-plugin
```

Aktifkan Docker dan izinkan user menjalankan Docker:

```bash
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

Logout dan login SSH kembali agar group berubah:

```bash
exit
ssh username@IP_VPS
```

Verifikasi:

```bash
docker version
docker compose version
docker run --rm hello-world
```

Output `docker version` harus memiliki bagian `Client` dan `Server`.

## 4. Clone repository dari GitHub

Buat folder aplikasi:

```bash
sudo mkdir -p /var/www
sudo chown "$USER":"$USER" /var/www
cd /var/www
```

Untuk repository public:

```bash
git clone https://github.com/MCat-arch/web-3dexpert.git 3dexpert-web
```

Untuk repository private, gunakan SSH deploy key atau token GitHub. Jangan
menaruh token permanen di command history atau URL clone.

Masuk ke project:

```bash
cd /var/www/3dexpert-web
git checkout main
git pull --ff-only origin main
```

## 5. Buat environment production di VPS

`.env` dibuat langsung di VPS dan tidak berasal dari commit GitHub:

```bash
cd /var/www/3dexpert-web
cp .env.example .env
chmod 600 .env
nano .env
```

File production berada di host VPS pada:

```text
/var/www/3dexpert-web/.env
```

Pastikan file tersebut benar-benar ada sebelum menjalankan Docker:

```bash
pwd
ls -l /var/www/3dexpert-web/.env
test -f /var/www/3dexpert-web/.env
```

`docker-compose.yml` menggunakan `env_file: .env` untuk menyuntikkan nilai
environment ke container. Mekanisme ini **tidak** membuat file
`/var/www/html/.env` di dalam container. Karena itu, command Artisan yang perlu
membaca atau menulis file `.env` harus memasang file host tersebut secara
sementara.

Nilai minimal yang harus diatur:

```dotenv
APP_NAME="3D Expert"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://IP_VPS
FORCE_HTTPS=false
APP_LOCALE=id
APP_TIMEZONE=Asia/Jakarta

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=3dexpert_laravel
DB_USERNAME=3dexpert
DB_PASSWORD=PASSWORD_USER_MYSQL_YANG_KUAT
DB_ROOT_PASSWORD=PASSWORD_ROOT_MYSQL_YANG_KUAT

REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
REDIS_CACHE_DB=1

SESSION_DRIVER=redis
SESSION_SECURE_COOKIE=false
CACHE_STORE=redis
QUEUE_CONNECTION=redis

MAIL_MAILER=resend
RESEND_API_KEY=re_API_KEY_DARI_DASHBOARD_RESEND
MAIL_FROM_ADDRESS=info@3dexpert.pro
MAIL_FROM_NAME="${APP_NAME}"
```

Untuk tahap akses melalui IP, `APP_URL` harus memakai alamat HTTP yang benar
dan dapat dijangkau browser, misalnya `http://103.117.57.39`. Jangan memakai
`https://` sebelum TLS benar-benar dikonfigurasi pada reverse proxy/Nginx.
Jangan memakai `127.0.0.1` atau `localhost`: nilai tersebut menunjuk ke
komputer yang membuka browser, bukan ke VPS. `@vite` menggunakan `APP_URL`
untuk membentuk URL asset CSS/JavaScript, sehingga protocol atau host yang
salah membuat halaman tampil tanpa styling.

`DB_PASSWORD` dan `DB_ROOT_PASSWORD` tidak diberikan oleh Laravel. Nilainya
dibuat oleh operator deployment. Buat password acak yang berbeda untuk kedua
akun tersebut. Jangan gunakan password contoh secara literal.

Karena tahap ini menggunakan HTTP melalui IP, gunakan:

```dotenv
SESSION_SECURE_COOKIE=false
FORCE_HTTPS=false
```

Setelah domain dan HTTPS aktif, ubah `APP_URL` menjadi `https://domain-anda.example`,
`SESSION_SECURE_COOKIE=true`, dan `FORCE_HTTPS=true`.

## 6. Siapkan media dan storage persistent di VPS

Media marketing tidak disimpan di GitHub dan tidak dibundel ke Docker image.
Folder `storage/app/media` berisi hero, logo, portfolio, icon, dan empat PDF
brochure yang direferensikan oleh data layanan. Upload folder ini ke VPS
sebelum build atau start container.

Buat directory persistent di luar checkout Git:

```bash
sudo install -d -o 33 -g 33 -m 775 /var/lib/3dexpert/media
sudo install -d -o 33 -g 33 -m 770 /var/lib/3dexpert/applications
```

Upload isi `storage/app/media` dari komputer deployment. Contoh memakai
`rsync` dari mesin Linux/macOS:

```bash
rsync -av --delete storage/app/media/ luxora_alam_semesta@103.117.57.39:/tmp/3dexpert-media/
ssh luxora_alam_semesta@103.117.57.39 \
  'sudo rsync -a --delete --chown=33:33 /tmp/3dexpert-media/ /var/lib/3dexpert/media/ && rm -rf /tmp/3dexpert-media'
```

Jika komputer deployment hanya memiliki `scp`, gunakan archive sementara:

```bash
tar -czf 3dexpert-media.tar.gz -C storage/app media
scp 3dexpert-media.tar.gz USER_VPS@IP_VPS:/tmp/
ssh USER_VPS@IP_VPS \
  'sudo tar -xzf /tmp/3dexpert-media.tar.gz -C /var/lib/3dexpert && sudo chown -R 33:33 /var/lib/3dexpert/media && rm -f /tmp/3dexpert-media.tar.gz'
```

Jalankan verifikasi di VPS sebelum melanjutkan:

```bash
find /var/lib/3dexpert/media -type f | wc -l
du -sh /var/lib/3dexpert/media
test -f '/var/lib/3dexpert/media/brochures/Arsitektur EKSTERIOR.pdf'
test -f '/var/lib/3dexpert/media/brochures/Arsitektur INTERIOR.pdf'
test -f '/var/lib/3dexpert/media/brochures/Gambar Perijinan.pdf'
test -f '/var/lib/3dexpert/media/brochures/PEMBANGUNAN Lengkap.pdf'
```

Jumlah dan ukuran file harus sesuai dengan source media yang dikirim. Jangan
menjalankan `rsync --delete` terhadap directory yang salah karena opsi tersebut
akan menghapus media production yang tidak ada di source.

## 7. Build dan jalankan Docker Compose

Pastikan Docker Compose membaca `.env`:

```bash
docker compose config
```

Jika valid, build image:

```bash
docker compose build
```

Build memasang dependency Composer, PHP extensions, dependency npm, dan asset
Vite. Pada VPS 2 GB, proses ini dapat membutuhkan waktu cukup lama.

Jalankan semua service:

```bash
docker compose up -d
docker compose ps
```



Service yang harus tersedia:

- `app`
- `worker`
- `nginx`
- `mysql`
- `redis`

Periksa status dan health:

```bash
docker compose ps
docker compose logs --tail=100 mysql
docker compose logs --tail=100 redis
docker compose logs --tail=100 app
docker compose logs --tail=100 worker
```

Compose mempublikasikan Nginx container pada `127.0.0.1:8080`, bukan `80:80`,
karena port 80/443 dipakai host Nginx reverse proxy (bagian 11.4). Selama host
Nginx belum dipasang, aplikasi hanya dapat diuji dari dalam VPS:

```bash
curl -I http://127.0.0.1:8080
```

Akses dari luar melalui IP/domain baru tersedia setelah host Nginx aktif. Jika
masih memakai mapping `80:80` dari deployment lama, jangan menjalankan host
Nginx sebelum container dipindahkan ke `127.0.0.1:8080`.

## 8. Inisialisasi Laravel, database, dan metadata media

Generate atau overwrite application key langsung pada `.env` host VPS:

```bash
cd /var/www/3dexpert-web
test -f .env
command -v openssl
NEW_APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
sed -i "s#^APP_KEY=.*#APP_KEY=${NEW_APP_KEY}#" .env
```

Command tersebut mengganti nilai `APP_KEY` yang sudah ada langsung pada file
host `/var/www/3dexpert-web/.env`. Cara ini dipakai karena `.env` sengaja
memiliki permission `600`, dan Docker bind mount dapat menolak penulisan dari
dalam container meskipun container dijalankan sebagai `root` (misalnya saat
Docker menggunakan rootless mode atau user namespace). Jika `APP_KEY` belum
ada di file, tambahkan satu baris `APP_KEY=` terlebih dahulu sebelum menjalankan
command tersebut.

Verifikasi bahwa key sudah berubah tanpa mencetak nilainya:

```bash
grep -q '^APP_KEY=base64:' .env && echo "APP_KEY tersedia"
```

Setelah key berubah, recreate service yang memakai `.env` agar environment
container mengambil nilai baru:

```bash
docker compose up -d --force-recreate app worker
```

Pastikan file tetap memiliki permission terbatas:

```bash
chmod 600 .env
```

Jalankan migration dan seeding:

```bash
docker compose exec app php artisan migrate --seed --force
```

Seeder utama mencakup data legacy yang sudah dipetakan untuk service,
testimonial, design review, portfolio, dan portfolio images.

Daftarkan metadata media setelah bind mount sudah aktif:

```bash
docker compose exec -u root app php artisan app:import-media-assets
```

Command tersebut membaca file dari `/var/www/html/storage/app/media`, yang
merupakan mount `/var/lib/3dexpert/media` dari host. Command harus melaporkan
file yang di-import atau sudah tersedia. Jika melaporkan `No supported media
files were found`, hentikan deployment dan periksa mount serta isi directory.

Verifikasi mount dan metadata:

```bash
docker compose exec app sh -lc 'find storage/app/media -type f | wc -l'
docker compose exec app php artisan tinker --execute='echo App\Models\MediaAsset::count(), PHP_EOL;'
```

`storage:link` tidak diperlukan untuk media utama. Media menggunakan disk
`media` dan dilayani oleh `MediaController`; membuat symbolic link public tidak
menggantikan file media atau metadata `media_assets`.

Jika tetap diperlukan:

```bash
docker compose exec -u root app php artisan storage:link
```

Buat cache production:

```bash
docker compose exec app php artisan optimize
```

## 9. Buat superadmin pertama

Superadmin dibuat setelah migration berhasil. Gunakan initializer idempotent:

```bash
docker compose exec app php artisan app:init-admin
```

Isi prompt:

- Name
- Email
- Password minimal 12 karakter (tidak ada password default)

Untuk menentukan nama, email, dan username secara langsung:

```bash
docker compose exec app php artisan app:init-admin \
  --name="Administrator 3D Expert" \
  --username="superadmin" \
  --email="admin@domain-anda.example"
```

Command juga membaca `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_USERNAME`, dan
`ADMIN_PASSWORD` dari environment. Menjalankannya lagi aman: akun superadmin
yang sudah ada tidak diubah tanpa `--force`, dan email milik role lain ditolak
tanpa `--force`. Gunakan `--force` hanya untuk memperbaiki/mempromosikan akun
atau mereset password; jika password baru tidak diberikan, password lama tetap
dipakai.

Untuk menambah staf lain dengan role berbeda, gunakan `app:create-admin`:

```bash
docker compose exec app php artisan app:create-admin \
  --name="Editor 3D Expert" \
  --username="editor" \
  --email="editor@domain-anda.example" \
  --role=editor
```

Role yang tersedia:

- `superadmin`: akses penuh panel admin.
- `admin`: akses admin sesuai policy aplikasi.
- `lms_admin`: kurikulum, enrollment, tugas, dan media.
- `editor`: akses terbatas.

Filament menerima **email atau username** pada kolom login. Username hanya
dapat digunakan jika sudah tersimpan pada kolom `users.username`. Untuk akun
baru, gunakan `--username` saat menjalankan `app:init-admin` atau
`app:create-admin`. Untuk
menemukan identifier akun tanpa menampilkan password atau hash password:

```bash
docker compose exec -T app php artisan app:list-admins
```

Jika hasilnya menampilkan username, masukkan username tersebut pada kolom
`Email or username`. Jika username kosong, gunakan email yang ditampilkan.
Kolom email tetap wajib saat membuat akun untuk menjaga kompatibilitas reset
password dan data akun.

Verifikasi login melalui:

```text
http://IP_VPS/admin
```

Jika `/admin` menghasilkan 404 atau halaman login menampilkan error
`filamentSchema is not defined`, lakukan rebuild image Nginx agar aset Filament
dipublish dari package Composer. Jangan hanya menjalankan `docker compose up`
tanpa rebuild karena aset statis dibakar ke image Nginx:

```bash
cd /var/www/3dexpert-web
git pull --ff-only origin main
docker compose build --no-cache nginx app
docker compose up -d --force-recreate app worker nginx
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan route:list --path=admin
docker compose exec -T app php artisan app:list-admins
```

Periksa aset admin dari VPS:

```bash
curl -I http://127.0.0.1/css/filament/filament/app.css
curl -I http://127.0.0.1/js/filament/schemas/schemas.js
```

Keduanya harus menghasilkan `200 OK`. Jika route list tidak menampilkan
`admin/login` dan `admin`, source belum ter-update atau cache route masih lama;
jalankan `php artisan optimize:clear`, lalu recreate container `app` dan
`nginx`.

Jangan membuat password admin hardcoded di seeder atau repository.

## 10. Smoke test melalui IP VPS

Buka dari komputer lokal:

```text
http://IP_VPS
```

Pastikan URL CSS yang dikirim halaman menggunakan `http://IP_VPS/build/...`,
bukan `https://127.0.0.1/build/...` atau `https://IP_VPS/build/...` sebelum
HTTPS aktif.

Periksa:

- Homepage tampil.
- Asset CSS dan JavaScript tidak 404.
- Gambar portfolio tampil.
- Gambar hero dan logo tampil.
- Tombol brochure membuka PDF tanpa status `404`.
- Course dan career tampil.
- Login `/admin` berhasil.
- Registrasi customer berhasil.
- Email verification masuk queue.
- Worker mengambil queue Redis.

### Verifikasi email dan autentikasi customer

Email verifikasi tidak akan masuk inbox jika `MAIL_MAILER=log`, domain Resend
belum berstatus `Verified`, API key salah/tidak punya izin kirim, atau worker
belum memproses queue `mail`.
Saat masih mengakses IP VPS melalui HTTP, pastikan cookie session tidak
ditandai secure:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_API_KEY_DARI_DASHBOARD_RESEND
MAIL_FROM_ADDRESS=info@3dexpert.pro
MAIL_FROM_NAME="3D Expert"
SESSION_SECURE_COOKIE=false
```

Lihat bagian **Resend** di dokumen ini untuk membuat domain dan API key.
Jangan menyalin nilai API key ke repository atau GitHub Actions.

Setelah `.env` diperbarui, jalankan:

```bash
docker compose up -d --force-recreate app worker
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
docker compose logs --tail=100 -f worker
```

Daftarkan customer baru dengan alamat email yang dapat diakses, lalu periksa
log worker. Job yang berhasil diproses akan keluar dari queue. Jika muncul
`401`/`403` dari Resend, periksa `RESEND_API_KEY`. Jika muncul
`domain is not verified`, selesaikan verifikasi DNS di dashboard Resend.
Masalah tersebut bukan masalah pada form verifikasi.

Uji kirim langsung tanpa queue:

```bash
docker compose exec -T app php artisan tinker --execute="Mail::raw('Tes email 3D Expert', fn (\$m) => \$m->to('ALAMAT_TUJUAN@gmail.com')->subject('Tes Resend'));"
```

Periksa status queue dan autentikasi dengan:

```bash
docker compose ps
docker compose logs --tail=100 worker
docker compose logs --tail=100 app
```

Login customer menggunakan email dan password yang dibuat melalui halaman
`/daftar`. Customer yang belum memverifikasi email akan diarahkan ke
`/verifikasi-email`; ini memang perilaku yang disengaja sebelum pendaftaran
event atau fitur akun dapat digunakan. Jika login selalu kembali ke halaman
login saat URL masih `http://IP_VPS`, pastikan `SESSION_SECURE_COOKIE=false`,
lalu jalankan `php artisan optimize:clear` dan restart service `app`.

Periksa media yang dilayani aplikasi:

```bash
curl -I http://IP_VPS/media/images/logo.png
curl -I http://IP_VPS/media/hero/hero_1_1750153978.jpg
curl -I 'http://IP_VPS/media/brochures/Arsitektur%20EKSTERIOR.pdf'
```

Response yang diharapkan adalah `200 OK`. PDF diharapkan memiliki
`Content-Type: application/pdf` dan `Content-Disposition: attachment`.

Periksa juga asset Vite dari dalam VPS sebelum membuka browser:

```bash
curl -I http://127.0.0.1/build/manifest.json
docker compose exec nginx \
  sh -lc 'ls -lh /var/www/html/public/build/manifest.json /var/www/html/public/build/assets/*'
```

Response `manifest.json` harus `200 OK`, dan directory `assets` harus berisi
file CSS serta JavaScript. Image Nginx memiliki copy asset sendiri; perubahan
`resources/css`, `resources/js`, atau `public` baru masuk ke image setelah
Nginx dibuild ulang.

Jika halaman tampil sebagai HTML polos tanpa CSS, ambil URL asset yang
dihasilkan Laravel:

```bash
curl -s http://127.0.0.1/ | grep -Eo 'href="[^"]+\.css[^"]*|src="[^"]+\.js[^"]*'
```

Semua URL tersebut harus memakai IP atau domain production, bukan
`localhost`, dan setiap path harus dapat diakses dengan `curl -I`. Jika file
asset tidak ditemukan atau image Nginx dibangun sebelum commit terbaru,
jalankan:

```bash
docker compose build --no-cache nginx
docker compose up -d --force-recreate nginx
```

Jika `manifest.json` tersedia tetapi URL asset mengarah ke host atau protocol
yang salah, periksa
`APP_URL` pada `/var/www/3dexpert-web/.env`, ubah ke URL yang sedang dibuka,
lalu recreate service Laravel:

```bash
nano /var/www/3dexpert-web/.env
docker compose up -d --force-recreate app worker nginx
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan optimize
```

Periksa queue:

```bash
docker compose exec app php artisan queue:failed
docker compose logs --tail=100 worker
```

Periksa Redis:

```bash
docker compose exec redis redis-cli ping
```

Output yang diharapkan:

```text
PONG
```

Periksa aplikasi melalui command Laravel:

```bash
docker compose exec app php artisan about
```

## 11. Backup awal sebelum traffic production

Buat folder backup dengan permission terbatas:

```bash
sudo mkdir -p /var/backups/3dexpert
sudo chmod 700 /var/backups/3dexpert
```

Contoh backup database:

```bash
docker compose exec -T mysql mysqldump \
  -u3dexpert -p'PASSWORD_USER_MYSQL_YANG_KUAT' \
  3dexpert_laravel | gzip | \
  sudo tee /var/backups/3dexpert/database-$(date +%F).sql.gz > /dev/null
```

Jangan memasukkan password production ke script yang dikomit. Atur backup
terjadwal setelah smoke test selesai.

Backup database tidak mencakup file pada disk media. Buat backup media dan
private application uploads secara terpisah:

```bash
sudo tar -czf /var/backups/3dexpert/media-$(date +%F).tar.gz \
  -C /var/lib/3dexpert media applications
```

## 12. Menambahkan domain setelah deployment via IP berhasil

### 11.1 Arahkan DNS

Pada provider domain, buat record:

```text
Type: A
Name: @
Value: IP_VPS
TTL: 300
```

Jika memakai `www`:

```text
Type: A
Name: www
Value: IP_VPS
TTL: 300
```

Tunggu propagasi dan verifikasi:

```bash
dig +short domain-anda.example
```

### 11.2 Ubah APP_URL

Di VPS:

```bash
cd /var/www/3dexpert-web
nano .env
```

Ubah:

```dotenv
APP_URL=https://domain-anda.example
SESSION_SECURE_COOKIE=true
FORCE_HTTPS=true
SESSION_DOMAIN=.domain-anda.example
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12
```

Recreate app agar environment terbaca:

```bash
docker compose up -d --force-recreate app worker
docker compose exec app php artisan optimize
```

### 11.3 Resend (email production)

Email verifikasi, reset password, dan notifikasi pendaftaran dikirim melalui
Resend. Package yang dipakai adalah `resend/resend-laravel` (sudah ada di
`composer.json`).

#### a. Verifikasi domain di Resend

1. Buka <https://resend.com/domains> lalu **Add Domain**.
2. Isi `3dexpert.pro`, pilih region terdekat, lalu simpan.
3. Resend menampilkan tiga record. Nilainya wajib disalin persis dari dashboard:

   | Type | Name | Value |
   |---|---|---|
   | MX | `send` | `feedback-smtp.<region>.amazonses.com` (priority 10) |
   | TXT | `send` | `v=spf1 include:amazonses.com ~all` |
   | TXT | `resend._domainkey` | `p=...` (DKIM, nilainya panjang) |

4. Tambahkan record tersebut pada DNS zone `3dexpert.pro`.
5. Opsional tetapi disarankan, tambahkan DMARC:

   ```text
   Type: TXT
   Name: _dmarc
   Value: v=DMARC1; p=none; rua=mailto:info@3dexpert.pro
   ```

6. Verifikasi propagasi dari komputer lokal:

   ```powershell
   Resolve-DnsName send.3dexpert.pro -Type MX
   Resolve-DnsName send.3dexpert.pro -Type TXT
   Resolve-DnsName resend._domainkey.3dexpert.pro -Type TXT
   Resolve-DnsName _dmarc.3dexpert.pro -Type TXT
   ```

7. Klik **Verify** di Resend sampai status domain menjadi `Verified`.

Jangan menambah record SPF kedua di `3dexpert.pro`. Kalau nanti domain ini juga
memakai Google Workspace atau provider lain, gabungkan isinya ke satu record
`v=spf1 ...`.

#### b. Buat API key

1. Buka <https://resend.com/api-keys> lalu **Create API Key**.
2. Permission: **Sending access**, batasi ke domain `3dexpert.pro`.
3. Salin key (`re_...`) langsung ke `.env` VPS. Jangan commit, jangan taruh di
   GitHub Actions secrets, dan jangan tempel di dokumen.

#### c. Set environment VPS

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_API_KEY_DARI_DASHBOARD_RESEND
MAIL_FROM_ADDRESS=info@3dexpert.pro
MAIL_FROM_NAME="3D Expert"
```

Baris `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, dan
`MAIL_PASSWORD` tidak dipakai lagi dan boleh dihapus.

#### d. Terapkan dan uji

```bash
cd /var/www/3dexpert-web
docker compose up -d --force-recreate app worker
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
```

Uji kirim langsung:

```bash
docker compose exec -T app php artisan tinker --execute="Mail::raw('Tes email 3D Expert', fn (\$m) => \$m->to('ALAMAT_TUJUAN@gmail.com')->subject('Tes Resend'));"
```

Periksa inbox dan menu **Emails** di dashboard Resend. Setelah itu uji jalur
queue: daftar akun baru di `/daftar`, lalu pastikan email verifikasi masuk dan
`docker compose exec -T app php artisan queue:failed` kosong.

Batasan paket gratis Resend: 100 email per hari, 3.000 per bulan, dan sekitar
2 request per detik. Karena pengiriman lewat queue, batas ini tidak membebani
request HTTP pengguna.

### 11.4 HTTPS dengan host Nginx dan Certbot

Container Nginx hanya mendengarkan HTTP. Host Nginx menjadi reverse proxy pada
port 80/443, menangani sertifikat Let's Encrypt, dan meneruskan request ke
`127.0.0.1:8080`. Compose sudah diubah ke `127.0.0.1:8080:80`; jangan
mengembalikannya ke `80:80`.

Urutan berikut meminimalkan downtime. Jangan menjalankan dua Nginx pada port
yang sama.

#### a. Buka port dan siapkan DNS

```bash
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw status
dig +short 3dexpert.pro
dig +short www.3dexpert.pro
```

Kedua `dig` harus mengembalikan IP VPS sebelum Certbot dijalankan.

#### b. Pindahkan container Nginx ke loopback

```bash
cd /var/www/3dexpert-web
git pull --ff-only origin main
docker compose stop nginx
docker compose up -d nginx
curl -I http://127.0.0.1:8080
```

`curl` harus menghasilkan `200 OK`. Situs melalui IP/domain akan berhenti
sementara sampai langkah berikut selesai.

#### c. Pasang host Nginx dan Certbot

```bash
sudo apt update
sudo apt install -y nginx certbot python3-certbot-nginx
sudo systemctl status nginx --no-pager
```

Jika instalasi gagal karena port 80 masih dipakai, pastikan langkah b sudah
selesai dan `ss -ltnp | grep ':80 '` tidak menunjuk ke proses Docker.

#### d. Konfigurasi reverse proxy

```bash
sudo tee /etc/nginx/sites-available/3dexpert.pro > /dev/null <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name 3dexpert.pro www.3dexpert.pro;

    # Upload lamaran: 5 berkas x 5 MB ditambah overhead form.
    client_max_body_size 30m;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_read_timeout 90s;
        proxy_send_timeout 90s;
    }
}
NGINX

sudo ln -sf /etc/nginx/sites-available/3dexpert.pro /etc/nginx/sites-enabled/3dexpert.pro
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t
sudo systemctl reload nginx
curl -I http://3dexpert.pro
```

#### e. Terbitkan sertifikat

```bash
sudo certbot --nginx -d 3dexpert.pro -d www.3dexpert.pro --redirect
sudo certbot renew --dry-run
sudo systemctl list-timers | grep certbot
```

Certbot menambahkan blok `listen 443 ssl` dan redirect HTTP ke HTTPS. Pastikan
perintah `renew --dry-run` berhasil agar perpanjangan otomatis tidak gagal 60
hari kemudian.

#### f. Ubah environment ke HTTPS

```bash
cd /var/www/3dexpert-web
nano .env
```

```dotenv
APP_URL=https://3dexpert.pro
FORCE_HTTPS=true
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.3dexpert.pro
TRUSTED_PROXIES=127.0.0.1,172.16.0.0/12
LOG_CHANNEL=stderr
LOG_STACK=stderr
```

`TRUSTED_PROXIES` wajib berisi alamat proxy. Host Nginx meneruskan request ke
container melalui gateway Docker, sehingga CIDR `172.16.0.0/12` ikut di-trust.
Jangan memakai `*`: tanpa proxy tepercaya, `X-Forwarded-For` bisa dipalsukan dan
rate limiter dapat dilewati. `LOG_CHANNEL=stderr` membuat log muncul di
`docker compose logs` dan tidak hilang saat container dibuat ulang.

```bash
docker compose up -d --force-recreate app worker
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
```

#### g. Verifikasi HTTPS

```bash
curl -I https://3dexpert.pro
curl -I http://3dexpert.pro
curl -I https://www.3dexpert.pro
curl -sI https://3dexpert.pro | grep -i strict-transport-security
dig +short 3dexpert.pro
```

Yang diharapkan:

- `https://3dexpert.pro` → `200 OK`.
- `http://3dexpert.pro` → `301`/`308` ke HTTPS.
- `https://www.3dexpert.pro` → halaman yang sama atau redirect ke apex.
- Header `Strict-Transport-Security` muncul.

Buka `/admin`, login, lalu ulangi smoke test pada bagian 10 memakai domain.
Setelah HTTPS aktif, perbarui **Payment Notification URL** Midtrans menjadi:

```text
https://3dexpert.pro/pembayaran/notifikasi
```

Jika login berhasil tetapi langsung kembali ke halaman login, periksa
`SESSION_SECURE_COOKIE=true`, `APP_URL=https://3dexpert.pro`, dan
`TRUSTED_PROXIES`. Jalankan `php artisan optimize:clear` setelah mengubahnya.

### 11.5 Scheduler pembayaran

Order yang ditinggalkan harus kedaluwarsa otomatis. Tanpa cron, kursi acara
tetap tertahan melewati `PAYMENT_EXPIRY_MINUTES`.

```bash
crontab -e
```

Tambahkan satu baris (user harus anggota group `docker`):

```cron
* * * * * cd /var/www/3dexpert-web && docker compose exec -T app php artisan schedule:run >> /var/log/3dexpert-schedule.log 2>&1
```

Verifikasi:

```bash
docker compose exec -T app php artisan schedule:list
docker compose exec -T app php artisan payments:expire
tail -n 20 /var/log/3dexpert-schedule.log
```

---

# Tahap 2 - GitHub Actions untuk deployment otomatis

Tahap ini dilakukan **setelah Tahap 1 berhasil** dan aplikasi dapat diakses
melalui IP VPS.

## 13. Model deployment GitHub Actions

Alur yang digunakan:

```text
Push ke main
    |
    v
GitHub Actions menjalankan test dan build frontend
    |
    v
GitHub Actions SSH ke VPS
    |
    v
VPS git pull + docker compose build
    |
    v
VPS recreate container dan menjalankan migration
```

`.env` tetap berada di VPS. GitHub Actions tidak mengirim file `.env` dari
repository.

## 14. Buat SSH deploy key khusus

Di komputer lokal, buat key khusus deployment:

```powershell
ssh-keygen -t ed25519 -C "github-actions-3dexpert" -f "$HOME\.ssh\3dexpert_actions"
```

Jangan beri passphrase kosong jika kebijakan organisasi mewajibkan key
berpassphrase. Simpan private key dengan permission aman.

Tambahkan public key ke user deployment VPS:

```bash
mkdir -p ~/.ssh
chmod 700 ~/.ssh
nano ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Tempel isi file `3dexpert_actions.pub` sebagai satu baris.

Uji dari komputer lokal:

```powershell
ssh -i "$HOME\.ssh\3dexpert_actions" username@IP_VPS
```

Private key tidak boleh di-commit atau ditempel di file workflow.

## 15. Tambahkan GitHub Actions secrets

Di GitHub buka:

```text
Repository -> Settings -> Secrets and variables -> Actions
```

Tambahkan repository secrets:

```text
VPS_HOST       = IP VPS atau hostname
VPS_PORT       = 22
VPS_USER       = user deployment
VPS_SSH_KEY    = isi private key 3dexpert_actions
VPS_APP_PATH   = /var/www/3dexpert-web
```

Jangan tambahkan:

- `APP_KEY`
- `DB_PASSWORD`
- `DB_ROOT_PASSWORD`
- `MAIL_PASSWORD`

Secret aplikasi tetap dikelola di `.env` VPS.

## 16. Tambahkan job deployment setelah CI

File `.github/workflows/ci.yml` saat ini melakukan CI. Job deployment harus
ditambahkan setelah CI berhasil, atau dibuat sebagai workflow terpisah dengan
approval environment production.

Command yang dijalankan pada VPS oleh job deployment:

```bash
cd /var/www/3dexpert-web
git pull --ff-only origin main
docker compose build
docker compose up -d --force-recreate
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan app:import-media-assets
docker compose exec -T app php artisan optimize
```

Job deployment tidak meng-upload media dari GitHub. Pastikan proses deployment
memiliki langkah upload/sinkronisasi media ke `/var/lib/3dexpert/media` sebelum
`docker compose up -d --force-recreate`. Jangan memakai `rsync --delete` tanpa
source media yang lengkap.

Gunakan `docker compose exec -T` di CI agar command tidak menunggu TTY.

Sebelum mengaktifkan otomatisasi production:

- Batasi workflow deployment hanya pada branch `main`.
- Gunakan GitHub Environment `production`.
- Aktifkan required reviewer untuk environment jika tersedia.
- Pastikan CI selesai sebelum deployment.
- Simpan log deployment dan rollback command.

## 17. Rollback deployment

Catat commit sebelum update:

```bash
cd /var/www/3dexpert-web
git rev-parse HEAD
```

Jika release baru bermasalah:

```bash
git log --oneline -5
git checkout COMMIT_SEBELUMNYA
docker compose build
docker compose up -d --force-recreate
docker compose exec -T app php artisan optimize
```

Jangan menjalankan `docker compose down -v` saat rollback. Named volume MySQL
dan Redis harus dipertahankan.

Setelah rollback, periksa:

```bash
docker compose ps
docker compose logs --tail=100 app
docker compose logs --tail=100 worker
```

## 18. Checklist selesai

### Tahap 1

- [ ] GitHub repository dapat diakses dari VPS.
- [ ] Docker Engine dan Compose aktif.
- [ ] `.env` production dibuat hanya di VPS.
- [ ] `/var/lib/3dexpert/media` dibuat dan diisi dari source media resmi.
- [ ] Empat PDF brochure, hero, logo, icon, dan portfolio diverifikasi.
- [ ] `docker compose config` berhasil.
- [ ] Image app, worker, dan Nginx berhasil dibuild.
- [ ] MySQL dan Redis healthy.
- [ ] Migration dan legacy seeder berhasil.
- [ ] Media asset metadata berhasil diimport.
- [ ] Mount media terlihat dari container `app` dan `worker`.
- [ ] Admin `superadmin` berhasil dibuat.
- [ ] Login admin diuji dengan email atau username yang terdaftar.
- [ ] Homepage dan `/admin` dapat diakses melalui IP.
- [ ] Hero, logo, dan link brochure menghasilkan `200 OK`.
- [ ] Queue worker berjalan.
- [ ] Backup database pertama dibuat.
- [ ] Backup media dan private application uploads pertama dibuat.

### Tahap domain

- [ ] DNS A record mengarah ke IP VPS.
- [ ] `APP_URL` diubah ke domain.
- [ ] HTTPS aktif.
- [ ] `SESSION_SECURE_COOKIE=true`.
- [ ] `TRUSTED_PROXIES` berisi proxy host, bukan `*`.
- [ ] Nginx tidak mengalami konflik port (container di `127.0.0.1:8080`).
- [ ] Smoke test diulang melalui domain.
- [ ] `certbot renew --dry-run` berhasil.

### Tahap email (Resend)

- [ ] Domain `3dexpert.pro` berstatus `Verified` di Resend.
- [ ] Record MX/TXT `send` dan TXT `resend._domainkey` aktif.
- [ ] `RESEND_API_KEY` hanya ada di `.env` VPS.
- [ ] Email verifikasi customer diterima di inbox.
- [ ] `queue:failed` kosong setelah pengiriman.
- [ ] Email reset password diterima.

### Tahap 2

- [ ] Deploy key khusus dibuat.
- [ ] SSH key diuji.
- [ ] GitHub Actions secrets dibuat.
- [ ] CI berhasil pada branch `main`.
- [ ] Production environment dan approval dikonfigurasi.
- [ ] Deployment workflow diuji.
- [ ] Rollback commit diuji atau didokumentasikan.
