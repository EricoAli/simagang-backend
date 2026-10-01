# Laravel 11 -> 13 Upgrade Plan

## Goal

Upgrade the existing application safely while preserving existing functionality.

## Official reference

Laravel 13 upgrade guide:
https://laravel.com/framework/docs/13.x/upgrade

The official guide documents dependency updates and framework changes for the
12.x -> 13.x step. For this repository, the actual path is 11.x -> 13.x, so the
upgrade must be treated as a staged upgrade rather than assuming a direct package
edit is enough.

## Upgrade Execution Record (October 2026)

### Environment
- **Previous Version:** Laravel 11.46.0
- **New Version:** Laravel 13.34.0
- **PHP Version:** PHP 8.5.0 (Herd Lite)
- **Composer Version:** 2.8.12

### Dependency Changes
The following constraints were updated in `composer.json` to support Laravel 13:
- `"laravel/framework": "^13.0"`
- `"laravel/tinker": "^3.0"`
- `"laravel/pail": "^1.2"`
- `"phpunit/phpunit": "^12.0"`
- `"nunomaduro/collision": "^8.1"` (Collision v9 does not exist yet; v8.9.x added support for L13)

### Known Environment Caveat & Workaround
**CRITICAL:** The current PHP 8.5 Herd Lite environment is missing the `ext-gd` extension and has a misconfigured `extension_dir` (`C:\php\ext` does not exist). 

This blocked the upgrade of `maatwebsite/excel` to `^4.0`, because its dependency `phpoffice/phpspreadsheet` requires `ext-gd`. Furthermore, the locked older version of `phpspreadsheet` is explicitly restricted to `php < 8.5.0`. 

To complete the framework upgrade successfully in this environment, we had to:
1. Keep `maatwebsite/excel` at `^3.1`.
2. Run Composer with `--ignore-platform-reqs` to bypass the PHP version and GD extension checks for `phpspreadsheet`.

**Before `maatwebsite/excel` can be upgraded or used safely, the PHP environment must be fixed to include `ext-gd`.**

### Compatibility Fixes
- **PHP 8.5 Deprecation:** `PDO::MYSQL_ATTR_SSL_CA` is deprecated in PHP 8.5. Fixed in `config/database.php` by checking for the new `\Pdo\Mysql::ATTR_SSL_CA` constant conditionally.
- **Cache:** Added the Laravel 13 required `serializable_classes` config to `config/cache.php` (defaulted to `false`).
- **Session:** Added the Laravel 13 `serialization` config to `config/session.php` (defaulted to `php` to preserve backwards compatibility).

### Verification & Test Results
- `php artisan about`: Confirmed Laravel 13.34.0 is running.
- `php artisan route:list`: All API routes loaded successfully.
- `php artisan migrate:status`: All migrations ran successfully.
- `php artisan test`: 
  - **Before Upgrade:** 1 passed, 2 deprecated (due to the PDO constant).
  - **After Upgrade:** 3 passed cleanly (0 deprecation warnings).

---

## Next Steps

Only after this framework upgrade is committed and stable:
- Fix the PHP 8.5 environment to include `ext-gd`.
- Continue ADM feature work.
- Address authorization and validation gaps.
- Refactor multi-prodi assumptions in a separate, reviewable change set.
- Note: Do NOT remove the `dosbing` concept or hardcode the system around only 3 roles, as the requirement for Dosen Pembimbing login/account remains uncertain.
