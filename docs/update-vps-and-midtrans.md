# Update kode di VPS dan perbaikan Midtrans

Panduan ini dipakai setelah aplikasi sudah berjalan di VPS dengan Docker Compose.
Untuk instalasi pertama, ikuti [step-deploy-vps.md](../step-deploy-vps.md).

- Domain produksi: `https://3dexpert.pro`
- Lokasi kode di VPS: `/var/www/3dexpert-web`
- Service: `app`, `worker`, `nginx`, `mysql`, `redis`
- `.env` hanya berada di VPS dan tidak pernah di-commit

## 1. Sebelum update

Jalankan dari komputer lokal:

```bash
cd "D:/download/web-3dexpert/3D Expert Web/3dexpert-web"
git status
php artisan test
npm run build
```

Pastikan tidak ada perubahan yang belum di-commit. Setelah itu commit dan push:

```bash
git add app config database docs lang resources routes tests
git commit -m "fix: ..."
git push origin main
```

Jangan menambahkan `.env`, `admin.md`, atau file credential lain ke Git.

## 2. Masuk ke VPS dan cek kondisi

```bash
ssh USER_VPS@IP_VPS
cd /var/www/3dexpert-web
git status --short
git rev-parse HEAD
docker compose ps
```

`git status` harus bersih. Jika ada perubahan lokal di VPS, jangan langsung
`reset` atau `pull`; cek dulu apakah perubahan itu disengaja. File yang biasanya
berubah karena runtime, misalnya `storage/` atau `bootstrap/cache/`, tidak boleh
masuk ke Git.

Simpan commit lama untuk rollback:

```bash
git rev-parse HEAD
```

## 3. Backup database

Lakukan sebelum migration. Perintah ini membaca password dari environment
container, jadi password tidak ditulis di shell history:

```bash
sudo mkdir -p /var/backups/3dexpert
docker compose exec -T mysql sh -c \
  'exec mysqldump --single-transaction --quick -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip | sudo tee "/var/backups/3dexpert/db-$(date +%F-%H%M).sql.gz" > /dev/null
ls -lh /var/backups/3dexpert
```

Backup media ada di `/var/lib/3dexpert/media` dan tidak tercakup oleh backup
database.

## 4. Fetch dan pull dari GitHub

Selalu `fetch` dulu, lalu lihat commit yang akan masuk sebelum `pull`:

```bash
cd /var/www/3dexpert-web
git fetch origin main
git log --oneline --decorate HEAD..origin/main
git pull --ff-only origin main
git rev-parse HEAD
```

`--ff-only` sengaja dipakai agar VPS tidak membuat merge commit dan tidak
menimpa perubahan lokal. Jika `pull` ditolak, hentikan proses dan periksa
`git status`.

## 5. Build dan recreate container

Perubahan CSS/JS masuk ke image Nginx, jadi Nginx harus ikut di-build ulang.
Jangan hanya menjalankan `docker compose up` tanpa build.

```bash
cd /var/www/3dexpert-web
docker compose build app worker nginx
docker compose up -d --force-recreate app worker nginx
docker compose ps
```

Jika hanya mengubah backend dan tidak menyentuh asset, `app` dan `worker` saja
bisa cukup. Namun untuk perubahan tampilan, selalu build `nginx` juga.

## 6. Migration dan cache Laravel

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
```

`optimize` menjalankan cache konfigurasi, route, view, dan event. Setelah
mengubah `.env`, `app` dan `worker` harus di-recreate lebih dulu agar env baru
masuk ke container.

Jika ada perubahan media:

```bash
docker compose exec -T app php artisan app:import-media-assets
```

## 7. Smoke test setelah update

Dari VPS:

```bash
curl -I http://127.0.0.1:8080
curl -I https://3dexpert.pro
curl -I https://3dexpert.pro/build/manifest.json
docker compose logs --tail=100 app
docker compose logs --tail=100 worker
docker compose logs --tail=100 nginx
```

Dari browser, cek minimal:

- homepage dan `/acara`
- `/admin` bisa login
- dialog pendaftaran acara terbuka dan tombol submit ada
- checkout kursus menampilkan tombol `Lanjutkan`
- footer punya link `Kegiatan`
- halaman pembayaran menampilkan QR dan countdown 15 menit
- setelah pembayaran sandbox, status order berubah di dashboard Midtrans dan
  pendaftaran/enrollment menjadi aktif

## 8. Rollback jika update bermasalah

Jangan pernah menjalankan `docker compose down -v`; named volume MySQL dan
Redis harus dipertahankan.

```bash
cd /var/www/3dexpert-web
git reset --hard COMMIT_SEBELUMNYA
docker compose build app worker nginx
docker compose up -d --force-recreate app worker nginx
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose ps
```

Setelah masalah selesai, kembali ke versi terbaru:

```bash
git fetch origin main
git reset --hard origin/main
docker compose build app worker nginx
docker compose up -d --force-recreate app worker nginx
```

`git reset --hard` hanya untuk checkout deployment di VPS yang sudah bersih,
bukan untuk repository pengembangan lokal.

## 9. Memperbaiki Midtrans QRIS

### 9.1 Pastikan mode dan key cocok

Buka `.env` di VPS:

```bash
cd /var/www/3dexpert-web
sudo nano .env
```

Nilai yang benar untuk production:

```dotenv
APP_URL=https://3dexpert.pro
MIDTRANS_IS_PRODUCTION=true
MIDTRANS_SERVER_KEY=Mid-server-...
MIDTRANS_CLIENT_KEY=Mid-client-...
MIDTRANS_MERCHANT_ID=...
PAYMENT_EXPIRY_MINUTES=15
```

Untuk sandbox:

```dotenv
MIDTRANS_IS_PRODUCTION=false
MIDTRANS_SERVER_KEY=SB-Mid-server-...
MIDTRANS_CLIENT_KEY=SB-Mid-client-...
```

Aturan penting:

- `MIDTRANS_CLIENT_KEY` bukan server key. Jangan menyalin server key ke client key.
- Server key: `Mid-server-...` (production) atau `SB-Mid-server-...` (sandbox).
- Client key: `Mid-client-...` (production) atau `SB-Mid-client-...` (sandbox).
- `MIDTRANS_IS_PRODUCTION` harus cocok dengan jenis key.
- Jangan menguji QR sandbox dengan aplikasi mobile banking. QR sandbox hanya
  untuk <https://simulator.sandbox.midtrans.com/qris/index>.

Cek jumlah baris Midtrans agar tidak ada duplikat:

```bash
for key in MIDTRANS_SERVER_KEY MIDTRANS_CLIENT_KEY MIDTRANS_MERCHANT_ID MIDTRANS_IS_PRODUCTION; do
  printf '%s: ' "$key"
  grep -c "^${key}=" .env
done
```

Setiap key harus menghasilkan `1`.

Cek jenis key tanpa menampilkan nilainya:

```bash
awk -F= '
  /^MIDTRANS_SERVER_KEY=/ {print "server_key=" (index($2,"SB-Mid-server-")==1 ? "sandbox" : index($2,"Mid-server-")==1 ? "production" : "UNKNOWN")}
  /^MIDTRANS_CLIENT_KEY=/ {print "client_key=" (index($2,"SB-Mid-client-")==1 ? "sandbox" : index($2,"Mid-client-")==1 ? "production" : "UNKNOWN")}
  /^MIDTRANS_IS_PRODUCTION=/ {print "is_production=" $2}
' .env
```

Contoh hasil production:

```text
server_key=production
client_key=production
is_production=true
```

Jika muncul `UNKNOWN`, berarti nilai key salah format atau masih kosong.

### 9.2 Terapkan perubahan

```bash
cd /var/www/3dexpert-web
docker compose up -d --force-recreate app worker
docker compose exec -T app php artisan optimize:clear
docker compose exec -T app php artisan optimize
docker compose exec -T app php artisan queue:restart
```

Pastikan **Payment Notification URL** di dashboard Midtrans production adalah:

```text
https://3dexpert.pro/pembayaran/notifikasi
```

Gunakan URL sandbox yang sesuai jika memakai mode sandbox. Endpoint ini tidak
memakai prefix locale dan tidak memakai CSRF karena dipanggil server-to-server.

### 9.3 Kalau QRIS masih gagal

Cek log aplikasi:

```bash
docker compose logs --tail=200 app
docker compose logs --tail=200 worker
```

Cari `Midtrans QRIS charge failed`, `order_id`, dan HTTP status dari Midtrans.
Jangan menyalin server key ke tiket, chat, atau issue GitHub.

Jika aplikasi bank menampilkan kode seperti `THIMP`:

- `THIMP` bukan status Midtrans dan tidak ada di kode aplikasi. Itu biasanya kode
  penolakan internal aplikasi bank/acquirer.
- Pastikan QR yang dipindai berasal dari production, bukan QR sandbox.
- Cek status order di dashboard Midtrans. Jika masih `pending`, Midtrans sudah
  menerima charge dan penolakan terjadi di sisi bank.
- Kumpulkan nama bank/aplikasi, `order_id`, waktu percobaan, nominal, dan
  screenshot.
- Cocokkan dengan log charge dan notifikasi Midtrans, lalu hubungi support
  Midtrans atau bank dengan data tersebut.

Jangan menebak penyebab hanya dari kode `THIMP`.

## 10. Checklist update

- [ ] `git status` bersih di VPS sebelum pull
- [ ] commit lama sudah dicatat
- [ ] backup database dibuat
- [ ] `git fetch origin main` dan `git pull --ff-only origin main` berhasil
- [ ] image `app`, `worker`, dan `nginx` sudah di-build
- [ ] migration berhasil
- [ ] `php artisan optimize` berhasil
- [ ] queue worker berjalan
- [ ] homepage, `/admin`, `/acara`, checkout, dan halaman pembayaran lolos smoke test
- [ ] konfigurasi Midtrans memakai key dan mode yang sama
- [ ] notifikasi Midtrans diterima dan status order berubah
