# API & Backend Audit - 2026

## 1. Goal
To assess the current state of the SIMAGANG backend API against the 2026 current MVP requirements, identify gaps, schema/API mismatches, and authorization vulnerabilities. This document establishes the foundational roadmap for the backend completion.

## 2. General Findings

### Working APIs
- Basic Authentication (`POST /api/auth/login`, `POST /api/auth/register`, `POST /api/auth/logout`) functions mechanically but lacks comprehensive role scoping.
- Some basic CRUD endpoints (Mitra, Dosbing, Mahasiswa) work for basic data entry but lack isolation.

### Incomplete / Broken / Risky APIs
- **Massive Authorization Vulnerability:** Almost all endpoints use `middleware(['auth.api:sanctum', 'role:admin,mahasiswa,mitra,dosbing'])`. There is NO route-level role restriction for `POST`/`PUT`/`DELETE`. An authenticated `mahasiswa` can delete a `mitra` or modify any `magang` record. Controller methods completely lack ownership checks (e.g. `LogbookController::destroy` allows anyone to delete any logbook).
- **Admin API Crashing:** Methods in `LaporanController` and `MagangController` hardcode `$user->mahasiswa->mahasiswa_id`. If an `admin` or `mitra` calls these endpoints, the application crashes with a null pointer exception.
- **Dashboard Logic:** `DashboardController` forces `$user->mitra` for the mitra dashboard, meaning Admin can't view it. It also hardcodes semesters 6 and 7 in the Mahasiswa dashboard.

### Missing APIs Required by Current MVP
- No dedicated endpoint for Admin to view/monitor specific Mahasiswa logbooks reliably without assuming Mahasiswa context.
- No endpoints to query "Mahasiswa by Prodi" (because Prodi does not exist in the schema).
- No isolated endpoints for partner assessment that ensure a Mitra can ONLY assess students assigned to them.

## 3. Schema & API Mismatches
- **Multi-Prodi Readiness:** The `mahasiswa` table lacks a `prodi` or `prodi_id` column. It is implicitly built for a single study program (TRPL).
- **Logbook Structure:** The `logbook` table defines `magang_id` as `UNIQUE`. This means a magang can only have ONE logbook entry in total, completely defeating the purpose of a logbook.
- **Jadwal Presentasi Disconnected:** The `jadwal_presentasi` table and its controller lack any foreign keys (`magang_id` or `mahasiswa_id`). A schedule can be created, but it cannot be linked to the actual student/internship it belongs to.
- **Laporan Redundancy:** There is a dedicated `laporan` table, but `dokumen_magang` also tracks `doc_laporan_magang` via its enum. This creates dual-source-of-truth conflicts.
- **Pra-KRS Conflict:** `dokumen_magang` enum requires `doc_pra_krs`, and `MagangController::store` enforces it. However, the current requirements explicitly state Pra-KRS is out of scope.
- **Dosen Pembimbing Role:** The MVP documentation states Dosen Pembimbing is master data, not a login role. However, the `users` table `role` enum contains `dosbing`, the middleware references `dosbing`, but the `dosen_pembimbing` table has no `user_id`. (Action: Maintain the Dosbing role/concept in code as UNCERTAIN pending stakeholder clarification, but do not prioritize building Dosbing login features).

## 4. Hardcoded Business Rules & Missing Validation
- `MagangController::store` hardcodes the allowed semesters to `Rule::in([6, 7])`.
- `DashboardController` hardcodes semesters 6 and 7, breaking if a student interns in a different semester (e.g., semester 5 or 8).
- Widespread missing validation for data ownership before `update` or `destroy` actions.

## 5. Missing Tests
- The repository has almost no automated tests. Only two feature tests exist (`ExampleTest`, `MagangStoreTest`). This severely impacts confidence when refactoring.

---

## 6. Implementation Dependency Order

To address these findings without breaking existing flows or writing throwaway code, the backend must be stabilized in the following order:

**A. Multi-prodi / Schema Foundation**
*Must be done first because all other API logic depends on the database structure.*
- Add `prodi` to `mahasiswa`.
- Drop `UNIQUE` constraint on `magang_id` in `logbook`.
- Add `magang_id` to `jadwal_presentasi`.
- Resolve `doc_pra_krs` and `laporan` vs `dokumen_magang` structural conflicts.

**B. Authentication and Authorization/Ownership Fixes**
*Must be done before APIs are considered "working".*
- Implement Policies/Gates for ownership checks (Mahasiswa only edits own data).
- Fix middleware scoping (e.g., Mahasiswa cannot hit Admin endpoints).
- Fix null-pointer crashes when Admin accesses Mahasiswa endpoints.

**C. ADM Mahasiswa / Dosbing / Mitra API Stabilization**
- Ensure master data CRUD is robust, authenticated, and isolated.

**D. ADM Magang**
- Fix the hardcoded semester rules.
- Support Admin monitoring capabilities.

**E. ADM Presentasi PI**
- Implement the fixed schedule-to-magang relationship.

**F. Dashboard**
- Refactor `DashboardController` to use dynamic queries instead of hardcoded semesters.

**G. Supporting APIs and Consistency Fixes**
- Final cleanup of edge cases (e.g., Laporan, Logbook image limits).

**H. Automated Tests**
- Write regression tests for core flows (Auth, Magang, Logbook).

**I. Postman Collection + Environment**
- Provide the verified Postman collection for QA/FE teams.

**J. Final API Documentation**
- Expose confirmed contracts.

*Note: This order aligns with the current WBS dependencies but inserts necessary structural fixes (Schema & Auth) first to prevent building features on a broken foundation.*
