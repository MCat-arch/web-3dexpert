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
