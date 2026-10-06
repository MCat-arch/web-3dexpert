# 3D Expert Laravel Landing Page Parity Tasks

## Goal

Reproduce the legacy landing page content and section order from `../index.php` in Laravel. Visual design may be modernized, but content, resource usage, interactions, and overall ordering must remain equivalent.

## Agreed Landing Page Order

1. Header/navigation
2. Hero
3. Features
4. Portfolio
5. Testimonials
6. Benefits
7. Review desain
8. Location
9. Mengapa Memilih Kami
10. Services
11. Order flow
12. FAQ
13. Floating WhatsApp
14. Footer

## Working Rules

- Treat the legacy `index.php` as the content source of truth.
- Keep the Laravel architecture: controllers, Blade components, Eloquent models, translations, validation, and private media storage.
- Do not silently remove legacy content because it is malformed. Mark it for review and preserve it where possible.
- Each phase must end with an executable validation step.
- Do not begin the next phase until the current phase passes its acceptance criteria.

## Status Legend

- `[ ]` Not started
- `[-]` In progress
- `[x]` Complete
- `[!]` Blocked or requires a content decision

---

## Phase 0 - Baseline and Content Inventory [x]

- [x] Create a legacy-to-Laravel section checklist from `index.php`.
- [x] Record all legacy section titles, body copy, buttons, links, video URLs, FAQ items, benefit items, and asset references.
- [x] Record the expected order listed above as a parity test contract.
- [x] Identify which content comes from the legacy database and which content is hardcoded in PHP.
- [x] Identify content decisions that cannot be inferred safely, especially duplicate services and malformed prices.

Acceptance:

- Every visible legacy landing-page block has a target Laravel location. See `docs/landing-page-parity-inventory.md`.
- Every missing block has an explicit implementation task below.

## Phase 1 - Data and Resource Readiness

- [ ] Verify the Laravel destination database contains the migrated rows.
- [ ] Restore all hero images, logo files, icons, portfolio files, uploads, and PDF brochures.
- [ ] Link each service to its correct brochure file.
- [ ] Add the legacy company contact values to `site_settings`.
- [ ] Import all 25 legacy benefit items and their icon paths.
- [ ] Create a FAQ data source containing all 10 legacy FAQ questions and answers.
- [ ] Decide how duplicate or malformed services should be corrected, then publish the approved services.
- [ ] Ensure migrated media paths resolve through the Laravel media controller.

Acceptance:

- No migrated content references a missing local file.
- All required data is available to the landing-page controller without hardcoded legacy database queries.

## Phase 2 - Header, Hero, and Features

- [ ] Match the legacy header/navigation destinations and anchor behavior.
- [ ] Restore the consultation/WhatsApp CTA in the header.
- [ ] Restore the language switcher behavior for Indonesian and English.
- [ ] Render the hero carousel with all legacy hero images.
- [ ] Match the legacy hero headline, subtitle, booking CTA, and carousel behavior.
- [ ] Recreate the Features section immediately after the Hero.
- [ ] Render the Features heading, subheading, Projects counter, and Clients counter.
- [ ] Restore the `Semua Desain` and `Desain Terbaik` buttons.

Acceptance:

- Header -> Hero -> Features is the first visible page sequence.
- Hero and Features content matches the legacy Indonesian page.
- English content has an equivalent translation.

## Phase 3 - Portfolio, Testimonials, and Benefits

- [ ] Render the Portfolio section immediately after Features.
- [ ] Preserve the featured portfolio behavior.
- [ ] Preserve the legacy visible-item limit and load-more behavior without dropping records from the controller query.
- [ ] Ensure all portfolio cards have valid detail links and cover images where available.
- [ ] Render Testimonials after Portfolio.
- [ ] Preserve two highlighted testimonials at the top.
- [ ] Render all remaining testimonials with their titles and ratings.
- [ ] Render the complete Benefits section after Testimonials.
- [ ] Render all legacy benefit titles and icons.
- [ ] Restore the Benefits booking/consultation CTA.

Acceptance:

- Portfolio -> Testimonials -> Benefits order matches the legacy page.
- No benefit item from the legacy page is missing.
- No published portfolio renders a broken cover image.

## Phase 4 - Reviews, Location, and Why Choose Us

- [ ] Render Review desain after Benefits.
- [ ] Render all migrated design reviews without an unintended hard limit.
- [ ] Render Location after Review desain.
- [ ] Restore office name, address, Google Maps link, and location video.
- [ ] Render Mengapa Memilih Kami after Location.
- [ ] Restore the description, YouTube video, list 1, and list 2 content.
- [ ] Preserve the legacy section anchor used by navigation.

Acceptance:

- Review -> Location -> Mengapa Memilih Kami order matches the legacy page.
- Location and Why Choose Us remain visible when their migrated settings exist.

## Phase 5 - Services, Order Flow, FAQ, and Floating WhatsApp

- [ ] Render Services after Mengapa Memilih Kami.
- [ ] Render all four legacy service cards, including prices, discount text, and features.
- [ ] Restore the per-service brochure CTA.
- [ ] Restore the per-service WhatsApp consultation behavior.
- [ ] Render Order flow after Services.
- [ ] Restore the order-flow link and thumbnail/play treatment.
- [ ] Add the complete 10-item FAQ section after Order flow.
- [ ] Restore FAQ accordion behavior: one open item at a time and outside-click close.
- [ ] Restore the FAQ consultation CTA.
- [ ] Add the floating WhatsApp button and legacy WhatsApp icon.

Acceptance:

- Services -> Order flow -> FAQ -> Floating WhatsApp order matches the legacy page.
- All four brochures resolve through controlled Laravel routes.
- All 10 FAQ items are present in Indonesian and English.

## Phase 6 - Footer and Shared Content

- [ ] Render Footer after the floating WhatsApp control.
- [ ] Restore company logo, office address, and contact details.
- [ ] Restore Facebook, Instagram, YouTube, TikTok, and WhatsApp links.
- [ ] Restore footer quick links to the landing-page anchors.
- [ ] Verify the footer is present on Indonesian and English pages.

Acceptance:

- Footer is the final page block.
- No footer contact or social item is silently omitted when its setting exists.

## Phase 7 - Routing, Localization, and Compatibility

- [ ] Make default Indonesian routes work without an unwanted `/id` prefix.
- [ ] Keep `/en` routes working for the English landing page.
- [ ] Keep legacy redirects for `index.php`, `index-en.php`, `detail.php?id=N`, and `karir.php`.
- [ ] Ensure portfolio, service, career, brochure, and contact route model binding uses slugs correctly.
- [ ] Verify all header/footer anchors resolve to a real section.
- [ ] Verify all translated strings exist in both locale files.

Acceptance:

- Legacy URLs redirect correctly.
- New public URLs return successful responses.
- No route generates a missing-parameter or model-binding error.

## Phase 8 - Validation and Sign-off

- [ ] Run PHP syntax checks.
- [ ] Run Laravel view compilation.
- [ ] Run the Laravel test suite.
- [ ] Smoke-test every landing-page route and media endpoint.
- [ ] Check desktop layout at 1280px and 1920px.
- [ ] Check mobile layout at 375px and 768px.
- [ ] Compare section order against the agreed list.
- [ ] Compare content counts: hero, portfolio, testimonials, benefits, reviews, services, FAQ, and footer links.
- [ ] Check browser console for JavaScript errors.
- [ ] Confirm no missing image, PDF, icon, or video resource requests.
- [ ] Record remaining content decisions before production launch.

Final acceptance:

- The Laravel landing page contains the same content blocks as the legacy `index.php` in the agreed order.
- Design differences are intentional and documented.
- All local resources render from Laravel-managed paths.
- All known data discrepancies are resolved or explicitly approved for review.

## Current Known Gaps

- The legacy landing page contains 25 benefits; the migrated Laravel data currently contains fewer.
- The legacy landing page contains 10 hardcoded FAQ items; Laravel does not yet have a FAQ source.
- Services were migrated with `needs_review` and may be hidden by `is_published`.
- Legacy contact/social values were hardcoded in `partials/footer.php` and must be populated in Laravel settings.
- Legacy page order differs from the current Laravel `home.blade.php` order.
- Legacy default navigation uses homepage anchors; Laravel currently mixes route links and locale-prefixed routes.
