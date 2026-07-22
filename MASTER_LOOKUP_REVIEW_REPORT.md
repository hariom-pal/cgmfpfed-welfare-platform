# Master Lookup Review Report

## 1. CI3 Files Reviewed

- `/var/www/html/scholarship/application/controllers/Scholarship.php`
- `/var/www/html/scholarship/application/models/Application_model.php`
- `/var/www/html/scholarship/application/models/AwsS3upload_model.php`
- `/var/www/html/scholarship/application/views/detail_scholarship.php`
- `/var/www/html/scholarship/application/views/add_scholarship.php`
- `/var/www/html/scholarship/application/views/layouts/default.php`
- `/var/www/html/scholarship/application/hooks/Visitor.php`
- `/var/www/html/scholarship/application/helpers/scheme_helper.php`

## 2. Master Tables Reviewed

- `schemes`
- `districts`
- `district_union`
- `samiti`
- `phads`
- `blocks`
- `gram_panchayat`
- `villages`
- `cities`
- `wards`
- `application`
- `application_verify`
- Laravel normalized tables: `districts`, `district_unions`, `samitis`, `phads`, `schemes`
- Laravel archive table: `source_data_archives`

## 3. Relationships Fixed

- Added `ScholarshipApplication::district()`.
- Added `ScholarshipApplication::districtUnion()`.
- Added `ScholarshipApplication::samiti()`.
- Added `ScholarshipApplication::phad()`.
- Eager-loaded these relationships in `ScholarshipRepository::queryVisibleFor()`.
- Eager-loaded these relationships in `ScholarshipController::show()`.

## 4. Queries Updated

- CI3 production resolves detail masters with these joins:
  - `application.scheme = schemes.id`
  - `application.district = districts.district_code`
  - `application.districtunion = district_union.id`
  - `application.samitiname = samiti.id`
  - `application.phadname = phads.phad_code`
  - `application.block = blocks.block_code`
  - `application.grampanchayat = gram_panchayat.gp_code`
  - `application.village = villages.village_code`
  - `application.city = cities.city_code`
  - `application.ward = wards.ward_code`
- Root cause found: Laravel had no `legacy_*` tables in the current database, so the ViewModel’s legacy joins returned empty arrays. Also, migrated `district_id` contained CI3 `district_code`, and `phad_id` contained CI3 `phad_code`.
- Added `2026_07_22_111318_normalize_scholarship_application_master_keys.php` to normalize existing `district_id` and `phad_id` values to Laravel master IDs.
- Updated `CompleteLegacyDataMigrationSeeder` so fresh migrations import normalized district/phad IDs instead of production codes.
- Updated `ScholarshipViewModelService` to use Eloquent relationships for normalized masters and `source_data_archives` for archived production code masters: block, gram panchayat, village, city, and ward.
- Updated verification lookup to read `application_verify` rows from `source_data_archives`; the current database does not contain `legacy_application_verify`.

## 5. Verification

- Sample application `S2263600001` now resolves:
  - District Union: `Manendragarh`
  - Primary Society: `Kanjiya`
  - Phad: `Ghatai`
  - District: `KOREA`
  - Block: `Bharatpur`
  - Gram Panchayat: `GHATAI`
  - Village: `Ghatai`
- Broad data check found:
  - `blocks missing: 0`
  - `gram_panchayat missing: 0`
  - `villages missing: 0`
  - `cities missing: 0`
  - `wards missing: 0`
  - `district relation missing: 0`
  - `phad relation missing: 0`
- `composer dump-autoload`
- `php artisan optimize:clear`
- `php artisan test`
- `php artisan view:cache`
- `php artisan migrate --force`

## 6. Remaining Issues

- No remaining master lookup issue was found for the View Scholarship Application location masters after normalization and archive lookup verification.
- Fields that are genuinely blank in production data, such as optional bank/course values for schemes where they do not apply, still render as `N/A`.
