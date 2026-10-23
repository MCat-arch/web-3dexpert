# Design System — 3D Expert Web (design.md)
Single source of truth for the visual design of every public page of the Laravel app (`3dexpert-web/`). The theme is derived from the root company profile — the legacy plain-PHP site at the repo root (`index.php`, `partials/header.php`, `partials/footer.php`, `assets/css/style.css`).

Permanent home: `3dexpert-web/docs/design.md` (implementation step 0 copies this file there).

## 1. Locked decisions
| # | Decision | Consequence |
|---|---|---|
| D1 | Theme = root company profile theme | Light page base `#f5f5f5`, dark header/footer, dark landing sections, gold gradient CTAs, Montserrat |
| D2 | Plain CSS, Tailwind removed | Hand-written CSS with semantic class names in `resources/css/app.css`; no Tailwind utilities in markup |
| D3 | Keep Blade components + CSP-safe Alpine | `x-*` components stay; Alpine stays CSP-safe (bare `x-data` names, no expressions — see §6) |
| D4 | Montserrat via Google Fonts | Weights 300/400/500/600/700; replaces Inter |
| D5 | Whole-site scope | Landing + shared layout + all feature folders (careers, events, customer, tracker, portfolio, courses) + auth + error pages |

Deliberate deviation from legacy: legacy used Segoe UI for body text and Montserrat for headings/nav. We use Montserrat for everything — one family, cleaner hierarchy. Legacy body weights were 300; we use 400 for body, 300 for large light text, 500/600/700 for emphasis.

## 2. Design tokens
### 2.1 Colors
```css
:root {
    /* Brand gold (primary) */
    --gold:        #feca07;   /* primary accent, links-on-dark, focus */
    --gold-2:      #ffae2e;   /* gradient end */
    --gold-hover:  #d4950d;   /* pressed/active */
    --gold-soft:   #f8b500;   /* touch feedback (legacy) */
    --amber:       #fbbf24;   /* outline buttons, badges */

    /* Dark surfaces (header/footer/landing sections) */
    --ink-950:     #111111;   /* hero, features, portfolio, testimonials, reviews */
    --ink-900:     #1a1a1a;   /* header, location gradient start */
    --ink-800:     #1b1b1b;   /* footer, FAQ */
    --ink-700:     #1f1f1f;   /* card gradient end */
    --ink-600:     #2a2a2a;   /* services, dropdowns, mobile menu */
    --ink-500:     #2d2d2d;   /* gradient end (location, mengapa) */
    --line-dark:   #333333;   /* borders on dark */
    --line-dark-2: #444444;   /* dropdown borders */

    /* Light surfaces (page base, feature pages, benefit section) */
    --paper:       #f5f5f5;   /* page background */
    --card:        #ffffff;
    --text:        #333333;
    --text-muted:  #666666;
    --line-light:  #e5e5e5;

    /* Text on dark */
    --on-dark:         #ffffff;
    --on-dark-2:       #cccccc;   /* nav links */
    --on-dark-muted:   rgba(255,255,255,.70);
}
```

### 2.2 Typography
- **Family**: `'Montserrat', ui-sans-serif, system-ui, sans-serif`
- **Landing h1**: 3rem/700 (mobile 2.8rem) · section h2: 2.5rem/600 (mobile ~1.75rem)
- **Body**: 1rem/400, line-height 1.6–1.7 · small: 0.875rem · eyebrow: 0.75rem 600 uppercase tracking-widest
- **Hero subtitle**: italic (legacy signature — keep)

### 2.3 Container, radius, shadow
- **Container**: `max-width: 1200px; margin-inline: auto; padding-inline: 20px` (legacy spec; not 1280px)
- **Radius**: buttons `5px` · cards `8px` · feature wrappers `20px`
- **Gold CTA shadow**: `0 2px 8px rgba(254,202,7,.3)`, hover `0 4px 12px rgba(254,202,7,.4)`
- **Light card shadow**: `0 25px 60px rgba(0,0,0,.15)` (white wrappers on dark sections)

### 2.4 Buttons
| Variant | Style |
|---|---|
| `.btn-primary` (CTA "Konsultasi", "Booking Sekarang") | `background: linear-gradient(180deg, var(--gold), var(--gold-2)); color: #000; font-weight: 500; border-radius: 5px; hover: reversed gradient + translateY(-2px) + gold glow` |
| `.btn-white` (hero "Booking Sekarang", "Semua Desain") | `white bg, #111 text, 4px radius` |
| `.btn-outline` ("Desain Terbaik") | `transparent, 2px solid var(--amber), white text; hover gold-tinted bg` |
| `.nav-link` | `var(--on-dark-2), Montserrat 300 14px; hover white; active gold` |

### 2.5 Icons
Inline SVG only. The CSP (`SecurityHeaders.php`) allows `style-src` from `fonts.googleapis` but does NOT allow `cdnjs.cloudflare.com`, so legacy's Font Awesome 6 CDN is blocked. Every legacy FA icon (social, map pin, check, chevron, hamburger) becomes an inline SVG — the pattern already used in current components.

### 2.6 Responsive Breakpoints & Adaptation
The layout and all components must be fully responsive across all device sizes (mobile, tablet, desktop, ultra-wide):
- **Breakpoints**:
  - Small Mobile: `< 480px` (fluid padding, 1 column, touch targets >= 44px, text scaling).
  - Mobile/Tablet portrait: `< 768px` (nav drawer slide-in, stacked grids, 1-col cards, full-width buttons where needed).
  - Tablet landscape / Small laptop: `< 1024px` (nav collapses to drawer, 2-column grids for cards and features).
  - Desktop standard: `1024px – 1200px` (horizontal desktop nav, 3 or 4-column grids).
  - Wide screens: `> 1200px` (max-width: 1200px centered container, crisp typography, no horizontal overflow).
- **Responsive Fluidity**:
  - Container: `width: 100%; max-width: 1200px; margin-inline: auto; padding-inline: clamp(16px, 4vw, 24px)`.
  - Fluid typography: h1 `clamp(2rem, 5vw, 3rem)`, h2 `clamp(1.75rem, 4vw, 2.5rem)`.
  - Responsive tables / horizontal scrollers with accessible touch scrolling.
  - Form fields adapt from 2-columns on desktop to 1-column on mobile.

## 3. Landing page section rhythm (parity order per task.md)
| # | Section | Background | Notes |
|---|---|---|---|
| 1 | Header | `--ink-900`, border-bottom `--line-dark` | sticky (improvement over legacy static — document) |
| 2 | Hero | image + overlay linear-gradient(to top, rgba(17,17,17,1) 5%, …, rgba(0,0,0,.1) 80%), 90vh | white h1 + italic subtitle + white hero button |
| 3 | Features | `--ink-950` (+ optional wave pattern, see §9) | h2 2.5rem/600; white solid + amber-outline buttons; stats row |
| 4 | Portfolio | `--ink-950` | grid, featured card spans 2×2 |
| 5 | Testimonials | `--ink-950` | 2 highlighted video facades, then rows of 4 |
| 6 | Benefits | `--card` (white), `--text` | the one fully light landing section; 5-per-row tiles |
| 7 | Review desain | `--ink-950` | 2×2 video grid |
| 8 | Location | `linear-gradient(135deg, --ink-900, --ink-500)` | inner card linear-gradient(145deg, --ink-600, --ink-700), radius 20px |
| 9 | Mengapa Memilih Kami | `linear-gradient(135deg, --ink-900, --ink-500)` | white content wrapper radius 20px |
| 10 | Services | `--ink-600` | 4 service cards |
| 11 | Order flow | `--paper` | video thumbnail card with play button |
| 12 | FAQ | `--ink-800` | accordion |
| 13 | Floating WhatsApp | fixed, gold gradient circle | |
| 14 | Footer | `--ink-800` (`--ink-950` under prefers-color-scheme: dark) | logo, quick links, socials, address, copyright |

Feature pages (`/portofolio`, `/acara`, `/karir`, `/kursus`, `/lacak-proyek`, `/masuk`, `/daftar`, `/akun`): light page base `--paper`, dark header/footer, white cards with `--line-light` borders and `--text`. This matches the legacy split: dark marketing sections on the landing page, light working pages.

## 4. Component specifications
- **Header (`partials/header.blade.php`)**: logo 35px tall (brightness 1.2), centered nav, right cluster = language switcher + gold "Konsultasi" button + hamburger (<1024px). Mobile menu: 280px right slide-in, `--ink-900`, overlay `rgba(0,0,0,.8)`; links `var(--on-dark-2)` 16px/300, hover gold + 10px indent + gold tick bar.
- **Language switcher**: flag + chevron; dropdown `--ink-600` bg, `--line-dark-2` border; active option `--ink-600`… legacy used `#444` bg + gold text — keep that.
- **Section heading (`x-section-heading`)**: centered eyebrow (gold, uppercase, tracking-widest) + h2 + optional subtitle.
- **Cards (`x-portfolio-card`, `x-service-card`, `x-event-card`, `x-course-card`)**: `--ink-600`/`--ink-700` gradient or solid `--ink-600` on dark sections; `--card` on light pages; border: 1px solid `var(--line-dark)`; hover: gold border `rgba(254,202,7,.4)` + lift.
- **Benefit tiles (`x-benefit-tile`)**: icon + label, centered column, no card chrome (legacy `.benefit-item`).
- **Forms (`x-customer-field`, `x-customer-auth-card`)**: light page; label `--text` 500; input white bg, `--line-light` border, gold focus ring; error `--text` red `#b91c1c`; auth card = white wrapper, radius 8px, light shadow.
- **Badges**: free = `--gold` tint on dark; paid = amber tint; status pills keep semantic colors (emerald/amber/red) but restyled to the token palette.
- **FAQ accordion (`x-faq-accordion`)**: native `<details name="faq">`, question row `--on-dark`, chevron rotates 180°.
- **Footer**: 4-column grid (logo+address / quick links / socials / contact), social icons = 40px bordered squares, hover gold.
- **Floating WhatsApp**: fixed bottom-right, gold gradient, inline WhatsApp SVG.

## 5. File inventory (what changes)
### Foundation
1. `resources/css/app.css` — full rewrite: tokens + base + component classes; REMOVE `@import 'tailwindcss'` and the `@theme` block; KEEP the `[data-*]` state rules (carousel, nav panel, FAQ, gallery, reveal) — they are already plain CSS.
2. `package.json` — remove `tailwindcss`, `@tailwindcss/vite`; keep `alpinejs`, `@alpinejs/csp`, `@alpinejs/collapse`, `laravel-vite-plugin`, `vite`. Then `npm install`.
3. `vite.config.js` — remove the `tailwindcss()` plugin import and entry.
4. `resources/js/app.js` — no structural change (components toggle semantic classes, not utilities); verify after CSS rewrite.

### Layout & shared
5. `resources/views/layouts/app.blade.php` — swap Inter→Montserrat font link; body class → bg `var(--paper)` text `var(--text)`… (plain CSS: `class="page"` styled in `app.css`). Brand icons (`favicon.ico`, PNG favicons, Apple/Android icons, and `site.webmanifest`) are linked from this layout; regenerate them with `php deploy/generate-brand-icons.php`.
6. `resources/views/partials/header.blade.php`, `partials/footer.blade.php` — restyle per §4.
7. **Components**: `section-heading`, `nav-link`, `language-switcher`, `alert`, `empty-state`, `floating-whatsapp`, `star-rating`, `faq-accordion`, `course-card`, `event-card`, `portfolio-card`, `service-card`, `benefit-tile`, `video-facade`, `video-embed`, `customer-field`, `customer-auth-card`.

### Landing page
8. `resources/views/home.blade.php` — all 14 sections; replace every Tailwind utility with semantic classes; content and section ORDER unchanged (parity contract in `task.md`).

### Feature folders
9. `views/portfolio/index.blade.php`, `views/portfolio/show.blade.php`
10. `views/events/index.blade.php`, `show`, `mine`
11. `views/careers/index.blade.php`, `show`, `apply`, `mine`
12. `views/courses/index.blade.php`, `show`, `checkout`, `player`; shared payment page in `views/payments/show.blade.php` (courses and paid events)
13. `views/tracker/index.blade.php`
14. `views/customer/account.blade.php`, `views/customer/courses.blade.php`, `customer/auth/{login,register,forgot-password,reset-password,verify-email}.blade.php`
15. `views/errors/404.blade.php`, `errors/500.blade.php`

## 6. Hard technical constraints (do not violate)
- **CSP**: `script-src 'self'` with no `unsafe-eval`/`unsafe-inline`. Alpine must stay the `@alpinejs/csp` build: bare `x-data="name"`, zero expressions, all visual state via classes toggled in `app.js`. No inline `<script>` in views. No external icon CDN.
- **No-JS defaults**: server-rendered markup must be the correct state (first hero slide active, mobile menu closed, FAQ readable via native `<details>`).
- **i18n**: all strings through `__('key')` / `lang/id` + `lang/en`; no hardcoded copy. Both locales must render identically.
- **Media**: images/PDFs only via `route('media.show', …)` or `asset()` for public brand files; never a storage path.
- **CTAs**: consultation/booking links come from `$settings('company_whatsapp')` — never a hardcoded number, never a `contact.create` route (D12).
- **Accessibility**: visible `:focus-visible` gold ring, `aria-expanded`/`aria-current` on toggles, `prefers-reduced-motion` disables carousel/scroll animation.
- **Performance**: hero slide 1 eager + `fetchpriority="high"`, everything else lazy; video embeds stay click-to-load (`ytFacade`).

## 7. Implementation order
1. Copy this file to `3dexpert-web/docs/design.md`.
2. Foundation: `app.css` rewrite → `package.json`/`vite.config.js` → `npm install` → `npm run build` (must succeed before any view work).
3. Layout: `layouts/app`, `partials/header`, `partials/footer`.
4. Shared components (§5 item 7).
5. Landing page + its components.
6. Feature folders, one folder per pass.
7. Error pages.
8. Sweep: `rg "neutral-|amber-|bg-white|text-white|border-white|rounded-|grid-cols|flex |gap-|px-|py-|text-sm|font-" resources/views` — no Tailwind utility classes may remain.

## 8. Validation
- `npm run build` clean; `php artisan test` green.
- `rg` sweep returns nothing.
- Visual check at 375 / 768 / 1280 / 1920 px, both locales.
- Browser console clean; no missing asset requests.
- Section order on `/` matches the 14-section contract.

## 9. Open items / risks
- **Wave pattern**: legacy sections reference `images/wave-pattern.png`, which does not exist in the assets — it 404'd in legacy too. Recommendation: skip the decoration or supply an asset.
- **Dependency change**: removing Tailwind is a `package.json` change — the plain-CSS decision (D2) is the approval; record it in the commit message.
- **Filament admin** (`public/css/filament`) has its own theme and is out of scope.
- **Regression risk**: ~40 Blade files change at once. Mitigation: the §7 sweep + the existing test suite; check each page in both locales.
