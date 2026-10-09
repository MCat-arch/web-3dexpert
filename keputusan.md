# Keputusan & Konflik — Catatan Keputusan Mandiri

Catatan setiap konflik, ambiguitas, atau keputusan yang diambil **tanpa menunggu
input user**, beserta alasannya. Dibuat atas permintaan user (2026-10-08) ketika
memberikan izin penuh untuk melanjutkan implementasi secara mandiri.

Format tiap entri: apa masalahnya → pilihan yang ada → keputusan → alasan →
apa yang perlu user tinjau.

Keputusan arsitektur yang sudah disetujui user ada di `notes.md` (D1–D20).
File ini khusus untuk hal-hal yang **saya putuskan sendiri**.

---

## K1 — O4 tidak ada dalam urutan yang user berikan

**Masalah.** User memberi urutan: `P5a → O1 → O2 → O3 → O5 → O6 → O7`.
**O4 (Query & Data Access) tidak disebut.** Bisa jadi salah tulis, bisa jadi
memang sengaja dilewati.

**Pilihan.**
1. Lewati O4 sesuai urutan literal.
2. Kerjakan O4 pada posisi aslinya (setelah O3).
3. Tanya user — tidak boleh, user minta tidak bertanya.

**Keputusan: kerjakan O4 setelah O3, sesuai rencana semula.**

**Alasan.** O4 berisi dua cacat yang sudah terverifikasi dan keduanya ada di
jalur launch:

- `HomeController` mengambil **semua** portfolio published padahal hanya 16 yang
  dirender. Pada 500 pengguna serentak ini terjadi 500 kali.
- `x-portfolio-card` fallback ke relasi `images` yang tidak di-eager-load.
  Karena `preventLazyLoading()` aktif di luar production, ini **melempar
  exception**, bukan sekadar query tambahan.

Homepage adalah langkah pertama dari profil launch (homepage → register →
verify → browse → enroll). Melewati O4 berarti launch dengan query tak
terbatas pada halaman paling ramai. Biaya O4 kecil (S–M) dan sudah
terdokumentasi lengkap.

**Yang perlu user tinjau.** Jika O4 memang sengaja dilewati, bilang saja —
pekerjaannya bisa dibatalkan tanpa memengaruhi fase lain.

---

## K2 — Risky test: output buffer tidak tertutup pada render homepage

**Masalah.** Setelah konversi suite ke PHPUnit: **55 lulus, 0 gagal, 8 risky**.
Semua 8 risky adalah test yang merender homepage, dengan pesan
*"Test code or tested code did not close its own output buffers."*

**Yang sudah diverifikasi.**

| Pemeriksaan | Hasil |
|---|---|
| `ob_get_level()` sebelum/sesudah `GET /` di test | `1 → 2` (satu buffer bocor) |
| `ob_get_level()` untuk `GET /portofolio` di test | `1 → 1` (bersih) |
| `ob_*` di `app/`, `resources/`, `bootstrap/` | Tidak ada (hanya satu komentar) |
| `@section` / `@endsection` di `home.blade.php` | Seimbang (21 → 485) |
| Tag komponen `<x-...>` di `home.blade.php` | Seimbang (4 self-closing + 1 berpasangan) |
| `@push` / `@once` / `x-slot` di `home.blade.php` | Tidak ada |
| Render yang sama lewat `tinker` | Buffer seimbang (`1 → 1`) |

Jadi: terlokalisasi pada jalur render `home.blade.php` di environment test, tapi
**akar masalahnya belum ditemukan**. Tersangka yang tersisa: paket dev yang
menyuntik HTML (`laravel/boost`), atau interaksi `@stack('scripts')`.

**Keputusan: catat sebagai isu diketahui, lanjut ke pekerjaan P0 berikutnya.**

**Alasan.**
- Ini **warning**, bukan kegagalan. Tidak ada satu pun assertion yang terpengaruh.
- Tidak berdampak di production: production tidak memeriksa keseimbangan buffer,
  dan render homepage lewat `tinker` bersih.
- Kriteria P0 ("suite berjalan dan hasilnya bisa dipercaya") sudah terpenuhi:
  63 test berjalan, 0 gagal.
- Biaya pencarian lanjutan sudah melebihi nilainya; empat hipotesis paling
  mungkin sudah dieliminasi.

**Yang perlu user tinjau.** Jika nanti muncul gejala nyata (output bocor ke
response, header terkirim duplikat), mulai dari tabel di atas — pekerjaan
eliminasi sudah selesai. Kandidat berikutnya: nonaktifkan `laravel/boost`
sementara dan ukur ulang.

---

## K3 — Ekstensi PHP yang mati: `intl`, `zip`, `pdo_sqlite`, `sqlite3`, `curl`, `gd`, `exif`

**Masalah.** `composer require` gagal karena `ext-intl` lalu `ext-zip` tidak
aktif. Setelah itu `php artisan test` gagal total: `could not find driver`
karena `pdo_sqlite` mati — padahal `phpunit.xml` memakai SQLite in-memory.

Artinya **`composer install` akan gagal untuk siapa pun yang menyiapkan proyek
ini**, dan suite test tidak mungkin berjalan. Ini bukan masalah proyek,
melainkan instalasi PHP 8.4 (WinGet) yang hampir semua ekstensinya dikomentari.

**Keputusan: aktifkan tujuh ekstensi di `php.ini`, dengan backup lebih dulu.**

Backup: `php.ini.bak-before-intl` di folder instalasi PHP.

| Ekstensi | Dibutuhkan untuk |
|---|---|
| `intl` | Dependensi transitif Composer; format angka/tanggal Laravel |
| `zip` | `openspout/openspout` — mesin export Filament |
| `pdo_sqlite`, `sqlite3` | Suite test (`phpunit.xml` → SQLite `:memory:`) |
| `curl` | HTTP client — dibutuhkan P2 untuk Midtrans |
| `gd`, `exif` | Pemrosesan gambar untuk upload Filament |

**Alasan.** Semua DLL sudah ada di folder `ext/`, hanya dikomentari. Mengaktifkan
ekstensi bawaan itu reversibel, berisiko rendah, dan tanpa itu P0 tidak bisa
selesai. `zip`, `gd`, dan `exif` bukan untuk test — tanpa `zip` fitur export
Filament gagal saat runtime, jadi sekalian diaktifkan.

**Yang perlu user tinjau.** Ini perubahan di **instalasi PHP**, bukan di proyek.
Server production butuh ekstensi yang sama; masukkan ke catatan deploy.

---

## K4 — `.env` memakai locale `en`, bertentangan dengan seluruh proyek

**Masalah.** `.env` berisi `APP_LOCALE=en` dan `APP_FALLBACK_LOCALE=en`,
sedangkan `.env.example` benar: `id` untuk keduanya. Dua test slug gagal karena
slug dibuat dari judul bahasa Inggris.

Dampaknya bukan cuma test:
- `Portfolio::generateSlug()` memakai `app.fallback_locale` → slug berbahasa Inggris
- `HasTranslations` fallback ke `en` → konten Indonesia yang kosong tampil Inggris
- `config/site.php` sudah `default_locale => 'id'`, jadi ini saling bertabrakan

**Keputusan: ubah `.env` menjadi `APP_LOCALE=id` dan `APP_FALLBACK_LOCALE=id`.**

**Alasan.** Sesuai `.env.example`, sesuai `config/site.php`, dan sesuai instruksi
user: *"focus on bahasa indonesia route."* Setelah diubah, 27 unit test lulus.

**Yang perlu user tinjau.** `.env` tidak masuk git, jadi perbaikan ini **harus
diterapkan manual di setiap environment lain** (staging, production).
`.env.example` sudah benar.

---

## K5 — Redis: client dipasang, server belum ada

**Masalah.** P0 Langkah 2 meminta cache/session/queue pindah ke Redis. Tapi:
ekstensi `phpredis` tidak ada, **dan tidak ada server Redis sama sekali** —
tidak ada binary, tidak ada yang listen di port 6379.

**Keputusan: pasang `predis/predis` (client), biarkan driver tetap `database`.**

**Alasan.**
- User menyetujui pemasangan dependensi, dan `predis` adalah pilihan tepat di
  sini: PHP murni, tidak perlu ekstensi — penting karena PHP ini ZTS/Windows,
  yang membuat kompilasi `phpredis` menyakitkan.
- Mengubah driver ke `redis` tanpa server akan membuat **seluruh aplikasi mati**.
- Opsi 3 di rencana P0 memang mengantisipasi ini: tetap `database` secara lokal,
  Redis untuk production, dan catat perbedaannya.

**Yang perlu user tinjau.** **Ini memblokir O1.** Baseline load test di atas
driver `database` mengukur mesin yang salah, dan setiap keputusan O2–O7
mewarisinya. Sebelum O1: pasang server Redis (Memurai di Windows, atau Redis di
WSL), atau tentukan hosting (A18) yang menyediakan managed Redis. Begitu server
ada, perubahannya hanya tiga baris di `.env`.

---

## K6 — `landingDestination()` menghapus nilai SEO redirect portfolio

**Masalah.** Saat T0, ditemukan `HandleLegacyRedirects::landingDestination()`
menulis ulang semua path `/portofolio/*` menjadi `/#portofolio`. Padahal
migrator sudah memetakan setiap `detail.php?id=N` ke slug-nya sendiri.
Akibatnya empat URL proyek berbeda digabung jadi satu anchor.

Satu test (`LegacyRedirectTest`) **meng-assert perilaku salah ini**, jadi test
tersebut mengunci bug-nya.

**Keputusan: hapus cabang portfolio dari `landingDestination()`, dan perbaiki
test-nya.**

**Alasan.** Penulisan ulang itu dibuat ketika route portfolio tidak ada —
supaya 301 tidak mengarah ke 404, yang memang benar saat itu. Sekarang route-nya
ada, jadi alasannya hilang. Cabang `/layanan` dan `/kontak` tetap dipertahankan
karena halaman itu benar-benar tidak ada (D12 untuk kontak, P6 untuk karir).

**Yang perlu user tinjau.** Tidak ada. Sudah terverifikasi:
`detail.php?id=1` → 301 → slug asli → 200.

---

## K7 — P1 tidak ada dalam urutan, padahal P5a tidak bisa jalan tanpanya

**Masalah.** User memberi urutan `P5a → O1 → O2 → O3 → O5 → O6 → O7`, lalu
berkata "now do p5a". **P1 (Customer Identity) tidak disebut.**

Tapi P5a tidak mungkin dibangun tanpa P1:

- Brief user: *"event (the user will have to create account and sign up for
  enroll to event)"* — enrollment butuh akun.
- `event_registrations.customer_id` mengacu ke tabel `customers` yang dibuat P1.
- Kriteria penerimaan P5a: *"A verified customer enrolls in a free event"* —
  butuh guard `customer` dan verifikasi email.
- Profil launch: register → verify → browse → enroll. Dua langkah pertama **adalah** P1.

Saat ini tidak ada tabel `customers`, tidak ada guard `customer`, tidak ada
scaffolding auth publik sama sekali.

**Pilihan.**
1. Kerjakan P5a tanpa auth — enrollment anonim. Bertentangan dengan brief.
2. Kerjakan P1 dulu, lalu P5a. Sesuai urutan Wave 1 yang sudah disepakati.
3. Tanya user — tidak boleh.

**Keputusan: kerjakan P1 lebih dulu, lalu lanjut P5a.**

**Alasan.** Sama seperti K1 (O4), ini kelihatannya kelalaian penulisan, bukan
keputusan. Urutan Wave 1 yang disepakati di `docs/plans/README.md` adalah
`T0 → P0 → P1 → P5a`, dan P1 tercantum eksplisit sebagai dependensi di header
rencana P5a. Membangun enrollment anonim lalu membongkarnya untuk menambahkan
akun jauh lebih mahal daripada mengerjakan P1 sekarang — dan P1 juga menutup
jalur eskalasi `/admin` (D1), yang harus beres **sebelum** ada pendaftaran
publik, bukan sesudahnya.

**Yang perlu user tinjau.** Jika yang dimaksud memang enrollment tanpa akun
(mis. hanya nama + nomor WhatsApp), bilang saja — itu fitur yang jauh lebih
kecil, tapi bertentangan dengan brief awal dan dengan D1.

---

## K8 — Perilaku acara berbayar selama P5a

**Masalah.** P5a hanya mengirim jalur pendaftaran gratis; P5b belum menyediakan
checkout. Sebelum keputusan ini, `EnrollInEvent` masih bisa membuat pendaftaran
`pending` tanpa pembayaran.

**Pilihan.** Tetap tampilkan acara berbayar dengan tombol nonaktif, sembunyikan
dari katalog, atau izinkan pendaftaran tanpa checkout.

**Keputusan: tampilkan acara berbayar, tetapi nonaktifkan pendaftaran sampai
P5b.** Pendaftaran server-side juga harus ditolak dan tidak boleh membuat row
atau mengambil kursi.

**Sumber.** Dikonfirmasi user pada sesi perencanaan 2026-10-08.

## K9 — Batas cache katalog acara P5a dan O5

**Masalah.** Instruksi P5 meminta cache katalog dengan invalidasi, sementara
urutan wave menempatkan strategi cache penuh di O5 setelah P5a.

**Keputusan: P5a menambahkan cache aman beserta invalidasinya; O5 tetap
bertanggung jawab atas pengukuran dan hardening beban/stampede.** Cache P5a tidak
boleh menyimpan kuota atau `seats_taken`; ketersediaan dibaca langsung.

**Sumber.** Dikonfirmasi user pada sesi perencanaan 2026-10-08.

## K10 — Audit dan alert rekonsiliasi kursi sebelum P8

**Masalah.** P5 mewajibkan rekonsiliasi kursi mencatat nilai sebelum/sesudah dan
memberi alert. Belum ada audit-log atau sistem alert staf di kode; P8-lah yang
merencanakan `audit_logs` dan `App\Support\Audit`. Membuat tabel audit kedua
sekarang berisiko menduplikasi desain P8, sedangkan log aplikasi saja tidak
menjamin notifikasi staf.

**Pilihan yang dipertimbangkan:**
1. P5a memakai structured `Log::alert` dengan `event_id`, nilai aktual, dan nilai
   hasil hitung; P8 kelak menambahkan audit trail durable dan alert staf.
2. Tarik generic `audit_logs` + helper `Audit` ke P5a dan revisi P8 agar
   menggunakannya; tentukan juga kanal alert staf.

**Keputusan: P5a memakai `Log::alert` terstruktur sekarang** dengan `event_id`,
counter tersimpan, jumlah pendaftaran yang dihitung, dan counter hasil koreksi.
P8 tetap menambahkan audit trail durable serta notifikasi staf; P5a tidak
menciptakan tabel audit sementara atau menebak alamat penerima.

**Sumber.** Dikonfirmasi user pada sesi perencanaan 2026-10-08.

## K11 — Rollback migrasi SQLite terhalang migrasi role lama

**Temuan.** Migrasi maju seluruhnya berhasil pada SQLite sementara. Saat
`migrate:rollback`, migrasi lama `2026_01_01_000011_add_role_to_users_table`
gagal ketika menghapus kolom `role`, karena indeks `users_role_index` masih ada.
Kegagalan ini mendahului P5a dan bukan berasal dari dua migrasi event.

**Keputusan untuk P5a:** jangan ubah migrasi role yang tidak terkait sebagai
bagian dari P5a. Catat sebagai pekerjaan P9: perbaiki urutan teardown indeks /
kolom dengan aman dan ulangi rollback/reapply seluruh migrasi pada database
terisolasi. Jangan mengklaim rollback semua migrasi telah lolos sampai itu
berhasil. Validasi rollback terhadap database produksi yang didukung juga perlu
dicatat terpisah dari kompatibilitas SQLite.

**Status.** Terbuka untuk P9; P5a forward-migration berhasil, validasi rollback
berhenti pada migrasi role lama.

## K12 — Batas tinggi untuk pendaftaran dari IP bersama

**Temuan.** Regresi D17 mensyaratkan 50 pendaftaran sah dari satu alamat IP tanpa
HTTP 429. Batas lama 30/menit memblokir pendaftaran ke-31, walaupun setiap orang
menggunakan email berbeda.

**Keputusan implementasi P1:** pisahkan bucket 3/menit per email dan 100/menit per
IP. Nilai IP memberi ruang di atas regresi 50 pengguna; bukan target kapasitas
yang sudah dibuktikan. Pertahankan pengujian 50 pendaftaran dan tinjau ulang batas
ini berdasarkan pengukuran O1/P9. Kontrol abuse utama tetap email unik dan
verifikasi, bukan pembatasan ketat IP.

**Status.** Diterapkan dan lulus `RegistrationRateLimitTest`.

---

## K8 — `@can` di Blade tidak mengenali guard `customer` (bug P5a yang sudah terkirim)

**Masalah.** Tombol "Batalkan Pendaftaran" di halaman detail acara **tidak pernah
muncul** untuk pemiliknya. Ditemukan saat mengerjakan P6.

Penyebabnya: `@can` di Blade memakai **guard default**. Guard default baru menjadi
`customer` pada route yang berada di belakang `auth:customer`, karena middleware
`Authenticate` memanggil `shouldUse()` saat berhasil. Di route **publik**
(`acara/{event}`, `karir/{vacancy}`) guard default tetap `web`, jadi `@can` tidak
melihat user sama sekali dan semua ability mengembalikan `false` tanpa error.

**Yang membuat bug ini lolos review lebih penting dari bug-nya:** test yang
seharusnya menangkap ini **lulus**. `actingAs($customer, 'customer')` **juga**
memanggil `shouldUse()`, sehingga guard default menjadi `customer` untuk seluruh
request — menutupi perbedaan antara test dan browser sungguhan.

Dibuktikan dengan `auth('customer')->login()` (tanpa `actingAs`):
`/akun/acara` → tombol muncul; `/acara/{slug}` → tombol hilang.

**Keputusan: tambahkan closure `$customerCan` lewat view composer, dan pakai itu
di semua view pelanggan — bukan `@can`.**

**Alasan.**
- Eksplisit: `Gate::forUser(auth('customer')->user())` tidak bergantung pada
  guard default yang bisa berubah.
- Konsisten dengan pola `$lroute` dan `$settings` yang sudah ada.
- Satu mekanisme untuk route publik maupun privat, jadi memindahkan route tidak
  bisa mematikan tombol secara diam-diam lagi.

Regression guard: `CustomerBladeAuthorizationTest`, yang **sengaja tidak memakai
`actingAs`** karena itu yang menyembunyikan bug-nya.

**Pelajaran yang sama muncul dua kali lagi:** `actingAs($staff)` tanpa argumen
guard di test P6 juga salah — request `auth:customer` sebelumnya sudah mengubah
guard default menjadi `customer`, sehingga staff ikut login di guard yang salah
dan middleware menolak dengan 403. **Selalu sebutkan guard secara eksplisit.**

**Yang perlu user tinjau.** Tidak ada; sudah diperbaiki dan ditest. Tapi jika
nanti menambah view pelanggan, pakai `$customerCan(...)`, jangan `@can(...)`.

---

## K9 — `UploadedFile::fake()` tidak bisa menguji validasi MIME berbasis isi file

**Masalah.** Test "file PHP diganti nama jadi .pdf harus ditolak" **gagal** —
file tersebut diterima. Terlihat seperti lubang keamanan serius (RCE legacy
kembali).

**Ternyata bukan.** `Illuminate\Http\Testing\File::getMimeType()` mengembalikan
`MimeType::from($this->name)` — tipe diambil dari **nama file**, bukan isinya.
Jadi fake bernama `cv.pdf` selalu melaporkan `application/pdf` apa pun isinya,
dan aturan `mimetypes:application/pdf` meloloskannya.

Diukur langsung:

| | `getMimeType()` |
|---|---|
| `UploadedFile::fake()->createWithContent('cv.pdf', '<?php ...')` | `application/pdf` |
| `new UploadedFile($realTmpFile, 'cv.pdf', null, null, true)` | `text/x-php` |
| `mime_content_type()` atas file yang sama | `text/x-php` |

Jadi perlindungannya **berfungsi di produksi**; yang salah adalah alat ujinya.

**Keputusan: test RCE memakai `UploadedFile` sungguhan, bukan fake.**

**Alasan.** Ditulis dengan fake, test itu **lulus tanpa menguji apa pun** — dan
itu justru cara sebuah test suite upload memberi rasa aman yang palsu. Ditambah
satu test (`test_the_fake_upload_helper_cannot_detect_disguised_content`) yang
mematok perilaku fake tersebut, supaya kalau Laravel nanti mengubahnya, catatan
ini ikut gagal dan bisa dihapus.

**Yang perlu user tinjau.** Tidak ada. Tapi setiap test validasi upload yang
bergantung pada isi file harus memakai file sungguhan.

---

## K10 — Proyek "cancelled": ditampilkan atau disembunyikan?

**Masalah.** Brief hanya menyebut *"only the project that not done yet"*. Itu
jelas untuk status `completed`, tapi tidak menyelesaikan `cancelled`. Saya
sudah menandai ini sebagai pertanyaan terbuka saat menulis rencana P7.

**Pilihan.**
1. Tampilkan `cancelled` beserta statusnya.
2. Sembunyikan seperti `completed`.

**Keputusan: sembunyikan — diperlakukan sama seperti `completed`.**

**Alasan.**
- `cancelled` juga termasuk "tidak sedang dikerjakan", jadi masih sesuai brief.
- Pembatalan proyek adalah percakapan yang seharusnya dilakukan dengan orang,
  bukan diumumkan oleh halaman self-service. Pelanggan yang proyeknya
  dibatalkan biasanya sudah tahu alasannya, atau justru sedang berselisih —
  keduanya bukan situasi yang membaik dengan satu badge status.
- Lebih sedikit status yang bocor = lebih sedikit informasi untuk penyerang.
  `cancelled` memakai **respons negatif seragam** yang sama, jadi tidak bisa
  dibedakan dari "kode tidak ada".

Diimplementasikan sebagai `Project::HIDDEN_STATUSES`, dan diuji
(`test_a_cancelled_project_is_indistinguishable_from_an_unknown_code`).

**Yang perlu user tinjau.** Kalau ternyata pelanggan memang perlu melihat
status dibatalkan, hapus `STATUS_CANCELLED` dari `HIDDEN_STATUSES` — satu baris.
Tapi ingat: begitu dilakukan, "dibatalkan" menjadi respons yang **bisa
dibedakan**, dan itu mengonfirmasi bahwa kode proyeknya benar.

---

## K11 — Addendum K2: penyebab output buffer BUKAN Livewire asset injection

Saat P7, sempat muncul dugaan kuat bahwa isu risky-test K2 disebabkan oleh
Livewire yang menyuntikkan asset ke setiap respons HTML (dan injeksi semacam itu
biasanya memakai output buffering).

**Diuji langsung, dan dugaan itu salah:**

| Kondisi | `ob_get_level()` sebelum → sesudah `GET /` |
|---|---|
| `livewire.inject_assets = true` | `1 → 2` (bocor) |
| `livewire.inject_assets = false` | `1 → 2` (tetap bocor) |

Jadi injeksi Livewire **bukan** penyebabnya. Hipotesis yang sudah dieliminasi
untuk K2 sekarang menjadi enam: `ob_*` di kode sendiri, keseimbangan
`@section`, keseimbangan tag komponen, `@push`/`@once`/`x-slot`, render lewat
tinker, dan injeksi asset Livewire.

**Keputusan: tetap dicatat sebagai isu diketahui, tidak memblokir apa pun.**
Masih 8 test risky, masih 0 kegagalan, dan tidak ada assertion yang terpengaruh.

**Yang perlu user tinjau.** Kalau nanti dikejar lagi, enam kandidat di atas
sudah habis. Kandidat berikutnya: komponen Blade spesifik di `home.blade.php`
(dipersempit dengan membuang satu per satu), atau `laravel/boost` yang memang
hanya aktif di non-production.

---

## K12 — Livewire runtime dimuat di setiap halaman publik

**Temuan sampingan saat P7.** Dari perbandingan respons mentah terlihat Livewire
menyuntikkan `<!-- Livewire Styles -->` dan `<!-- Livewire Scripts -->`
(termasuk `livewire.js`) ke **setiap halaman publik** — padahal tidak ada satu
pun komponen Livewire di halaman publik. Livewire hanya dipakai oleh Filament
di `/admin`.

Artinya setiap pengunjung publik mengunduh satu file JS yang tidak pernah
dipakai. Pada target 500 pengguna serentak, itu 500 unduhan yang sia-sia pada
jalur yang justru paling ramai.

**Keputusan: dicatat untuk fase O, tidak diubah sekarang.**

**Alasan.** `livewire.inject_assets = false` lalu memanggil `@livewireStyles` /
`@livewireScripts` secara manual di layout Filament adalah perubahan yang
tampak kecil tapi bisa mematikan panel admin kalau salah — dan tidak ada
baseline pengukuran untuk membuktikan manfaatnya (D18: ukur dulu). Tempatnya di
**O2** (biaya request path), bukan di tengah fase fitur.

**Yang perlu user tinjau.** Masukkan ke O2. Perkiraan manfaat: satu request JS
per pengunjung hilang; risikonya panel admin perlu dites ulang setelahnya.
