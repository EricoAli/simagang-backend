# Development Execution Plan - Urgent Window

## Target

Get the repository into a state where the 7 October ADM milestone can be
implemented, exercised in Postman, and explained clearly during the upcoming
presentation.

## Phase A - baseline (do first)

1. Fresh clone the GitHub repository.
2. Open the repository root in Antigravity 2.0 as a workspace/project.
3. Copy this `docs/` directory and `AGENTS.md` into the repository root.
4. Do not change source code yet.
5. Ask Antigravity to inspect repository + docs and produce an analysis only.

## Phase B - install and baseline

Typical Windows commands from the repository root:

```powershell
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate:status
php artisan route:list
php artisan test
```

Only run migrations if the local database/environment is configured for this
fresh clone. Do not point the fresh clone at a production database.

## Phase C - branch

Create a dedicated working branch before feature changes. Suggested names:

```text
feature/upgrade-laravel-13
feature/adm-magang
feature/adm-presentasi-dashboard
```

Use the actual team's branch naming convention if one already exists.

## Phase D - framework upgrade

Do the Laravel upgrade in an isolated branch/commit sequence.

1. Analyze dependency constraints.
2. Stage framework upgrade.
3. Run tests.
4. Run route listing.
5. Smoke-test auth.
6. Smoke-test ADM endpoints.
7. Commit only when stable.

## Phase E - ADM Magang

Use the current implementation and 2026 requirements together.

Before editing, inspect:

- `MagangController`
- `Magang` model
- `MahasiswaController`
- `MitraController`
- `DosbingController`
- `DokumenMagangController`
- relevant migrations
- current frontend contract if available

Verify:

- correct CRUD scope
- ownership and authorization
- request validation
- response shape
- not-found behavior
- duplicate/conflict behavior
- file handling where applicable
- no accidental TRPL-only assumptions beyond confirmed rules

## Phase F - Presentasi PI + Dashboard

Inspect:

- `JadwalPresentasiController`
- `JadwalPresentasi` model
- dashboard controller
- related migrations
- current FE expectations

Verify:

- date/time validation
- role restrictions
- CRUD behavior allowed by current scope
- student read access
- dashboard query correctness
- empty dataset behavior
- no misleading hardcoded semester logic

## Phase G - Postman

1. Import historical collection/documentation only as a reference.
2. Build/update the current collection from the actual API.
3. Set `base_url` to local server.
4. Login to obtain tokens.
5. Run the ADM smoke sequence.
6. Run negative/authorization tests.
7. Save examples where they materially improve the documentation.

## Phase H - documentation

Update:

- current API map
- implementation notes
- decisions/conflicts
- test status
- known limitations

## Phase I - presentation evidence

Have these ready:

- repository link
- branch/commit evidence
- current Laravel version
- API route list or summary
- Postman collection
- successful request/response examples
- tests passing
- short before/after explanation for completed ADM features
- known issues / next steps

## Stop conditions

Stop and ask for a human decision when:

- current docs conflict with current business behavior
- a database migration would be destructive
- auth semantics would change
- a new status/state machine is required
- multi-prodi schema requires a new domain decision
- the historical API and current controller contract differ materially
