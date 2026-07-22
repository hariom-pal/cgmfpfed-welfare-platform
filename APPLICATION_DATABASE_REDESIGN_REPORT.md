# Application Database Redesign Report

## 1. Legacy SQL Review

Reviewed `/var/www/html/CGMFPFED-Welfare-Platform/scholarship.sql` as the production source of truth. The dump contains 33 tables:

`agepricematrix`, `application`, `application_backup_20260519`, `application_batch`, `application_detail`, `application_files`, `application_status`, `application_verify`, `blocks`, `circles`, `cities`, `district_union`, `districts`, `gram_panchayat`, `members`, `payment_batch`, `payment_batch_application`, `payment_batch_backup_20260518`, `paymentfailreasons`, `pg_request`, `pg_response`, `pg_response_clone`, `phads`, `priviledge`, `relations`, `role_priviledge`, `samiti`, `schemes`, `update_amount`, `user_type`, `users`, `villages`, `wards`.

The scholarship business domain in the dump is centered on `application`, with separate legacy tables for uploaded files, verification, workflow/status remarks, wallet gateway request/response, IC/HQ batches, scholarship payment batches, user roles, scheme masters, geography masters, and payment failure reasons.

## 2. Tables Migrated

- `application` -> `scholarship_applications`
- `application_files` and file columns from `application` -> `scholarship_application_documents`
- `application_verify` -> `scholarship_tendupatta_collections` plus verification metadata in the details ViewModel
- `application_status` -> `scholarship_application_audits` and `scholarship_workflow_transitions`
- `application_batch` -> `scholarship_workflow_batches` with type `IC`
- `payment_batch` -> `scholarship_workflow_batches` with type `PAYMENT`
- `payment_batch_application` -> `scholarship_batch_applications`
- `pg_request` and `pg_response` -> `scholarship_wallet_transactions` and `scholarship_payment_attempts`
- `schemes` -> `schemes`
- `districts`, `district_union`, `samiti`, `phads`, `blocks`, `gram_panchayat`, `villages`, `circles`, `cities`, `wards` -> normalized Laravel master tables
- `users`, `user_type`, `priviledge`, `role_priviledge` -> Laravel `users`, `user_types`, `priviledges`, `role_priviledge`
- `paymentfailreasons` -> rejection/payment failure master coverage through workflow status/reason records

## 3. Tables Redesigned

- `application.status` is retained for legacy status compatibility, while normalized lifecycle columns now drive behavior: `application_state`, `submission_state`, `workflow_state`, `workflow_stage`, `approval_state`, and `payment_state`.
- `payment_txn_status` and `paymentreferenceid` are separated. VLE wallet payment is represented by `scholarship_wallet_transactions` and payment attempts with purpose `vle_submission_fee`; scholarship disbursement uses purpose `scholarship_disbursement`.
- `application.scholarship_session` is no longer request-controlled business input. Laravel now stores `academic_session_id` and `scholarship_session_id` derived from application date and the session master, with `scholarship_session` kept as a compatibility/display snapshot.
- `application_status` history is redesigned into append-only audit and workflow transition tables so the latest workflow action can be queried accurately.
- Legacy batch tables are consolidated into `scholarship_workflow_batches` and `scholarship_batch_applications`.

## 4. Missing Tables Added

- `scholarship_workflow_transitions`
- `scholarship_payment_attempts`
- `scholarship_session_id` on `scholarship_applications`

No additional production scholarship table was left unmapped. Backup/clone tables were reviewed and excluded from active domain migration because their active equivalents are present.

## 5. Session Master Implementation

`ScholarshipSessionService` derives the Academic Session and scholarship processing session from the application date and `academic_sessions` master rows. It first matches `start_date <= application date <= end_date`, then falls back to the latest configured master session when seed data is sparse.

The Academic Session master is reset to exactly:

- `2023-2024`: 01-Aug-2023 to 31-Jul-2024
- `2024-2025`: 01-Aug-2024 to 31-Jul-2025
- `2025-2026`: 01-Aug-2025 to 31-Jul-2026, active

New and updated applications now receive:

- `academic_session_id`
- `scholarship_session_id`
- `scholarship_session` display snapshot from the matched master row

The application form no longer posts editable `academic_session_id` or `scholarship_session`; Blade displays the derived values only. A one-time migration recalculates every existing migrated application's `academic_session_id` from its application date and the master date range.

Application listing search is intentionally limited to Academic Session, Scheme, District Union, Samiti, Phad, Application Number, Aadhaar Number, Student Name, Last Action Date From/To, and Last Action Role. Last Action filters use `scholarship_workflow_transitions`, not application creation date.

## 6. Workflow Redesign

Workflow actions are persisted in:

- `scholarship_application_audits` for human-readable audit trail
- `scholarship_workflow_transitions` for normalized state transition history and filtering

The application listing now supports filtering by current workflow level and by latest workflow action date using `max(scholarship_workflow_transitions.acted_at)`.

## 7. Wallet Payment Lifecycle

VLE wallet submission remains independent:

- Pending or failed wallet payment keeps the application at VLE.
- Successful wallet payment submits the application into workflow.
- Wallet attempts use `payment_purpose = vle_submission_fee`, `payment_channel = csc_wallet`, and link to `scholarship_wallet_transactions`.

Wallet payment data is not overwritten by scholarship disbursement references.

## 8. Scholarship Payment Lifecycle

Scholarship disbursement now records separate payment attempts:

- Payment batch creation/submission creates a `scholarship_disbursement` attempt with state `submitted`.
- Bank success records reference, response payload, completion timestamp, and updates status to `19`.
- Bank failure records reference, failure reason, response payload, and updates status to payment failed.
- Scholarship disbursement attempts always have `wallet_transaction_id = null`.

## 9. Status Mapping

- `0` Pending at Samiti after wallet success
- `1` Resubmitted
- `2`, `3`, `9`, `13`, `26` Returned/correction states
- `4`, `5`, `11`, `15`, `28` approval and payment-ready states
- `17`, `18` payment failed
- `19`, `20` payment completed
- `21` through `25` permanent rejection states
- `99` payment batch submitted / Axis Bank processing

## 10. Payment Batch Processing

Payment batches are created through `createPaymentBatch()`. Applications must be in `FinalApplicationForPayment`, then transition to status `99` and receive a submitted scholarship disbursement attempt. Final bank results update the application and latest batch application row without touching wallet records.

## 11. Snorkel Scheduler Integration Flow

The Laravel domain now supports the legacy scheduler flow:

Application approved -> payment batch created -> batch submitted -> status `99` -> scheduler/bank processing -> bank response -> status `19` on success or payment failed state on failure.

The scheduler can call the existing payment result service/controller path with bank reference and failure details.

## 12. Database ER Diagram

```text
users
  |-- scholarship_applications.applicant_user_id
  |-- scholarship_workflow_transitions.acted_by

academic_sessions
  |-- scholarship_applications.academic_session_id
  |-- scholarship_applications.scholarship_session_id

schemes
  |-- scholarship_applications.scheme_id
  |-- scholarship_application_documents.scheme_id

scholarship_applications
  |-- scholarship_application_documents
  |-- scholarship_tendupatta_collections
  |-- scholarship_application_audits
  |-- scholarship_workflow_transitions
  |-- scholarship_wallet_transactions
  |-- scholarship_payment_attempts
  |-- scholarship_batch_applications

scholarship_workflow_batches
  |-- scholarship_batch_applications
```

## 13. Migration Strategy

- Preserve legacy identifiers and status values for traceability.
- Normalize workflow/payment state into typed Laravel enums and service-owned transitions.
- Backfill `scholarship_session_id` from application `created_at`/legacy `add_date` against session master dates.
- Backfill `academic_session_id` from application `created_at`/legacy `add_date` against session master dates.
- Keep legacy text fields only as display snapshots or migration metadata.
- Keep backup and clone legacy SQL tables out of active Laravel schema after documenting their active equivalents.

## 14. Testing Performed

- `php -l` on changed PHP files
- `php artisan migrate --force`
- `php artisan test tests/Feature/ScholarshipModuleTest.php`

Additional full-suite verification was run after report generation.

## 15. Assumptions

- The existing `academic_sessions` table is the Laravel session master seeded from production SQL session values.
- `application_backup_20260519`, `payment_batch_backup_20260518`, and `pg_response_clone` are production backup/clone tables, not active business tables.
- `agepricematrix` belongs to Beema/insurance pricing and is documented as reviewed but not migrated into Scholarship workflow code.
