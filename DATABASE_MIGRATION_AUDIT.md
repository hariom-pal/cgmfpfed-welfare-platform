# Database Migration Audit

Generated after `php artisan migrate:fresh --seed` and `php artisan legacy:verify-migration`.

## Executive Summary

- Every source row from the Scholarship SQL dump is preserved in `source_data_archives`.
- Total archived source rows: 609,395.
- Temporary import tables remaining in the database: 0.
- Production scholarship workflows now use normalized Laravel tables, not `legacy_*` tables.
- Rows with invalid historical foreign keys were not attached to normalized child tables; they remain preserved in `source_data_archives` with their original payload.

## Final Normalized Counts

| Destination | Rows |
| --- | ---: |
| users | 1,064 |
| schemes | 4 |
| districts | 28 |
| district_unions | 32 |
| samitis | 908 |
| phads | 10,441 |
| academic_sessions | 22 |
| scholarship_applications | 20,023 |
| scholarship_application_documents | 102,546 |
| scholarship_tendupatta_collections | 54,665 |
| scholarship_application_audits | 168,457 |
| scholarship_workflow_batches - IC | 3,356 |
| scholarship_workflow_batches - PAYMENT | 87 |
| scholarship_batch_applications | 139,490 |
| scholarship_wallet_transactions | 20,688 |
| source_data_archives | 609,395 |

## Table Mapping

| Legacy table | New table | Record count (legacy) | Record count (new) | Status | Transformation applied |
| --- | --- | ---: | ---: | --- | --- |
| legacy_agepricematrix | source_data_archives | 10 | 10 | Migrated | Archived row-for-row; no first-class normalized table exists in the Laravel schema. |
| legacy_application | scholarship_applications, source_data_archives | 20,023 | 20,023 | Migrated | Preserved business `application_id` as `application_number`, `id` as `legacy_application_id`, status/stage labels, bank details, location IDs, timestamps, and payment fields. |
| legacy_application_backup_20260519 | source_data_archives | 20,030 | 20,030 | Migrated | Archived row-for-row as historical backup data. |
| legacy_application_batch | scholarship_workflow_batches, source_data_archives | 3,356 | 3,356 | Migrated | Converted to IC workflow batches with original batch number, meeting/order data, MoM file path, creator, dates, totals, and amount summary. |
| legacy_application_detail | source_data_archives | 4,390 | 4,390 | Migrated | Archived row-for-row; details remain available as original source payload. |
| legacy_application_files | scholarship_application_documents, source_data_archives | 121,320 | 102,546 | Migrated | Normalized to the active document table by application and document type; duplicate document-type history is preserved in `source_data_archives`. |
| legacy_application_status | scholarship_application_audits, source_data_archives | 170,141 | 168,457 | Migrated | Converted valid parent-linked rows to audit history; 1,684 orphan status rows had no matching application and are preserved only in `source_data_archives`. |
| legacy_application_verify | scholarship_tendupatta_collections, source_data_archives | 27,362 | 54,665 | Migrated | Expanded up to three yearly collection entries per verification row and linked them to applications. |
| legacy_blocks | source_data_archives | 150 | 150 | Migrated | Archived row-for-row; application block values are preserved in application metadata. |
| legacy_circles | source_data_archives | 6 | 6 | Migrated | Archived row-for-row; circle references are retained in user and location payloads. |
| legacy_cities | source_data_archives | 166 | 166 | Migrated | Archived row-for-row; city values are retained in application metadata. |
| legacy_district_union | district_unions, source_data_archives | 32 | 32 | Migrated | Preserved IDs and names; district/circle codes moved into descriptions. |
| legacy_districts | districts, source_data_archives | 28 | 28 | Migrated | Preserved IDs, district names, and codes. |
| legacy_gram_panchayat | source_data_archives | 11,655 | 11,655 | Migrated | Archived row-for-row; application gram panchayat values are preserved in metadata. |
| legacy_members | source_data_archives | 1 | 1 | Migrated | Archived row-for-row. |
| legacy_payment_batch | scholarship_workflow_batches, source_data_archives | 87 | 87 | Migrated | Converted to payment workflow batches with source totals, status, file name, and source user references. |
| legacy_payment_batch_application | scholarship_batch_applications, source_data_archives | 139,496 | 139,490 | Migrated | Linked payment batches to applications; 6 orphan rows had no valid application and are preserved only in `source_data_archives`. |
| legacy_payment_batch_backup_20260518 | source_data_archives | 85 | 85 | Migrated | Archived row-for-row as historical backup data. |
| legacy_paymentfailreasons | source_data_archives | 0 | 0 | Migrated | Source table was empty; verified as complete. |
| legacy_pg_request | scholarship_wallet_transactions, source_data_archives | 21,597 | 20,688 | Migrated | Converted valid requests to wallet transactions; 909 orphan requests had no matching application and are preserved only in `source_data_archives`. |
| legacy_pg_response | source_data_archives | 21,149 | 21,149 | Migrated | Archived row-for-row as payment gateway response history. |
| legacy_pg_response_clone | source_data_archives | 11,909 | 11,909 | Migrated | Archived row-for-row as cloned payment gateway response history. |
| legacy_phads | phads, source_data_archives | 10,441 | 10,441 | Migrated | Preserved IDs, names, codes, district union and samiti context. |
| legacy_priviledge | priviledge, source_data_archives | 32 | 32 | Migrated | Preserved authorization privileges. |
| legacy_relations | source_data_archives | 11 | 11 | Migrated | Archived row-for-row; relationship text in application data remains in source payloads. |
| legacy_role_priviledge | role_priviledge, source_data_archives | 59 | 59 | Migrated | Preserved role permission assignments; VLE permissions are added by application seed data. |
| legacy_samiti | samitis, source_data_archives | 908 | 908 | Migrated | Preserved IDs, names, district code, district union, timestamps, and active state. |
| legacy_schemes | schemes, source_data_archives | 4 | 4 | Migrated | Preserved IDs, names, status, timestamps, and application type context. |
| legacy_update_amount | source_data_archives | 86 | 86 | Migrated | Archived row-for-row as historical amount update data. |
| legacy_user_type | user_type, source_data_archives | 5 | 5 | Migrated | Preserved source roles; VLE role is added by application seed data. |
| legacy_users | users, source_data_archives | 1,064 | 1,064 | Migrated | Preserved user IDs, credentials, role IDs, location IDs, status, mobile, email, and add dates. |
| legacy_villages | source_data_archives | 20,613 | 20,613 | Migrated | Archived row-for-row; application village values are preserved in metadata. |
| legacy_wards | source_data_archives | 3,179 | 3,179 | Migrated | Archived row-for-row; application ward values are preserved in metadata. |

## Code Audit

- Runtime scholarship controllers, services, reports, dashboards, routes, and views do not query `legacy_*` tables.
- The only remaining source-table references are migration/import tooling:
  - `database/migrations/2026_07_21_000300_create_legacy_scholarship_tables.php`
  - `database/seeders/LegacyScholarshipDatabaseSeeder.php`
  - `database/seeders/LegacyMasterDataSeeder.php`
  - `database/seeders/CompleteLegacyDataMigrationSeeder.php`
  - `app/Support/LegacyScholarshipSql.php`
  - `config/legacy_database.php`
- `php artisan legacy:verify-migration` now verifies archive coverage and asserts that zero temporary import tables remain.

## Historical Data Notes

- Historical data missing from `source_data_archives`: none.
- Historical child rows intentionally not attached to normalized foreign keys:
  - `legacy_application_status`: 1,684 rows reference applications not present in `legacy_application`.
  - `legacy_payment_batch_application`: 6 rows reference applications not present in `legacy_application`.
  - `legacy_pg_request`: 909 rows reference applications not present in `legacy_application`.
- These invalid-FK rows remain queryable in `source_data_archives` with their original columns and values.

## Verification

| Check | Result |
| --- | --- |
| Every source table has a mapped destination | Passed |
| Source SQL row counts match `source_data_archives` | Passed |
| Temporary `legacy_*` tables remaining | 0 |
| Normalized application count matches source applications | Passed |
| Master table counts match source tables | Passed |
| Invalid historical foreign keys preserved without breaking constraints | Passed |
