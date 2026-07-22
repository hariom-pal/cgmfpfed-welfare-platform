# Data Filtering Rules

Status: analysis only. No CI3 or Laravel implementation was changed.

## CI3 Files Reviewed

- `/var/www/html/scholarship/application/controllers/Scholarship.php`
- `/var/www/html/scholarship/application/models/Application_model.php`
- `/var/www/html/scholarship/application/controllers/Dashboard.php`
- `/var/www/html/scholarship/application/controllers/Batch.php`
- `/var/www/html/scholarship/application/controllers/Payment.php`
- `/var/www/html/scholarship/application/views/manage_application.php`
- `/var/www/html/scholarship/application/views/statuswise_scholarship1.php`

## Base Application Listing

Current production scholarship listing is `Scholarship::index()`.

Common filters:

- `scheme`: `application.scheme`
- `application_number`: `application.application_id`
- `name`: `application.deceasedame LIKE` in code, despite scholarship fields using student names
- `district`: `application.district`
- `districtunion`: `application.districtunion`
- `samiti`: `application.samitiname`
- `phad`: `application.phadname`
- `fromdate`: `date(application.add_date) >= value`
- `todate`: `date(application.add_date) <= value`
- `days`: `DATEDIFF(NOW(), add_date)` range and status `0,1`
- payment filter: `payment_txn_status = '1'`

Base list joins:

- `application.samitiname = samiti.id`
- `application.districtunion = district_union.id`

Ordering:

- `district_union.union_name asc`

Pagination:

- `Scholarship::index()` uses 500 per page.
- Status-specific lists use 50 per page.

## Role-Based List Scopes

| Role | Production list scope |
| --- | --- |
| VLE | `application.added_by = USER_ID` |
| 1 Super Admin | no geography scope in base list |
| 2 District Union | `application.districtunion = users.districtunion` |
| 3 Samiti | `application.samitiname = users.samiti` |
| 4 Investigation Committee | `application.districtunion = users.districtunion`, except union 5/32 pair |
| 5 Circle | district unions where `district_union.circle_id = users.circle` |

Special union rule:

- If the user's district union is 5 or 32, production treats the visible district union set as `[5, 32]`.

## Detail Access Check

`Application_model::checkaccess()` is the production detail visibility gate.

Rules:

- VLE can access only applications where `application.added_by == USER_ID`.
- Samiti can access only matching `districtunion` and `samitiname`.
- District Union and Investigation Committee can access matching district union, with 5/32 paired.
- Circle can access district unions belonging to the user's circle.
- Super Admin falls through to access.

Laravel must reproduce this in repository/policy/service code before rendering details.

## Pending Status Filters

`Scholarship::pending()` status filters:

| Role | Statuses |
| --- | --- |
| 1 Super Admin | `11,12` using both `application_status.status` and `application.status` |
| VLE | `0,1` |
| 3 Samiti | `0,1` |
| 2 District Union | `5,8` |
| 4 Investigation Committee | `4` |
| 5 Circle | `6` |

Payment filter remains:

- `payment_txn_status = '1'`

## Under Process Status Filters

`Scholarship::underprocess()` builds role-specific scopes and status sets.

Observed production sets include:

- VLE and Samiti: `2,3,4,5,6,8,9,10,11,12,15,16`
- District Union: `0,1,2,3,4,6,9,10,11,12,15,16`
- Investigation Committee and Circle use their geography scope and workflow-stage status sets.
- Super Admin does not apply geography scope.

The function then applies request filters and loads through `Application_model::getApplication()`.

## Completed, Rejected, Failed

Production routes:

- `scholarship/completed`
- `scholarship/rejected`
- `scholarship/failed`

Observed status rules:

- Completed applications use payment-completed statuses `19,20`.
- Failed applications use payment-failed statuses `17,18`.
- Rejected includes permanent and workflow rejection statuses such as `2,3,7,9,10,13,14,21,22,23,24,25,26`.

These lists should be copied from `Scholarship.php` exactly during implementation, not inferred from Laravel enum groupings alone.

## Create Batch Filter

When `createbatch=1`:

- Role 4 sees status `4` and `batchid IS NULL`.
- Role 5 sees status `6` and `batchid IS NULL`.

The view then shows checkboxes for users in roles 4 and 5 when `createbatch` is true.

## Payment Filters

`Payment.php` uses:

- Pending payment applications: status `15,16`
- Completed payment applications: status `19,20`
- Failed payment applications: status `17,18`
- Payment batch state in `PAYMENT_BATCH_ID`

`Scholarship::payment()` uses status `28` for final payment processing.

## Laravel Migration Target

Current Laravel observations:

- `app/Domains/Scholarship/Repositories/ScholarshipRepository::queryVisibleFor()` implements basic visibility.
- It eager loads `academicSession`, `scheme`, `applicant`, `district`, `districtUnion`, `samiti`, and `phad`.
- It does not fully reproduce all CI3 status-specific route filters.
- Circle scope currently infers circle from `district_unions.description`; production uses `district_union.circle_id`.

Required migration target:

- Repository methods per production route: all, pending, underprocess, completed, rejected, failed.
- Each method must apply the exact CI3 role/status/geography/payment filters.
- Detail access must reproduce `Application_model::checkaccess()`.
- List and detail view models must be built before Blade.
- Blade must not perform database lookups for status dates, verification rows, master names, or document URLs.
