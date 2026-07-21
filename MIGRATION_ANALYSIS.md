# Scholarship Migration Analysis

## Source Inputs Reviewed

- Legacy archive: `/var/www/html/scholarship.zip`
- Current SQL dump: `/var/www/html/CGMFPFED-Welfare-Platform/scholarship.sql`
- BRD: `/home/hariom/Downloads/Scholarship Document.pdf`
- Existing Laravel application: `/var/www/html/CGMFPFED-Welfare-Platform`

## Legacy Application Inventory

The legacy PHP application contains operational controllers for applications, scholarship workflow, IC/District Union batches, payment processing, failed payment reprocessing, reports, members, relations, schemes, societies, users, dashboard, S3 uploads, and payment gateway request/response audit records.

Important legacy files reviewed:

- `controllers/Scholarship.php`: application list, edit/delete access by role and status, finance forward/remove actions.
- `models/Application_model.php`: role/hierarchy filtering, edit/delete status checks, dashboard counts, legacy amount calculation.
- `helpers/scheme_helper.php`: scheme amount options and amount verification.
- `controllers/Batch.php`: IC/District Union batch movement, order/MoM metadata, status audit.
- `controllers/Payment.php`: payment batch generation, CSV response handling, payment failed/completed statuses.

## Database Inventory

The latest SQL dump contains 33 tables:

`agepricematrix`, `application`, `application_backup_20260519`, `application_batch`, `application_detail`, `application_files`, `application_status`, `application_verify`, `blocks`, `circles`, `cities`, `district_union`, `districts`, `gram_panchayat`, `members`, `payment_batch`, `payment_batch_application`, `payment_batch_backup_20260518`, `paymentfailreasons`, `pg_request`, `pg_response`, `pg_response_clone`, `phads`, `priviledge`, `relations`, `role_priviledge`, `samiti`, `schemes`, `update_amount`, `user_type`, `users`, `villages`, `wards`.

Backup tables are preserved in the legacy import layer but are not first-class Laravel operational tables. The canonical live legacy entities are application, application_detail, application_files, application_status, application_verify, application_batch, payment_batch, payment_batch_application, paymentfailreasons, location masters, users/RBAC, schemes, relations, and member/Sangrahak data.

## Business Rules Applied

The BRD overrides older legacy behavior where they conflict. The Laravel module now enforces:

- Student Aadhaar is the unique student identifier.
- One Student Aadhaar can have only one scholarship application in one Academic Session.
- One student can apply under only one Scheme in one Academic Year.
- Scheme 1 and Scheme 2 are restricted to Class 10 and Class 12.
- Scholarship payment uses the student bank account only.
- Head of family bank details are not captured in Laravel operational tables.
- Account holder name must match the Aadhaar-verified student name.
- One Student Aadhaar maps to one bank account.
- One bank account cannot link to multiple Student Aadhaar numbers, including sibling reuse.
- Percentage is calculated from marks obtained and maximum marks.
- Scholarship Scheme cannot change after final submission.
- Every submit, resubmit, workflow, payment, and batch action writes an audit entry.

## Workflow Mapping

Legacy numeric statuses are retained through `ScholarshipApplicationStatus`:

- VLE submit: `0` Application Received Under Samiti Verification.
- Samiti recommend/return/reject: `4`, `2`, `21`.
- IC recommend/return/reject: `5`, `3`, `22`.
- District Union recommend/return/reject: `11`, `9`, `24`.
- HQ recommend/return/reject: `15`, `13`, `25`.
- Finance final/payment batch: `28`, `99`.
- Payment result: `19` success, `17` failed.

The new operational tables are:

- `scholarship_applications`
- `scholarship_application_documents`
- `scholarship_tendupatta_collections`
- `scholarship_application_audits`
- `scholarship_workflow_batches`
- `scholarship_batch_applications`
- `scholarship_notifications`
- `scholarship_wallet_transactions`

## External Integrations

No live Aadhaar, DigiLocker, or Tendupatta API credentials/endpoints were available. The module defines interfaces and mock implementations:

- `AadhaarServiceInterface` with `MockAadhaarService`
- `DigiLockerServiceInterface` with `MockDigiLockerService`
- `TendupattaServiceInterface` with `MockTendupattaService`

This allows manual data entry and verification today while keeping the code API-ready.

## Wallet Finding

The reviewed SQL dump and legacy source did not expose a dedicated wallet balance/debit table. Legacy payment gateway audit tables are preserved by the legacy importer. The Laravel module adds `scholarship_wallet_transactions` so submission/payment-related wallet events can be recorded without inventing balance behavior that was not present in the source package.

## Assumptions

- Finance return for bank correction is modeled with legacy status `26` and resubmission with status `27`, matching the closest available legacy status numbers while reflecting the BRD correction workflow.
- IC batch MoM is required and stored as a path field; physical file upload storage can be connected to the existing filesystem/S3 conventions later.
- The current repo-root `scholarship.sql` is treated as local migration input and should not be committed because it is a large source artifact.
- CSC Connect is intentionally excluded for this phase; existing internal login remains active.
