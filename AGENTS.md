# SIMAGANG DTEDI UGM - Agent Instructions

## Mission

Work on the existing SIMAGANG backend and its related project documentation.

The current engineering objective is to complete the assigned ADM backend work,
upgrade the Laravel application from 11.x to 13.x safely, verify the API with
Postman, and keep the implementation aligned with the 2026 project-management
artifacts.

This is an existing production-oriented academic application, not a greenfield
application. Prefer incremental, traceable, and reversible changes.

## Repository

Historical/current baseline repository:

https://github.com/WahyuAgg/pmld_si_magang_be

The repository is an existing Laravel API backend, not a greenfield project.

Do not replace the application with a new Laravel skeleton unless explicitly
requested.

## Current Baseline

The current working copy has been freshly cloned and successfully installed.

Baseline environment:

- Laravel 11.46.0
- PHP 8.3.33
- Composer 2.8.12
- MySQL
- Database: `pmld_si_magang_dev`
- PHP extensions required by the current lock file are available, including GD
  and ZIP
- `composer install` completes successfully
- Database migrations complete successfully
- `php artisan test` currently passes:
  - Tests: 3 passed
  - Assertions: 3

Do not assume that the historical development database is identical to the
fresh development database.

The existing historical/testing database is kept separately and must not be
destructively modified.

## Source-of-truth hierarchy

1. Current 2026 project-management documents under `docs/` are the source of
   truth for intended current requirements and deadlines.

2. The current repository code is the source of truth for current
   implementation behavior.

3. 2025 SRS/ERD/Postman materials are historical reference only.

4. When two sources conflict, identify the conflict explicitly and do not
   silently choose one side.

5. Do not invent requirements that are not supported by the available
   project documents.

## Critical Project Context

- The existing product scope was originally TRPL-focused.
- The current architecture target is multi-prodi support for DTEDI.
- The intended target includes the four DTEDI study programs:
  - TRPL
  - TRIK
  - TRI
  - TRE
- Do not hardcode a single study program where the architecture requires
  multi-prodi support.
- The current MVP documents describe three login roles:
  - Admin/Prodi
  - Mahasiswa
  - Mitra
- Dosen Pembimbing is currently described as master/data used in the process,
  not as a separate login role in the current MVP.
- The historical repository nevertheless contains a `dosbing` role in users
  and route middleware.
- Treat the role discrepancy as an explicit issue to investigate.
- Do not change the role model merely because the historical implementation
  differs from the current documentation.

## Historical Documentation Warning

The 2025 SRS, ERD, API documentation, and Postman documentation may contain
assumptions that are no longer valid.

In particular:

- Do not blindly copy historical API behavior.
- Do not assume the historical ERD is the final database design.
- Do not assume historical Postman documentation represents the latest API.
- Use the current repository implementation when documenting the current API.
- Use current 2026 requirements when deciding intended future behavior.
- Report conflicts instead of silently resolving them.

## Engineering Safety Rules

- Analyze before modifying.
- Prefer small, reversible changes.
- Do not perform broad refactors without first identifying their impact.
- Do not make destructive database changes without an explicit migration plan.
- Do not modify the historical/testing database.
- Do not change authentication/authorization semantics without checking every
  affected endpoint.
- Do not silently broaden or narrow scope.
- Do not delete working endpoints merely because they are not mentioned in a
  newer document.
- Preserve API compatibility where practical unless the current requirement
  explicitly changes it.
- Avoid hardcoded TRPL, semester, academic year, or other context-specific
  values when the intended architecture requires them to be configurable or
  data-driven.
- Do not expose secrets, local `.env` values, tokens, passwords, or production
  credentials.
- Run tests after meaningful changes.
- Verify changed endpoints in Postman.
- Explain significant schema, authentication, authorization, or API changes
  before implementing them.

## Laravel Upgrade Rules

Laravel 11 → 13 is a separate engineering concern from feature development.

When performing the Laravel upgrade:

- Follow the official Laravel upgrade path and compatibility requirements.
- Inspect `composer.json` and `composer.lock` before changing dependencies.
- Do not run a broad `composer update` merely to resolve unrelated issues.
- Upgrade framework/dependencies deliberately and minimally.
- Do not combine a large application refactor with the framework upgrade unless
  explicitly requested.
- Run the existing test suite before and after the upgrade.
- Verify routes, authentication, database access, and API behavior after the
  upgrade.
- Record breaking changes or compatibility issues discovered during the
  upgrade.

## Required Workflow

Explore -> Report -> Plan -> Implement -> Test -> Postman Verify -> Review

### Explore

Inspect the existing code, database schema, routes, middleware, models,
controllers, requests, resources, services, tests, and configuration.

During Explore, do not modify source code.

### Report

Identify:

- current architecture
- existing behavior
- hardcoded assumptions
- authorization risks
- multi-prodi readiness
- API inconsistencies
- conflicts between code and documentation
- missing WBS functionality
- Laravel upgrade risks

Do not implement fixes during this stage.

### Plan

Before substantial implementation, provide:

- files/components affected
- database changes required
- API changes required
- authorization implications
- migration strategy
- testing strategy
- rollback/recovery considerations

### Implement

Make the smallest safe change that satisfies the approved scope.

### Test

Run relevant automated tests and report the exact result.

### Postman Verify

Verify changed API endpoints against the actual running application.

### Review

Inspect the final diff for:

- accidental unrelated changes
- security issues
- authorization mistakes
- hardcoded values
- backwards compatibility problems
- unnecessary dependencies
- missing tests

## Immediate Priority

The urgent WBS milestone is **7 October 2026**:

- Backend ADM Magang
- Backend ADM Presentasi PI + Dashboard

The following ADM tasks are also assigned to Erico and are marked On Going in
the tracker:

- Backend ADM Manajemen Mahasiswa
- Backend ADM Dosen Pembimbing
- Backend ADM Mitra

Do not spend the urgent development window implementing unrelated MHS/MTR
features unless required by the current dependency graph.

## First Task in a New Session

Before modifying source code:

1. Read this `AGENTS.md`.
2. Read all relevant files under `docs/`.
3. Inspect the current repository.
4. Inspect routes, middleware, models, controllers, migrations, tests, and
   authentication.
5. Identify conflicts between current implementation and current requirements.
6. Produce an analysis report.
7. Do not modify source code until the analysis is complete and an
   implementation plan has been established.