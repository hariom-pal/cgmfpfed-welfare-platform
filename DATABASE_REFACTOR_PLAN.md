# Database Refactor Plan

## Implemented in This Phase

Migration:

- `database/migrations/2026_07_22_120000_normalize_production_geography_relationships.php`

New normalized master tables:

- `circles`
- `blocks`
- `gram_panchayats`
- `villages`
- `cities`
- `wards`

New relationship columns:

- `districts.legacy_code`
- `district_unions.legacy_id`
- `district_unions.district_id`
- `district_unions.circle_id`
- `samitis.legacy_id`
- `samitis.district_id`
- `samitis.district_union_id`
- `phads.legacy_id`
- `phads.legacy_code`
- `phads.district_id`
- `phads.district_union_id`
- `phads.samiti_id`
- `users.circle_master_id`
- `users.district_union_master_id`
- `users.samiti_master_id`
- `scholarship_applications.block_id`
- `scholarship_applications.gram_panchayat_id`
- `scholarship_applications.village_id`
- `scholarship_applications.city_id`
- `scholarship_applications.ward_id`

Laravel code updates:

- Added Eloquent models for the new geography masters.
- Added relationships to District, DistrictUnion, Samiti, Phad, User, and ScholarshipApplication.
- Updated ScholarshipRepository eager loading.
- Updated ScholarshipViewModelService to prefer Eloquent relationships for master names.
- Updated DataScopeService to use `district_unions.circle_id` before compatibility fallback.
- Updated legacy seeders to write normalized relationship columns.

## Problems Fixed

- Removed the need to store district/circle/samiti/phad relationship metadata inside `description`.
- Stopped relying on `source_data_archives` as the primary geography lookup source.
- Added a relational source for block, gram panchayat, village, city, and ward display names.
- Preserved production codes and IDs explicitly through legacy columns.

## Remaining Recommended Work

- Add master management screens/services for the new geography masters if administrators need to maintain them.
- Move bank/branch into a bank master only after production source tables are verified.
- Move institute/university/course to dedicated masters only after CI3 source of truth is identified; current production stores many of these as application text fields.
- Replace remaining archive fallbacks once all migrated records have verified normalized values.
- Add database-level check constraints for enum-like fields where MySQL version supports them.
