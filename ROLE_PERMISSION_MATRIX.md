# Role Permission Matrix

Status: analysis only. No CI3 or Laravel implementation was changed.

## Production Tables Reviewed

- `user_type`
- `priviledge`
- `role_priviledge`
- `users`
- `vle_user`

The Laravel archive table `source_data_archives` contains the imported production data.

## Production Roles

| Role key | Production name | Source |
| --- | --- | --- |
| `1` | Super Admin | `user_type` |
| `2` | District Union | `user_type` |
| `3` | Samiti | `user_type` |
| `4` | Investigation Commitee | `user_type` |
| `5` | Circle | `user_type` |
| `VLE` | VLE/CSC operator | literal session value, not in `user_type` |

Important: production `USER_TYPE` is both the role identifier and the key used in `role_priviledge.role_id`. VLE is special because it is a string and is handled by hardcoded Visitor rules.

## Production Permission Records

| ID | Name |
| --- | --- |
| 1 | Add User |
| 2 | Edit User |
| 4 | Add Roles |
| 5 | Verify Application |
| 6 | Assess Verified Application |
| 8 | View Verfied Application |
| 9 | View All Rejected Application |
| 10 | View All Assessed Application |
| 14 | Create/Update/View Camp Type |
| 15 | Add/View Price List |
| 16 | Access Report |
| 20 | Create Assessment Camp |
| 21 | View Assessmemt Camp |
| 22 | Add Camp Location |
| 23 | Edit Camp Location |
| 24 | Delete Camp Location |
| 25 | Edit Assessment Camp |
| 26 | Delete Assessment Camp |
| 27 | Create Distribution Camp |
| 28 | Start Distribution Access |
| 29 | View Distributed Application |
| 30 | View Distribution Camp |
| 31 | Access Dashboard |
| 32 | Export All Applications |
| 33 | Export Verified Applications |
| 34 | Export Statewise Data |
| 35 | Manage Schemes |
| 36 | Manage Members |
| 37 | Manage Relation |
| 38 | Manage Batch |
| 39 | Reports |
| 40 | Society Data |

## Production Role to Permission Mapping

| Role | Permissions |
| --- | --- |
| 1 Super Admin | 1, 2, 4, 5, 6, 8, 9, 10, 14, 15, 16, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 40 |
| 2 District Union | 4, 5, 6, 8, 9, 10, 14, 15, 16, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 38 |
| 3 Samiti | No rows found in `role_priviledge` archive |
| 4 Investigation Commitee | 38 |
| 5 Circle | 38, 39 |
| VLE | Hardcoded in `Visitor.php`; not table-driven |

## Visitor Permission Enforcement

Visitor only checks a subset of permissions directly:

- User creation/editing: 1, 2
- Roles: 4
- Beneficiary verification/status views: 5, 8, 9, 10
- Beneficiary exports: 32, 33
- Statewise export: 34
- Member management: 36

Scholarship controller access is primarily controlled by:

- controller login checks
- VLE Visitor allowlist
- application query filters
- `Application_model::checkaccess()`
- status-specific controller branches

## Menu Permission Sources

Production scholarship layout `views/layouts/default.php` checks permissions directly in the view:

- Permission 38 shows `Batches`.
- `USER_TYPE == 1` shows Payment menu and Samiti Wise Count report.
- VLE sees Add Application and Incomplete Application.

Production generic layout `views/default.php` checks:

- Permission 1 or 2 shows Manage User.
- Permission 35 shows Schemes.
- VLE gets My Applications and Add Application.
- Non-VLE gets All Applications.

## Laravel Migration Target

Laravel should keep permission lookup in a service/middleware layer and expose a prepared navigation view model to Blade. Blade should not query `role_priviledge`.

Current Laravel observations:

- `app/Models/User::hasPermission()` queries `role_priviledge`.
- `app/Http/Middleware/EnsurePermission.php` checks route middleware permissions.
- `config/legacy_permissions.php` groups menu permissions as `masters`, `applications`, `workflow`, `reports`, and `settings`.

Current gaps:

- The Laravel menu groups do not exactly match production `views/layouts/default.php`.
- VLE menu behavior must be hardcoded or modelled explicitly to match CI3.
- Role 3 Samiti has no `role_priviledge` rows, so Laravel permission-only routes can accidentally block Samiti workflows that production allowed through controller/status/data rules.
