# Database Analysis

Status: reviewed and implemented as a forward Laravel migration.

## Sources Reviewed

- Production CI3 code: `/var/www/html/scholarship/application`
- Production archive data: `source_data_archives`
- Laravel migrations: `database/migrations`
- Laravel seeders: `database/seeders`
- Existing analysis docs: `DATABASE_RELATIONSHIP_MAP.md`, `DATA_FILTERING_RULES.md`, `ROLE_PERMISSION_MATRIX.md`

The CI3 SQL export at `/var/www/html/scholarship/application/config/export_into_db.sql` contains dump boilerplate only. Production relationships were therefore verified from the imported archive tables and CI3 controller/model queries.

## Production Tables

| Table | Rows | Classification | Purpose | Key relationships verified |
| --- | ---: | --- | --- | --- |
| `agepricematrix` | 10 | Configuration | Age/price matrix from broader welfare app | No scholarship FK use found |
| `application` | 20023 | Transaction | Scholarship applications | scheme, district, district union, samiti, phad, block, panchayat, village/city/ward, status, files, payments |
| `application_backup_20260519` | 20030 | Audit/backup | Production backup | Historical copy |
| `application_batch` | 3356 | Transaction | Workflow batches/MoM/order data | `application.batchid = application_batch.batchid` |
| `application_detail` | 4390 | Transaction/legacy | Legacy detail rows | Not used in current scholarship display path |
| `application_files` | 121320 | Transaction | Uploaded files | `application_files.application_id = application.application_id` |
| `application_status` | 170141 | Audit | Workflow history | `application_status.application_id = application.application_id` |
| `application_verify` | 27362 | Audit | Samiti/collection verification | `application_verify.application_id = application.application_id` |
| `blocks` | 150 | Master | Blocks | `blocks.district_code = districts.district_code` |
| `circles` | 6 | Master | Circle/CCF scope | `district_union.circle_id = circles.id` |
| `cities` | 166 | Master | Urban city | `cities.block_code = blocks.block_code` |
| `district_union` | 32 | Master | District union | `district_union.district_code`, `district_union.circle_id` |
| `districts` | 28 | Master | Districts | `districts.district_code` |
| `gram_panchayat` | 11655 | Master | Gram panchayat | `gram_panchayat.block_code = blocks.block_code` |
| `members` | 1 | Master/transaction | Member data | Visitor gates by permission 36 |
| `payment_batch` | 87 | Transaction | Payment batch | linked through payment batch applications |
| `payment_batch_application` | 139496 | Mapping | Payment batch applications | batch/application mapping |
| `payment_batch_backup_20260518` | 85 | Audit/backup | Payment backup | Historical copy |
| `pg_request` | 21597 | Transaction/audit | CSC payment requests | application/payment reference |
| `pg_response` | 21149 | Transaction/audit | CSC payment responses | application/payment reference |
| `pg_response_clone` | 11909 | Audit/backup | Payment response backup | Historical copy |
| `phads` | 10441 | Master | Phad collection unit | district, district union, samiti, `phad_code` |
| `priviledge` | 32 | Security | Permission catalog | `role_priviledge.permission_id` |
| `relations` | 11 | Master | Relationship catalog | Relation module |
| `role_priviledge` | 59 | Security mapping | Role to permission mapping | `role_id`, `permission_id` |
| `samiti` | 908 | Master | Primary society/samiti | district, district union |
| `schemes` | 4 | Configuration/master | Scholarship scheme | `application.scheme = schemes.id` |
| `update_amount` | 86 | Audit/utility | Amount update data | Utility table |
| `user_type` | 5 | Security | Staff role catalog | `users.user_type` |
| `users` | 1064 | User/security | Staff users | role, district, circle, district union, samiti |
| `villages` | 20613 | Master | Village | `villages.gp_code = gram_panchayat.gp_code` |
| `wards` | 3179 | Master | Urban ward | `wards.city_code = cities.city_code` |

## Current Laravel Tables

| Table | Classification | Finding |
| --- | --- | --- |
| `schemes`, `districts`, `district_unions`, `samitis`, `phads` | Master | Existing, but district union/samiti/phad hierarchy was partly stored in `description`. Fixed with dedicated relationship columns. |
| `scholarship_applications` | Transaction | Existing, but block/panchayat/village/city/ward were code fields. Fixed with normalized FK columns. |
| `scholarship_application_documents` | Transaction | Correct domain replacement for `application_files`; versioning exists. |
| `scholarship_application_audits` | Audit | Correct domain replacement for `application_status`/workflow trail. |
| `scholarship_tendupatta_collections` | Transaction/audit | Correct domain table for collection rows. |
| `scholarship_workflow_batches`, `scholarship_batch_applications` | Transaction/mapping | Correct replacement for workflow batches. |
| `scholarship_wallet_transactions` | Transaction/audit | Correct replacement for payment gateway transaction trail. |
| `user_type`, `priviledge`, `role_priviledge`, `users` | User/security | Preserved for production-equivalent auth behavior. Added normalized user scope IDs. |
| `source_data_archives` | Audit/migration | Preserves raw production records for traceability only; no longer the primary lookup source for geography. |

## Implemented Corrections

- Added normalized geography tables: `circles`, `blocks`, `gram_panchayats`, `villages`, `cities`, `wards`.
- Added explicit legacy columns: `legacy_id`, `legacy_code`, and legacy parent references where required.
- Added relationship columns to `district_unions`, `samitis`, `phads`, `users`, and `scholarship_applications`.
- Backfilled all production geography masters from `source_data_archives`.
- Backfilled application geography IDs from legacy code columns.
- Updated Eloquent models and relationships.
- Updated view model loading to prefer relationships before archive compatibility fallback.

## Validation Counts

- `circles`: 6
- `blocks`: 150
- `gram_panchayats`: 11655
- `villages`: 20613
- `cities`: 166
- `wards`: 3179
- `scholarship_applications.block_id`: 20023 populated
- `scholarship_applications.gram_panchayat_id`: 19914 populated
- `scholarship_applications.village_id`: 19914 populated
- `scholarship_applications.city_id`: 204 populated
- `scholarship_applications.ward_id`: 119 populated
