# Task Tracker: Design System Adjustment (`task_design.md`)

Berdasarkan `docs/design.md`, implementasi penyesuaian desain ini mengubah seluruh halaman publik aplikasi Laravel (`3dexpert-web`) agar setia pada tema *root company profile* warisan (legacy plain-PHP site):
- **Base halaman:** `--paper` (`#f5f5f5`) untuk feature pages, header & footer gelap, marketing landing sections gelap (`--ink-950`), aksen gold gradient (`#feca07` → `#ffae2e`).
- **CSS Architecture:** Menghapus Tailwind CSS sepenuhnya, beralih ke Semantic Plain CSS di `resources/css/app.css`.
- **Typography:** Google Fonts Montserrat (300, 400, 500, 600, 700).
- **Responsivitas:** Wajib 100% responsif di mobile (<480px, <768px), tablet (<1024px), desktop (1024px-1200px), dan layar lebar (1920px). Touch targets minimal 44px, drawer mobile slide-in 280px, fluid typography (`clamp()`), flex/grid adaptif tanpa overflow horizontal.
- **Alpine.js:** Tetap CSP-safe (bare `x-data`, tanpa inline expression).
- **Paritas:** Urutan 14 seksi di `home.blade.php` tetap presisi, link WhatsApp dinamis via `$settings('company_whatsapp')`.

---

## Ringkasan Pemetaan Berkas (File Mapping)

| Kategori | File Target | Rincian Perubahan |
|---|---|---|
| **Foundation & Build** | `docs/design.md` | Disalin sebagai Single Source of Truth (SST) spesifikasi desain. |
| | `package.json` | Hapus `tailwindcss` & `@tailwindcss/vite`, pertahankan alpine & vite. |
| | `vite.config.js` | Hapus plugin `@tailwindcss/vite`. |
| | `resources/css/app.css` | Tulis ulang penuh: tokens `:root`, reset, Montserrat, utilities layout semantik, tombol, cards, forms, tabel, accordion, badge, dan aturan state Alpine (`[data-*]`). |
| | `resources/js/app.js` | Verifikasi handler toggle class agar sinkron dengan class semantik baru. |
| **Layout & Shell** | `resources/views/layouts/app.blade.php` | Swap Google Fonts Inter → Montserrat, ganti styling body dari utility ke `.site-body`, perbarui skip-to-content. |
| | `resources/views/partials/header.blade.php` | Header gelap `--ink-900`, logo 35px, nav tengah, right cluster (lang + CTA Konsultasi + hamburger), drawer mobile slide-in 280px. |
| | `resources/views/partials/footer.blade.php` | Footer gelap `--ink-800`, 4 kolom responsif, icon medsos kotak 40px border hover gold, copyright. |
| **Shared Components** | `components/nav-link.blade.php` | Class `.nav-link`, `.is-active`. |
| | `components/language-switcher.blade.php` | Dropdown `--ink-600`, border `--line-dark-2`, item aktif background `#444` teks gold. |
| | `components/section-heading.blade.php` | Eyebrow emas uppercase tracking-widest, h2 2.5rem/600, subtitle. |
| | `components/alert.blade.php` | `.alert`, `.alert-success`, `.alert-error`. |
| | `components/empty-state.blade.php` | `.empty-state`, icon, judul, deskripsi. |
| | `components/floating-whatsapp.blade.php` | Fixed bottom-right, gold circle gradient, SVG WA. |
| | `components/star-rating.blade.php` | `.star-rating`, `.star-icon` emas. |
| | `components/faq-accordion.blade.php` | Native `<details name="faq">`, styling `.faq-details`, rotasi chevron 180°. |
| | `components/benefit-tile.blade.php` | `.benefit-tile`, icon + label tanpa border/card chrome. |
| | `components/video-facade.blade.php` | Card video dengan thumbnail dan play button emas. |
| | `components/video-embed.blade.php` | Responsive container iframe. |
| | `components/portfolio-card.blade.php` | Card portfolio dengan varian dark landing dan light page. |
| | `components/service-card.blade.php` | Card services di background `--ink-600`. |
| | `components/event-card.blade.php` | Card event di background `--paper` dengan badge tiket. |
| | `components/course-card.blade.php` | Card kursus di background `--paper` dengan badge level dan harga. |
| | `components/customer-field.blade.php` | Form group, input border `--line-light`, label `--text` 500, ring fokus emas. |
| | `components/customer-auth-card.blade.php` | Wrapper putih radius 8px, shadow lembut. |
| **Landing Page** | `resources/views/home.blade.php` | Bersihkan Tailwind utility dari seluruh 14 seksi (Hero, Features, Portfolio, Testimoni, Benefit, Review Desain, Location, Mengapa Kami, Services, Order Flow, FAQ, dll.). |
| **Feature: Portfolio** | `resources/views/portfolio/index.blade.php` | Filter kategori pill, grid card portfolio, paginasi semantik. |
| | `resources/views/portfolio/show.blade.php` | Galeri thumbnail, detail spesifikasi produk, breadcrumb, related items. |
| **Feature: Events** | `resources/views/events/index.blade.php` | Tab filter event, grid event cards, paginasi. |
| | `resources/views/events/show.blade.php` | Banner event, info pembicara, agenda, tombol registrasi. |
| | `resources/views/events/mine.blade.php` | Daftar tiket event yang diikuti pengguna. |
| **Feature: Careers** | `resources/views/careers/index.blade.php` | Filter lowongan, list card karir. |
| | `resources/views/careers/show.blade.php` | Detail kualifikasi & tanggung jawab pekerjaan, CTA lamar. |
| | `resources/views/careers/apply.blade.php` | Formulir lamaran kerja dengan input file resume. |
| | `resources/views/careers/mine.blade.php` | Riwayat dan status lamaran kerja pelamar. |
| **Feature: Courses** | `resources/views/courses/index.blade.php` | Katalog kursus, filter kategori/level, list kursus. |
| | `resources/views/courses/show.blade.php` | Silabus kurikulum terstruktur, mentor card, pricing card CTA. |
| | `resources/views/courses/checkout.blade.php` | Ringkasan pemesanan, data pembeli, rincian biaya. |
| | `resources/views/courses/payment.blade.php` | Instruksi pembayaran, countdown/status, simulator pembayaran. |
| | `resources/views/courses/player.blade.php` | Area pemutar video + playlist kurikulum silabus. |
| **Feature: Tracker** | `resources/views/tracker/index.blade.php` | Input pelacakan proyek, visualisasi timeline milestone. |
| **Feature: Customer** | `resources/views/customer/account.blade.php` | Halaman pengaturan akun pelanggan & ganti kata sandi. |
| | `resources/views/customer/courses.blade.php` | Dashboard kursus saya (enrolled courses & progress). |
| | `customer/auth/login.blade.php` | Halaman login dengan auth card semantik. |
| | `customer/auth/register.blade.php` | Halaman registrasi akun baru. |
| | `customer/auth/forgot-password.blade.php`| Formulir permohonan reset password. |
| | `customer/auth/reset-password.blade.php` | Formulir pembaharuan password baru. |
| | `customer/auth/verify-email.blade.php`  | Tampilan verifikasi alamat email. |
| **Error Pages** | `resources/views/errors/404.blade.php` | Error 404 berpenampilan rapi dengan font Montserrat & CTA ke beranda. |
| | `resources/views/errors/500.blade.php` | Error 500 berpenampilan rapi dengan font Montserrat & CTA ke beranda. |

---

## Implementation Checklist & Progress Tracker

- [x] **Fase 0: Inisialisasi & Dokumentasi**
  - [x] Salin `design.md` ke `3dexpert-web/docs/design.md`.
  - [x] Petakan seluruh berkas yang akan diubah dan buat `3dexpert-web/task_design.md`.

- [x] **Fase 1: Foundation (CSS & Build Tooling)**
  - [x] Bersihkan `package.json` (buang `tailwindcss` dan `@tailwindcss/vite`).
  - [x] Perbarui `vite.config.js` (hapus import dan call `tailwindcss()`).
  - [x] Jalankan `npm install` untuk memperbarui `package-lock.json`.
  - [x] Tulis ulang `resources/css/app.css` dengan token lengkap, base reset, Montserrat font, styling semantik komprehensif, dan aturan `[data-*]` Alpine.
  - [x] Uji `npm run build` untuk memastikan kompilasi aset berhasil tanpa error.

- [x] **Fase 2: Layout & Shared Components**
  - [x] Sesuaikan `resources/views/layouts/app.blade.php` (Google Fonts Montserrat, body class `.site-body`, skip-link).
  - [x] Desain ulang `resources/views/partials/header.blade.php` (header sticky, logo 35px, nav, drawer mobile 280px).
  - [x] Desain ulang `resources/views/partials/footer.blade.php` (footer gelap `--ink-800`, medsos kotak 40px, layout 4 kolom).
  - [x] Refactor komponen navigasi & interaksi:
    - [x] `resources/views/components/nav-link.blade.php`
    - [x] `resources/views/components/language-switcher.blade.php`
    - [x] `resources/views/components/floating-whatsapp.blade.php`
  - [x] Refactor komponen visual & status:
    - [x] `resources/views/components/section-heading.blade.php`
    - [x] `resources/views/components/alert.blade.php`
    - [x] `resources/views/components/empty-state.blade.php`
    - [x] `resources/views/components/star-rating.blade.php`
    - [x] `resources/views/components/faq-accordion.blade.php`
    - [x] `resources/views/components/benefit-tile.blade.php`
    - [x] `resources/views/components/video-facade.blade.php`
    - [x] `resources/views/components/video-embed.blade.php`
  - [x] Refactor komponen kartu & form:
    - [x] `resources/views/components/portfolio-card.blade.php`
    - [x] `resources/views/components/service-card.blade.php`
    - [x] `resources/views/components/event-card.blade.php`
    - [x] `resources/views/components/course-card.blade.php`
    - [x] `resources/views/components/customer-field.blade.php`
    - [x] `resources/views/components/customer-auth-card.blade.php`

- [x] **Fase 3: Landing Page (`home.blade.php`)**
  - [x] Seksi 2: Hero Section (carousel 90vh, italic subtitle, button putih, dots indikator).
  - [x] Seksi 3: Features Section (`--ink-950`, tombol solid putih & outline amber, statistik).
  - [x] Seksi 4: Portfolio Section (`--ink-950`, featured card 2x2 grid).
  - [x] Seksi 5: Testimonials Section (`--ink-950`, 2 featured video facades + 4 video grid).
  - [x] Seksi 6: Benefits Section (`--card` putih, teks `--text`, 5 tiles per baris).
  - [x] Seksi 7: Review Desain Section (`--ink-950`, 2x2 grid video).
  - [x] Seksi 8: Location Section (gradien `--ink-900` ke `--ink-500`, inner card radius 20px).
  - [x] Seksi 9: Mengapa Memilih Kami (gradien background, white content wrapper radius 20px).
  - [x] Seksi 10: Services Section (`--ink-600`, 4 service cards).
  - [x] Seksi 11: Order Flow Section (`--paper`, kartu video dengan play button).
  - [x] Seksi 12: FAQ Section (`--ink-800`, accordion `<details name="faq">`).

- [ ] **Fase 4: Feature Pages**
  - [ ] **Portfolio**:
    - [ ] `resources/views/portfolio/index.blade.php`
    - [ ] `resources/views/portfolio/show.blade.php`
  - [ ] **Events**:
    - [ ] `resources/views/events/index.blade.php`
    - [ ] `resources/views/events/show.blade.php`
    - [ ] `resources/views/events/mine.blade.php`
  - [ ] **Careers**:
    - [ ] `resources/views/careers/index.blade.php`
    - [ ] `resources/views/careers/show.blade.php`
    - [ ] `resources/views/careers/apply.blade.php`
    - [ ] `resources/views/careers/mine.blade.php`
  - [ ] **Courses**:
    - [ ] `resources/views/courses/index.blade.php`
    - [ ] `resources/views/courses/show.blade.php`
    - [ ] `resources/views/courses/checkout.blade.php`
    - [ ] `resources/views/courses/payment.blade.php`
    - [ ] `resources/views/courses/player.blade.php`
  - [ ] **Tracker**:
    - [ ] `resources/views/tracker/index.blade.php`
  - [ ] **Customer & Auth**:
    - [ ] `resources/views/customer/account.blade.php`
    - [ ] `resources/views/customer/courses.blade.php`
    - [ ] `resources/views/customer/auth/login.blade.php`
    - [ ] `resources/views/customer/auth/register.blade.php`
    - [ ] `resources/views/customer/auth/forgot-password.blade.php`
    - [ ] `resources/views/customer/auth/reset-password.blade.php`
    - [ ] `resources/views/customer/auth/verify-email.blade.php`
  - [ ] **Errors**:
    - [ ] `resources/views/errors/404.blade.php`
    - [ ] `resources/views/errors/500.blade.php`

- [ ] **Fase 5: Validasi, Quality Sweep, dan Testing**
  - [ ] Jalankan regex sweep Tailwind untuk memastikan 0 sisa utility class di `resources/views`:
    `rg "neutral-|amber-|bg-white|text-white|border-white|rounded-|grid-cols|flex |gap-|px-|py-|text-sm|font-" resources/views`
  - [ ] Jalankan `npm run build` (harus sukses dan bersih).
  - [ ] Jalankan `php artisan test` (seluruh unit test dan feature test harus hijau/lolos).
  - [ ] Uji responsivitas dan konsistensi dwibahasa (ID & EN).
