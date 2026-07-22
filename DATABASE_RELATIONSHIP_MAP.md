# Database Relationship Map

Status: analysis only. No CI3 or Laravel implementation was changed.

## Production Tables Reviewed

Production archive counts in Laravel `source_data_archives`:

| Source table | Rows |
| --- | ---: |
| application | 20023 |
| schemes | 4 |
| districts | 28 |
| district_union | 32 |
| circles | 6 |
| samiti | 908 |
| phads | 10441 |
| blocks | 150 |
| gram_panchayat | 11655 |
| villages | 20613 |
| cities | 166 |
| wards | 3179 |
| application_files | 121320 |
| application_status | 170141 |
| application_batch | 3356 |
| payment_batch | 87 |
| payment_batch_application | 139496 |
| users | 1064 |
| vle_user | 0 |

## Core Application Relationships

Production CI3 detail query in `Scholarship::detail()` joins:

| Application field | Master table | Master key | Display column |
| --- | --- | --- | --- |
| `application.scheme` | `schemes` | `schemes.id` | `schemes.name` |
| `application.district` | `districts` | `districts.district_code` | `districts.district_name` |
| `application.districtunion` | `district_union` | `district_union.id` | `district_union.union_name` |
| `application.samitiname` | `samiti` | `samiti.id` | `samiti.samiti_name` |
| `application.phadname` | `phads` | `phads.phad_code` | `phads.phad_name` |
| `application.block` | `blocks` | `blocks.block_code` | `blocks.block_name` |
| `application.grampanchayat` | `gram_panchayat` | `gram_panchayat.gp_code` | `gram_panchayat.gp_name` |
| `application.village` | `villages` | `villages.village_code` | `villages.village_name` |
| `application.city` | `cities` | `cities.city_code` | `cities.city_name` |
| `application.ward` | `wards` | `wards.ward_code` | `wards.ward_name` |

Production also joins:

- `district_union.circle_id = circles.id` in export/report queries.

## User Scope Relationships

Production `users` fields:

- `users.user_type` -> `user_type.id`
- `users.district` -> `districts.district_code`
- `users.circle` -> `circles.id`
- `users.districtunion` -> `district_union.id`
- `users.samiti` -> `samiti.id`

Role scope is loaded by:

- `User_model::getuserbyid(USER_ID)`

Permission scope:

- `role_priviledge.role_id = USER_TYPE`
- `role_priviledge.permission_id = priviledge.id`

## Documents

Production document links use `AwsS3upload_model::getmyfile($application_id, $file_type)`.

Lookup:

- table: `application_files`
- filter: `application_id`
- filter: `filetype`
- order: latest `id desc`
- returns latest uploaded file URL/path

Important production file types found in detail/edit views:

- `tpcard`
- `aadharcard`
- `haadharcard`
- `admission_copy`
- `passbook`
- `admission_receipt`
- `head_passbook`
- `phadbookfile`

Laravel must resolve document URLs in service/controller code and pass prepared rows to Blade.

## Workflow Status

Production status trail:

- `application.application_id = application_status.application_id`
- Latest status/verification date is often read from `application_status` ordered by `id desc`.

Production verification data:

- `application_verify.application_id = application.application_id`
- Latest row is used in detail view for collection verification fields.

## Batches and Payments

Application batches:

- `application.batchid = application_batch.batchid`
- `application_batch` groups workflow batches/MoM files/order data.

Payment batches:

- `payment_batch_application.batch_id = payment_batch.id`
- `payment_batch_application.application_number = application.application_id`

Payment status is also tracked directly on application fields:

- `payment_txn_status`
- `paymentstatus`
- `paymentreferenceid`
- `paymentfailreason`
- `otherreason`

## Laravel Relationship Review

Current Laravel model `ScholarshipApplication` has these relationships:

- `academicSession`
- `scheme`
- `district`
- `districtUnion`
- `samiti`
- `phad`
- `applicant`
- `audits`
- `documents`
- `currentDocuments`
- `tendupattaCollections`
- `walletTransactions`

Current Laravel gaps against production relationship map:

- No first-class Eloquent relationships for block, gram panchayat, village, city, ward.
- `district_id` and `phad_id` were normalized by migration, but legacy codes still matter for exact production lookup.
- `ScholarshipViewModelService` currently falls back to `source_data_archives` for block, gram panchayat, village, city, and ward names.
- Circle scope should use a real district union circle relationship/column, not parse description text.
- Document URL resolution must reproduce latest-file-by-application-and-filetype behavior.

## Master Lookup Migration Target

Every displayed master value must come from one of these proper sources:

- Eloquent relationship where the Laravel master table contains a normalized record.
- Repository/service lookup using preserved legacy code columns.
- Archive lookup only as an explicit compatibility source until normalized master tables exist.

Blade must receive display-ready values for:

- Scheme
- District Union
- District
- Block
- Gram Panchayat
- Village
- City
- Ward
- Primary Society/Samiti
- Phad
- Institute
- University
- Course
- Bank
- Branch
- Gender
- Class
- Scholarship Type
- Every uploaded document link

Do not display IDs or codes when a valid production master/name exists.
