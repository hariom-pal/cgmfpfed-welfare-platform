# Phase 4A Legacy Scholarship Database Migration

## Scope

Phase 4A imports the complete legacy Scholarship SQL dump into Laravel without replacing the existing Repository / Service architecture. The migration keeps the existing application tables for active modules and adds an archival `legacy_*` mirror for every source table, preserving transaction, workflow, audit, reporting, configuration, mapping, and lookup data.

The source dump is read from `LEGACY_SCHOLARSHIP_SQL_PATH`, defaulting to `/home/hariom/Downloads/scholarship.sql`.

## Table Classification

| Legacy table | Category | Laravel target | Count |
| --- | --- | --- | ---: |
| `agepricematrix` | Configuration | `legacy_agepricematrix` | 10 |
| `application` | Transaction | `legacy_application` | 10097 |
| `application_batch` | Workflow | `legacy_application_batch` | 1602 |
| `application_detail` | Transaction | `legacy_application_detail` | 4390 |
| `application_files` | Audit | `legacy_application_files` | 60855 |
| `application_status` | Audit / Workflow | `legacy_application_status` | 79267 |
| `application_verify` | Workflow / Audit | `legacy_application_verify` | 13022 |
| `blocks` | Master | `legacy_blocks` | 150 |
| `circles` | Master | `legacy_circles` | 6 |
| `cities` | Master | `legacy_cities` | 166 |
| `districts` | Master | `legacy_districts`, projected to `districts` | 28 |
| `district_union` | Master | `legacy_district_union`, projected to `district_unions` | 32 |
| `gram_panchayat` | Master | `legacy_gram_panchayat` | 11655 |
| `members` | User hierarchy | `legacy_members` | 1 |
| `paymentfailreasons` | Configuration | `legacy_paymentfailreasons` | 0 |
| `payment_batch` | Transaction | `legacy_payment_batch` | 68 |
| `payment_batch_application` | Mapping / Transaction | `legacy_payment_batch_application` | 118898 |
| `pg_request` | Audit / Transaction | `legacy_pg_request` | 10082 |
| `pg_response` | Audit / Transaction | `legacy_pg_response` | 11002 |
| `pg_response_clone` | Audit / Deprecated backup | `legacy_pg_response_clone` | 11909 |
| `phads` | Master | `legacy_phads`, projected to `phads` | 10432 |
| `priviledge` | User / Permission | `legacy_priviledge`, `priviledge` | 32 |
| `relations` | Master | `legacy_relations` | 11 |
| `role_priviledge` | Mapping / Permission | `legacy_role_priviledge`, `role_priviledge` | 59 |
| `samiti` | Master | `legacy_samiti`, projected to `samitis` | 908 |
| `schemes` | Master / Configuration | `legacy_schemes`, projected to `schemes` | 4 |
| `update_amount` | Audit / Transaction | `legacy_update_amount` | 66 |
| `users` | User | `legacy_users`, `users` | 1034 |
| `user_type` | User / Role | `legacy_user_type`, `user_type` | 5 |
| `villages` | Master | `legacy_villages` | 20613 |
| `wards` | Master | `legacy_wards` | 3179 |

## Active Laravel Master Projection

The following legacy data is projected into the current Laravel master modules so existing screens continue to work with production lookup values:

| Laravel table | Source | Count |
| --- | --- | ---: |
| `schemes` | `legacy_schemes` | 4 |
| `districts` | `legacy_districts` | 28 |
| `district_unions` | `legacy_district_union` | 32 |
| `samitis` | `legacy_samiti` | 908 |
| `phads` | `legacy_phads` | 10432 |
| `academic_sessions` | distinct `legacy_application.scholarship_session` and `first_year_session` | 17 |
| `users` | legacy auth subset from `scholarship.sql` | 1034 |
| `user_type` | legacy roles | 5 |
| `priviledge` | legacy permissions | 32 |
| `role_priviledge` | legacy role-permission mapping | 59 |

Lookup tables without first-class Laravel modules in Phase 4A remain fully preserved in their `legacy_*` mirror tables.

## Business Logic Notes

- Authentication remains compatible with the legacy `User_model::login()` flow: submitted password is SHA-512 hashed in binary mode before `password_verify`.
- Legacy SSO compatibility is preserved through the AES-128-CTR `legacy/checklogin` bridge.
- Role and permission behavior remains based on `user_type`, `priviledge`, and `role_priviledge`.
- The legacy `Visitor::check_permission()` hook informed the Laravel permission middleware and menu visibility.
- Dashboard/profile access remains available to authenticated users, while restricted modules use permission IDs from the legacy hook and menu code.
- Workflow state is stored directly on `application.status`, with movement/history in `application_status`, `application_verify`, batch tables, and payment tables. It has not been redesigned.

## Relationship Preservation

The `legacy_*` mirror keeps the legacy column names, primary keys, nullable behavior, defaults, enum definitions, and dumped indexes from the source DDL. Relationships are preserved by retaining legacy IDs and reference columns:

- `legacy_application` references scheme, district, district union, samiti, phad, block, gram panchayat, village, user, batch, payment, and session values using the original codes/IDs.
- `legacy_application_status`, `legacy_application_verify`, and `legacy_application_files` retain their `application_id` references.
- `legacy_payment_batch_application` retains batch/application/payment mapping data.
- User hierarchy columns are preserved in `users` and `legacy_users`: `user_type`, `district`, `circle`, `districtunion`, and `samiti`.

## Intentionally Not Projected To Active Tables

No legacy table was skipped from import. Some tables were intentionally not projected into active Laravel module tables because Phase 4A does not yet have matching first-class modules for them:

- Geography lookups: `blocks`, `circles`, `cities`, `gram_panchayat`, `villages`, `wards`
- Transaction/workflow/audit data: `application`, `application_status`, `application_verify`, `application_files`, payment gateway tables, batch mappings
- Configuration/support tables: `agepricematrix`, `relations`, `paymentfailreasons`, `update_amount`

These are preserved in `legacy_*` tables until their Laravel modules are implemented.

## Legacy Issues Discovered

- The SQL dump contains malformed UTF-8 byte sequences in some historical text fields. The importer scrubs invalid byte sequences to the Unicode replacement character during import so every row can be loaded and counted.
- `paymentfailreasons` exists and is referenced in source views/controllers, but the dump contains no rows.
- `pg_response_clone` appears to be a backup/deprecated audit table; it is still imported because historical payment data may depend on it.
- The legacy source uses the misspelled names `priviledge` and `role_priviledge`; Laravel keeps those names for compatibility.

## Verification

Run:

```bash
php artisan legacy:verify-migration
```

The verifier compares every SQL dump row count with the corresponding `legacy_*` table count. Current result: all 31 tables match.
