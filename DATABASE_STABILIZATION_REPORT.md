# Database Stabilization Report

## 1. Database Issues Found

- Platform menu did not reflect the Welfare Platform architecture. Beema appeared before Masters and scholarship operations were exposed as `Application` instead of a module-level `Scholarship` entry.
- Centralized Masters existed but did not include several common reusable masters already modeled in Laravel: Academic Session, Circle, Block, Gram Panchayat, Village, City, and Ward.
- Reusable master CRUD assumed every master table had `code`, `name`, and `description` columns. This caused invalid validation and SQL risk for `academic_sessions` and geography tables that use `legacy_code` or date columns.
- Geography master models extend `BaseMasterModel`, which uses soft deletes, but `circles`, `blocks`, `gram_panchayats`, `villages`, `cities`, and `wards` did not have `deleted_at`.
- Runtime controllers/services still contained raw SQL expressions for report aggregation, impossible query guards, source archive JSON lookup, and migration verification database-name lookup.
- Master create/update/delete operations did not consistently stamp actor columns when the target master table supported them.

## 2. Root Cause

- The master module was originally implemented around the first set of simple masters, then later production geography/application masters were added with different schemas.
- The navigation still reflected piecemeal migration modules instead of the single platform layout.
- Imported source-system archive tables led to raw JSON SQL in a service layer rather than a Laravel-side lookup path.
- Some migration-created geography masters were added after `BaseMasterModel` was already using `SoftDeletes`.

## 3. Fix Implemented

- Rebuilt menu order to:
  1. Dashboard
  2. Masters
  3. Scholarship
  4. Beema
  5. Reports
  6. User Management
  7. Other Modules
- Kept Masters visible only through `masters.manage`, which is Super Admin only.
- Added registry-driven master field metadata and normalized defaults in `MasterRegistry`.
- Updated master validation, list, detail, create, and edit screens to render fields from registry metadata.
- Updated `MasterRepository` to search and sort only real configured columns.
- Updated `MasterService` to stamp `created_by`, `updated_by`, and `deleted_by` when columns exist.
- Removed runtime raw SQL from `app/`, `routes/`, and `resources/`.

## 4. Relationships Corrected

- Centralized geography master records now participate safely in soft-delete based master CRUD.
- Existing Eloquent relationships for District Union, Samiti, Phad, Block, Gram Panchayat, Village, City, Ward, and Scholarship Application are preserved and used by the centralized master registry.
- No Blade lookup queries were introduced.

## 5. Migrations Created

- `2026_07_22_130000_add_soft_deletes_to_centralized_geography_masters.php`
  - Adds `deleted_at` to `circles`, `blocks`, `gram_panchayats`, `villages`, `cities`, and `wards` when missing.
  - Does not modify existing production migrations.

## 6. Models Updated

- No model relationship signatures were changed.
- The stabilization migration aligns existing geography master models with `BaseMasterModel` soft-delete behavior.

## 7. Queries Optimized

- Master search now uses configured searchable columns instead of always querying `code`, `name`, and `description`.
- Master sorting now validates requested sort columns against the selected table.
- Report status summary no longer uses raw aggregate SQL in the controller.

## 8. Raw SQL Removed

- Removed runtime raw SQL from:
  - `DashboardController`
  - `ScholarshipReportController`
  - `ScholarshipViewModelService`
  - `VerifyLegacyMigration`
- Remaining raw SQL is limited to migrations and import seeders:
  - SQL dump import requires `DB::unprepared`.
  - import schema discovery requires source-table inspection.
  - bulk migration backfill statements are migration-only and intentionally isolated outside runtime application logic.

## 9. Master Module Changes

- Centralized Masters now includes:
  - Academic Session
  - Scheme
  - Course
  - Category
  - Caste
  - Religion
  - District
  - Circle
  - District Union
  - Samiti
  - Phad
  - Block
  - Gram Panchayat
  - Village
  - City
  - Ward
  - Document Type
  - Workflow Status
  - Rejection Reason
  - Notification Template
- Masters remain under one protected module and are not nested under Scholarship or Beema.

## 10. Remaining Issues

- Bank, Branch, University, Institute, Occupation, and Beema-specific masters are not yet present as first-class Laravel master tables/models in this codebase. They should be added from the production schema as dedicated migrations/models before enabling CRUD.
- Import seeders still contain raw SQL by design because they load and normalize the production SQL dump. They are not used by request-time application logic.

## Verification

- `php artisan migrate`
- `./vendor/bin/pint --dirty`
- `php artisan test`
- Result: 30 tests passed, 168 assertions.
