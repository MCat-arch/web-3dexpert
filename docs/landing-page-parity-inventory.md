# Landing Page Parity Inventory

Source of truth: legacy `../index.php` for Indonesian content and section order. The Laravel target is `resources/views/home.blade.php` plus its controller, components, shared layout, translations, database, and managed media.

## Agreed Render Order

| Order | Legacy block | Legacy source | Laravel target | Baseline status |
|---:|---|---|---|---|
| 1 | Header/navigation | `partials/header.php` | `layouts/app.blade.php`, `partials/header.blade.php` | Present, content differs |
| 2 | Hero | `index.php`, `hero_banner` | `HomeController`, `home.blade.php`, `HeroSlide` | Present |
| 3 | Features | `features_section` | `Settings`, `home.blade.php` | Present but wrong position/content shape |
| 4 | Portfolio | `properties`, `property_images` | `Portfolio`, portfolio components | Present, load-more behavior differs |
| 5 | Testimonials | `testimonial_section` | `Testimonial`, `video-embed` | Present, verify limits/order |
| 6 | Benefits | hardcoded markup and `assets/images/icons/` | `ValueProposition`, benefit component | Incomplete: most items missing |
| 7 | Review desain | `review_design` | `DesignReview`, `video-embed` | Present, Laravel limits to 6 |
| 8 | Location | `location_section` plus hardcoded office data | `site_settings`, `home.blade.php` | Video restored; office/map content incomplete |
| 9 | Mengapa Memilih Kami | `mengapa_section` | `Settings`, `ValueProposition`, `home.blade.php` | Present but wrong position |
| 10 | Services | `layanan_section`, `assets/pdf/` | `Service`, `service-card` | Data present but services unpublished |
| 11 | Order flow | `alur_section`, hardcoded thumbnail path | `site_settings`, `home.blade.php` | Link restored; thumbnail/content incomplete |
| 12 | FAQ | hardcoded 10 FAQ items in `index.php` | No Laravel equivalent yet | Missing |
| 13 | Floating WhatsApp | hardcoded `wa.me` and `assets/images/icons/ICS.png` | No Laravel equivalent yet | Missing |
| 14 | Footer | `partials/footer.php` | `partials/footer.blade.php` | Present; contact/social settings empty |

## Legacy Dynamic Baseline

Verified during the migration inventory:

- `hero_banner`: 5 rows
- `features_section`: 1 row
- `properties`: 4 rows
- `property_images`: 6 rows
- `testimonial_section`: 10 rows
- `review_design`: 3 rows
- `layanan_section`: 4 rows
- `location_section`: 1 row
- `mengapa_section`: 4 rows
- `alur_section`: 1 row
- `job_detail`: 3 rows
- `users`: 1 row

Features baseline:

- Heading: `Satukan Imajinasi dan Realitas Bersama Arsitek Profesional`
- Subheading: `Ini adalah sub konten dari hosting`
- Projects: `736`
- Clients: `581`

Legacy static hero content:

- Indonesian heading: `Wujudkan Rumah Mewah dengan Sentuhan Profesional`
- Indonesian subtitle: `Perpaduan sempurna antara estetika, fungsi, dan eksklusivitas.`
- Indonesian CTA: `Booking Sekarang`
- English heading: `Create a Luxurious Home with a Professional Touch`
- English subtitle: `The perfect blend of aesthetics, function and exclusivity.`
- English CTA: `Book Now`
- CTA target: legacy WhatsApp contact
- Carousel interval: 4 seconds

## Legacy Benefits Checklist

All of these must be represented in Laravel with their corresponding icon:

- Desain Interior
- Desain Exterior
- Gambar Kerja Pembangunan
- Gambar Kerja Perijinan
- Rencana Anggaran Biaya
- Detail Furnitur
- File Desain 3D dan 2D
- Animasi 3D
- Analisis Struktur
- Pengawasan Konstruksi
- Konsultasi Gratis
- Survey Lokasi Proyek
- CS Responsif
- Hardfile A4 Jilid Premium
- Softfile Siap Print
- Pembayaran Bertahap
- Garansi Bisa Dibangun
- Desain Sesuai Harapan
- Pemilihan Material Optimal
- Optimasi Lahan Sempit dan Luas
- Koordinasi Desain Saat Pembangunan
- Pendampingan Sidang Perijinan
- Efisiensi Biaya Pembangunan
- Garansi Lolos Perijinan Pembangunan

Legacy benefit CTA: `Booking Sekarang` -> WhatsApp.

## Legacy FAQ Checklist

The FAQ section contains these 10 items and must remain after Order flow:

1. Apa saja layanan yang disediakan 3D Expert?
2. Apakah saya bisa revisi desain jika belum sesuai?
3. Apakah layanan 3D Expert bisa dilakukan secara online?
4. Apa saja yang saya dapatkan dalam paket desain?
5. Berapa lama waktu yang dibutuhkan untuk proses desain?
6. Apakah 3D Expert bisa membantu dalam perencanaan?
7. Bagaimana sistem pembayaran?
8. Apa yang dimaksud dengan `Garansi Bisa Dibangun`?
9. Apakah harga desain bisa disesuaikan dengan budget?
10. Bagaimana cara memulai kerja sama?

Each item has a legacy answer in `index.php`; the complete answer text must be moved into a Laravel FAQ data source rather than copied into a controller or mixed into the layout.

## Legacy Service and Brochure Mapping

The original `index.php` maps service IDs to brochures:

| Legacy service ID | Brochure |
|---:|---|
| 1 | `Gambar Perijinan.pdf` |
| 2 | `Arsitektur EKSTERIOR.pdf` |
| 3 | `Arsitektur INTERIOR.pdf` |
| 4 | `PEMBANGUNAN Lengkap.pdf` |

All four rows must remain visible after the content decision resolves duplicate titles and malformed prices.

## Legacy Location and Why Content

Location:

- Heading: `Alamat Kantor`
- Office label: `HEAD OFFICE PT LUXORA AURA`
- Address: `Head Office PT Luxora Aura Semesta`, `Jl. Bendul Merisi Selatan 7 no 57 Surabaya`
- Google Maps link: legacy hardcoded office map URL
- Video: `location_section.video_url`

Why Choose Us:

- Heading: `Mengapa Memilih Kami?`
- Description: `mengapa_section.description`
- Video: `mengapa_section.video_url`
- List 1: all non-null `mengapa_section.list1` values
- List 2: all non-null `mengapa_section.list2` values

Order flow:

- Heading: `Alur Pemesanan 3D Expert`
- Link: `alur_section.link_url`
- Legacy thumbnail path was placeholder `path-to-your-thumbnail.jpg`; replace with a real managed asset or retain a clear fallback.

## Resource Inventory

- Legacy hero images: `assets/images/hero_*`
- Legacy logo: `assets/images/logo.png`
- Legacy social/benefit icons: `assets/images/icons/`
- Legacy portfolio image: `assets/images/project-1.png` and any files referenced by database rows
- Legacy uploads: `uploads/`
- Legacy brochures: `assets/pdf/`
- Laravel managed media: `storage/app/media/hero`, `portfolio`, and `brochures`
- Laravel public brand assets: `public/images/`

## Known Gaps and Decisions

- The legacy database connection was unavailable during the final Phase 0 query retry; restart MySQL before Phase 1 verification.
- The legacy `desain_rumah` and `galeri_rumah` tables were absent from the available legacy database, so there are no rows to migrate from them.
- All four migrated services were flagged for review and are currently unpublished. Publishing requires correcting duplicate titles and malformed prices without deleting rows.
- Company address, email, WhatsApp, phone, maps, and social links were hardcoded in the legacy footer and need explicit Laravel settings.
- The legacy default navigation uses anchors; Laravel currently mixes route links with locale-prefixed routes. This is a Phase 7 compatibility task.
- Legacy portfolio rows currently use external image URLs. Local assets must not be assumed to belong to a portfolio unless the database or a content decision establishes that mapping.

## Phase 0 Completion Criteria

- Every legacy block has a Laravel target or an explicit missing task.
- All known static benefit and FAQ content is listed.
- Dynamic row counts and key feature values are recorded.
- Asset and brochure sources are recorded.
- Unresolved content decisions are explicit and not silently discarded.
