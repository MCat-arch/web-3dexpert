# Legacy data migration runbook

The riskiest step in the rewrite, because **the production schema does not match the
SQL dump you have** — see §3.2 items 12–14 of the analysis document. You will not
know the real shape until you export production.

## Before you start

1. **Complete phase 0 of the implementation plan.** The live admin password is
   published in plaintext at `/Kredensial Akses.txt`.
2. **Export production** and restore it locally:
   ```bash
   mysqldump -u USER -p DATABASE > legacy-$(date +%F).sql
   mysql -u root -p -e "CREATE DATABASE legacy_3dexpert CHARACTER SET utf8mb4"
   mysql -u root -p legacy_3dexpert < legacy-$(date +%F).sql
   ```
3. **Never point `LEGACY_DB_*` at the live database.** The command only reads, but
   there is no reason to take the risk.

## Run it

```bash
php artisan migrate:fresh                        # empty destination
php artisan app:migrate-legacy-data --dry-run    # inventory + report, rolls back
php artisan app:migrate-legacy-data              # commit
php artisan app:verify-migration                 # reconcile; non-zero exit = blocker
```

The dry run prints a present/missing/row-count table for all 15 legacy tables first.
**Read that table before committing** — it is how you discover the drift.

## Then import the files

The database migration creates content rows and stores references such as
`hero/<filename>` and `portfolio/<filename>`. It does not import binary media.
After the migration, run:

```bash
php artisan app:import-media-assets
php artisan app:verify-migration
```

The importer scans `storage/app/media`, `public/legacy-assets`, and `public/images`
for JPEG, PNG, WebP, GIF, and PDF files. Files under `storage/app/media` should
retain the `hero/`, `portfolio/`, and `brochures/` subdirectories used by those
references. The import is repeatable: identical path/content pairs are skipped,
and conflicting content at an already-used path stops the import. Source files
are not deleted.

Media is stored as database BLOBs and written in 1 MB chunks to keep PHP memory
usage bounded. Before importing, configure MySQL/MariaDB `max_allowed_packet` to at
least 2 MB. `app:verify-migration` reports published portfolios whose referenced
cover file is missing.

## What it will flag, and why

| Flag | Meaning | What to do |
|---|---|---|
| `needs_review` on a service | Duplicate title, or a price like `'24.0002'` that did not parse | Fix the title/price, then publish. All 4 rows are real services (your decision #4) |
| Portfolios with `legacy_source='desain_rumah'`, unpublished | The older module. May duplicate rows already in `properties` | Compare against the `properties` ones; publish or delete |
| Job vacancies unpublished | 4+ repeated characters — e.g. `'asddddddddddddddMARKETING EXECUTIVE'` | Review; delete the junk, publish the real ones |
| Skipped orphan rows in `mengapa_section` | All-null rows left by legacy deletes, which nulled a column instead of removing the row | Nothing. Confirm the count looks right |
| All users deactivated | The admin password was public | Change each password, then reactivate. Create your own with `app:create-admin` |
| Empty company settings | Contact details were hardcoded in `partials/footer.php`, never in the DB | Fill them in before launch |

## Idempotency

Safe to re-run: rows match on `(legacy_source, legacy_id)` or a natural key and are
updated, not duplicated. One transaction wraps the whole run, so a failure halfway
leaves nothing behind.

`--fresh` truncates the destination tables first. **It will discard edits made in the
admin panel since the last run.**

Two things are deliberately *not* overwritten on re-run, so your editorial choices
survive: `is_featured` (skipped if any portfolio already has it) and any user row
that already exists.

## Verify before cutover

```bash
php artisan app:verify-migration    # must exit 0
php artisan test
```

Then, per tasks 8.4–8.5 of the implementation plan:

```bash
# Every indexed URL must resolve. Pull the list from Search Console, not memory.
php artisan tinker --execute="App\Models\LegacyRedirect::all(['old_path','new_path'])->each(fn(\$r) => print(\"{\$r->old_path} -> {\$r->new_path}\n\"));"
```

Record current Search Console rankings **before** cutover, so you can tell whether
the redirect map worked.
