# Phase O3 — Media & Static Asset Delivery

| | |
|---|---|
| **Depends on** | O1 (baseline), O2 (sizing depends on this phase's outcome) |
| **Blocks** | O2's `max_children` sizing is only viable after this |
| **Blocked by** | `[!]` A23 — approval to empty `media_assets.content` |
| **Plan stability** | **Firm** — `MediaController` and the migrations were read directly |

## Objective

Stop serving images through PHP. **This is the top traffic risk for the launch
profile** (`notes.md` §9.3 B1) and the phase with the largest structural payoff.

---

## The problem, precisely

`MediaController::show()` per image request:

1. `abort_if` path traversal, `abort_unless` allowed extension.
2. One indexed query on `media_assets.path`.
3. Then **either** a disk read via `Storage::disk('media')->readStream()` when
   `storage_path` is filled, **or** a second query streaming the BLOB with
   `MYSQL_ATTR_USE_BUFFERED_QUERY` disabled.
4. `fpassthru()` — PHP pushes every byte to the client.

Step 4 is the problem. **A PHP-FPM worker is held for the entire transfer.** On a
slow mobile connection that is seconds per image, and Indonesian mobile is a large
share of this audience.

Workers are scarce — 32–40, not 500. Launch means 500 **first-time** visitors with
empty caches, so the existing `Cache-Control: public, max-age=2592000` (line 94)
helps nobody yet. A page with ~20 assets produces ~10,000 asset requests, each
occupying a worker. HTML requests then queue behind image transfers.

This is a self-inflicted slowloris, caused entirely by legitimate traffic.

**What is already right:** the 30-day `Cache-Control`, the extension allowlist, the
MIME cross-check, `X-Content-Type-Options: nosniff`, and `attachment` disposition
for PDFs. Keep all of it.

**What is missing:** no `ETag`, no `Last-Modified`, so no conditional requests and
no 304s.

---

## Step 1 — Serve public media without PHP (the main fix)

Hero images, portfolio covers, logos, and icons are **public marketing assets**.
The authorization `MediaController` provides has no value for them — anyone can see
them by loading the homepage. Decision **D16**, assumption **A22**.

- [ ] Classify every asset as **public** or **private**. Applicant CVs (P6) are
      private and must stay behind a controller. Marketing media is public.
- [ ] Write public assets to a web-server-served path and reference them directly,
      bypassing PHP and MySQL entirely.
- [ ] Set long `Cache-Control` plus `immutable` on fingerprinted paths at the web
      server.
- [ ] Keep `MediaController` mounted for private media only.
- [ ] **Do not** reintroduce the legacy mistake: public assets served from a
      directory the web server will execute. Serve them as static files from a
      location with no PHP handler. The legacy RCE came from uploads landing in a
      web-executable directory.

This removes the dominant cost instead of tuning it. Everything below is secondary.

---

## Step 2 — If PHP must stay in the path: hand off to the web server

For private media where authorization is genuinely required, PHP should authorize
and then **delegate the byte transfer**:

- **nginx:** `X-Accel-Redirect` to an `internal` location.
- **Apache:** `X-Sendfile` with `mod_xsendfile`.

The worker is released the moment the header is sent; the web server streams the
file. This keeps the authorization check while removing worker occupancy — the
single most valuable technique in this phase for private files.

- [ ] Implement for `MediaController`'s private path.
- [ ] Mark the internal location so it cannot be requested directly.
- [ ] Verify a worker is genuinely freed: watch PHP-FPM active workers while pulling
      a large file over a throttled connection.

---

## Step 3 — Conditional requests

- [ ] Add `ETag` (use the existing `sha256` column — it is already stored and
      indexed, so no new computation) and `Last-Modified` (from `updated_at`).
- [ ] Handle `If-None-Match` / `If-Modified-Since` and return **304** with no body.
- [ ] Keep `Cache-Control: public, max-age=2592000`; add `immutable` for
      content-addressed paths.
- [ ] Verify with `curl -I` and a second request carrying the returned `ETag`.

`sha256` being present already makes this nearly free — a strong ETag without
hashing on the fly.

---

## Step 4 — Finish the BLOB → disk migration

**Current state:** `media_assets.content` is `LONGBLOB NOT NULL` (from the
2026-10-06 migration, with an explicit `ALTER TABLE … MODIFY content LONGBLOB NOT
NULL`). `storage_path` was added **nullable** on 2026-10-07. `MediaController`
prefers the disk and falls back to the BLOB.

So the project is **mid-migration**, and right now pays both costs: files on disk
*and* every byte still in MySQL — inflating backups, dumps, and replication with
no benefit.

- [ ] Populate `storage_path` for every asset and verify each file exists on the
      `media` disk with a matching `sha256`.
- [ ] Add a verification command (or extend `app:verify-migration`) that asserts:
      every row has `storage_path`, the file exists, and its hash matches.
- [ ] `[!]` **A23 — only after that passes:** make `content` nullable and empty it,
      as a **separate, reversible** migration. Do not combine with the populate
      step; if the populate was wrong, you need the BLOBs still there.
- [ ] Back up the database before emptying. This is irreversible in practice even
      if the migration is reversible in form.
- [ ] Measure database size before and after, and record it.

---

## Step 5 — Canonical asset URLs

From `optimasi.md` §5: the header uses public assets directly while footer and
components serve through the media route, and 27 public files are byte-identical to
private media (~2.81 MiB).

- [ ] Choose **one** canonical URL per logo and icon.
- [ ] **Before moving or deleting anything**, map every path against
      `media_assets` rows, config values, and every view reference.
- [ ] Heed the audit's warning: hashes match across `hero` and `portfolio` folders.
      **Identical bytes at different logical paths may both be referenced.** Do not
      delete on hash-match alone.
- [ ] Update references to the canonical path, then re-run the hash comparison.

---

## Step 6 — CDN (evaluate)

A CDN makes the first-visit problem disappear after the first requester per edge —
which is precisely the launch scenario.

- [ ] Evaluate against hosting (A18). If a CDN is in play, Step 1's web-server
      serving becomes the origin and Step 2 matters much less.
- [ ] Note the CSP interaction: a CDN host must be added to `img-src` in
      `SecurityHeaders`, deliberately, with a reason (P9 covers CSP).

---

## Validation

```bash
curl -I <public asset url>        # expect no PHP, long max-age
curl -I <private asset url>       # expect ETag + Last-Modified
curl -I -H 'If-None-Match: "<etag>"' <private asset url>   # expect 304
```

- [ ] Access logs show public assets served by the web server, not PHP.
- [ ] PHP-FPM active workers under O1's 500-user run are dominated by HTML, not images.
- [ ] Re-run O1's load script and record the delta. This phase should produce the
      largest single improvement in the browse path.

## Acceptance

- [ ] Public images are served **without executing PHP** — verified in access logs.
- [ ] A revalidated private asset returns 304 with no body.
- [ ] `storage_path` is populated and hash-verified for every asset.
- [ ] `content` is emptied only after verification, with a backup, as its own change.
- [ ] One canonical URL per logo/icon, with every reference audited first.
- [ ] Worker occupancy improvement measured against the O1 baseline.

## Why this outranks everything except bcrypt

Caching the homepage payload saves ~12 queries per page view. Taking 20 image
requests per page view out of PHP saves ~20 worker-seconds per visitor on a slow
connection. At 500 concurrent first-time visitors the second number is larger by
orders of magnitude — and it is the one `optimasi.md` did not reach, because it
scoped itself to the landing page's queries rather than its asset delivery.
