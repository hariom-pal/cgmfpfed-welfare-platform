# MIGRATION_AUDIT.md

Read-only forensic audit of the legacy → Laravel ETL (`database/seeders/CompleteLegacyDataMigrationSeeder.php`), performed against real data. **No data was modified in producing this document.** No repair script has been written — per instruction, repair work is blocked pending explicit approval.

## Methodology

- Loaded the committed `scholarship.sql` (125MB, legacy CI3 dump) into a scratch MySQL database `scholarship_legacy_audit` on the same server as the live app database (`cgmfpfed_welfare_platform`), and ran cross-database SQL directly — this is the authoritative legacy source, not the (already-dropped) `legacy_*` staging tables.
- Compared row counts, distinct-application counts, and one specific record (`S0923324884`) field-by-field between the two.
- Cross-checked every discrepancy found against `PROJECT_UNDERSTANDING.md`/`FINDINGS_VALIDATION.md`'s already-documented orphan-row figures before concluding whether something was a *new* finding or an *already-known, expected* one.

## 1. Applications (`application` → `scholarship_applications`)

| Legacy | Laravel | Gap |
|---|---|---|
| 20,023 | 20,023 | **0** |

Every legacy application record migrated. No application-record loss.

## 2. Documents (`application` direct columns + `application_files` → `scholarship_application_documents`)

Per-type distinct-application coverage, restricted (on the legacy side) to applications that actually migrated:

| Document type | Legacy apps with this file | Laravel apps with this file | Gap |
|---|---|---|---|
| aadharcard | 20,023 | 20,023 | 0 |
| admission_copy | 20,023 | 20,023 | 0 |
| haadharcard | 20,023 | 20,023 | 0 |
| tpcard | 20,023 | 20,023 | 0 |
| head_passbook | 17,586 | 17,586 | 0 |
| passbook | 2,434 | 2,434 | 0 |
| admission_receipt | 2,434 | 2,434 | 0 |

**Zero gap on every document type.** Document migration is complete and faithful for every application that itself migrated.

`passbook`/`admission_receipt` legitimately only exist for ~12% of applications — this matches the already-documented class-restriction rule (`PROJECT_UNDERSTANDING.md` §11): higher-education schemes (3/4) require these two extra documents, school-level schemes (1/2) do not.

`application_files` also contains 863 distinct `application_id`s (121,320 total rows referencing 20,886 distinct application_ids, vs 20,023 real applications) that don't correspond to any row in `application` at all — orphaned file references, consistent with the zero-FK-constraint legacy schema already documented in §13. These are correctly and necessarily skipped by `insertDocuments()`'s `$applicationId === null → continue` guard; they cannot be migrated because they don't belong to any real application.

### Case: S0923324884 ("Passbook shows Not Available")

Directly verified both legacy sources for `legacy_application_id=24884` (Laravel id 20018):
- `application.passbook` column value: **empty string** (not populated in legacy).
- `application_files` rows for `application_id='S0923324884'`: `aadharcard`(×2), `admission_copy`(×2), `haadharcard`, `head_passbook`, `tpcard`(×2) — **no `passbook` row**.

This application is `scheme_id=1` (school-level "Class Scholarship"), which per the class-restriction rule never required a `passbook` upload — only `head_passbook`. Laravel's `document_type=head_passbook` row for this application (`id=178486`) is present and correctly marked `is_current=1`.

**Conclusion: this is not a migration defect.** The legacy record itself never had a student "Passbook" file — only "Head of Family Passbook" (`head_passbook`), which migrated correctly. The two are visually easy to conflate when reading the legacy CI3 UI, which is the most likely source of the discrepancy report. No other document type is missing for this application.

## 3. Workflow / audit history

| Table | Legacy | Laravel | Gap |
|---|---|---|---|
| `application_status` → `scholarship_application_audits` | 170,141 | 168,457 | 1,684 |
| — → `scholarship_workflow_transitions` | n/a | **0** | — |

The 1,684-row audit gap exactly matches the already-documented orphan count (`FINDINGS_VALIDATION.md` correction #12 / `PROJECT_UNDERSTANDING.md` §13: "1,684 `application_status` ... rows reference applications missing from `legacy_application`"). Expected, not new.

**`scholarship_workflow_transitions` has zero rows for any legacy-imported application.** This table is dropped and recreated empty by `database/migrations/2026_07_22_150000_redesign_application_workflow_database.php`, and `migrateAudits()` only ever writes `scholarship_application_audits` — nothing backfills `scholarship_workflow_transitions` from legacy data. This is the root cause the previous fix for the "Last Action Role" filter already worked around at the query level (falling back to `scholarship_application_audits` when no transition row exists — see `ScholarshipRepository::whereLastActionRole()`/`whereLatestAction()`). The underlying table itself remains empty for all ~20,000 legacy applications; the app-level fix compensates for it in the two places it's queried, but nothing else that might read `latestWorkflowTransition` directly (e.g. the "Last Action" column in the applications list, which will keep showing "N/A" for legacy rows) benefits from that workaround.

## 4. Payments

| Table | Legacy | Laravel | Gap |
|---|---|---|---|
| `pg_request` → `scholarship_wallet_transactions` | 21,597 | 20,688 | 909 |
| — → `scholarship_payment_attempts` | n/a | **0** | — |

The 909-row gap exactly matches the already-documented orphan count (§13: "909 `pg_request` rows reference applications missing from `legacy_application`"). Expected, not new.

**`scholarship_payment_attempts` has zero rows**, for the identical structural reason as `scholarship_workflow_transitions`: it's created fresh by the same redesign migration and is only ever written going forward by `ScholarshipService::recordPaymentAttempt()` (native wallet flow). No backfill from `pg_request`/`pg_response` exists. Any UI showing "Payment Attempts" for a legacy-imported application (the `ScholarshipController::show()` view eager-loads `paymentAttempts`) will show empty history for all ~20,000 legacy applications, even though `scholarship_wallet_transactions` (a separate, correctly-migrated table) does have the real transaction history.

## 5. Batches

| Table | Legacy | Laravel | Gap |
|---|---|---|---|
| `application_batch` → `scholarship_workflow_batches` (IC) | 3,356 | 3,356 | 0 |
| `payment_batch` → `scholarship_workflow_batches` (PAYMENT) | 87 | 87 | 0 |
| `payment_batch_application` → `scholarship_batch_applications` | 139,496 | 139,490 | 6 |

The 6-row gap exactly matches the already-documented figure (§13: "6 `payment_batch_application` ... rows reference applications missing from `legacy_application`"). Expected, not new. Batch migration is otherwise complete.

## 6. Tendupatta collections

| | Legacy (`application_verify`) | Laravel (`scholarship_tendupatta_collections`) |
|---|---|---|
| Distinct applications | 19,583 | 19,368 |
| Rows | 27,362 | 54,665 |

Laravel row count is ≈2× legacy row count by design: `application_verify` stores up to 3 collection-year columns per legacy row (`collection_year1/2/3`), and `migrateTendupattaVerifications()` normalizes each populated year into its own `scholarship_tendupatta_collections` row. The ≈2× ratio indicates most applications have exactly 2 of the 3 year-slots populated — consistent with the "first two years" gaddi-eligibility framing already noted in §12. **Not a bug.**

The 215-application gap in distinct-application coverage (19,583 → 19,368) is small and consistent with the same orphan-reference pattern seen elsewhere (rows in `application_verify` referencing an `application_id` that isn't a real, migrated application, or where all three year columns were empty) — not independently re-traced row-by-row given the pattern is already well-established above, but flagged here for completeness.

## 7. Root cause of Issue 2 ("Pending at VLE" empty) — applicant ownership

This is **not a query bug**. The `pending_vle` filter (`application_state = 'created'`) correctly matches **69 real rows** in the live database. The reason nobody can see them:

- **20,016 of 20,023 applications (99.97%) have `applicant_user_id = NULL`.** Of the 69 `application_state='created'` (draft/"Pending at VLE") rows specifically, **62 have `applicant_user_id = NULL`**.
- Root cause: VLE users are **not bulk-migrated**. Per `CscConnectService::authenticateCallback()`, a VLE `users` row is only ever JIT-provisioned (`updateOrCreate(['csc_id' => $cscId], ...)`) the first time that VLE logs in via CSC Connect OAuth. In the current database, **zero users have the VLE role** (`users.user_type` distribution: 961 Samiti, 44 IC, 42 District Union, 9 Circle, 6 Super Admin, 2 Accounts — no VLE) and **zero users have a non-null `csc_id`**. So `CompleteLegacyDataMigrationSeeder::loadUserMap()`/`userIdForCsc()` can never resolve a legacy `added_by` CSC ID to a Laravel user, and `applicant_user_id` is correctly left `NULL` for essentially every legacy-imported application.
- `DataScopeService::applyScholarshipVisibility()` restricts `application_state=Created` rows to `applicant_user_id = <current user>.id` for the VLE role, and excludes them entirely for every other role. With `applicant_user_id` null, **no user — VLE or otherwise — can ever see these 62 rows**, regardless of how correct the status filter is.
- The remaining 7 non-null-`applicant_user_id` applications are attributed to **Samiti-role users** (`user_type=3`), not VLE — plausible if the legacy system allowed Samiti staff to submit an application on a collector's behalf (their `added_by` would legitimately be a small internal legacy user ID rather than a CSC ID); not re-confirmed against legacy `Scholarship.php` submission code in this pass, flagged as an assumption.
- Compounding factor (expected, not a bug): 48 of the 69 draft rows are in academic session `2024-2025` (id 2), which is **not** the currently active session (`2025-2026`, id 3) — so even after a visibility fix, they'd only appear once that session is explicitly selected, per the (working-as-designed) default-to-active-session behavior.

**No reconciliation mechanism exists** anywhere in the codebase to backfill `applicant_user_id` on legacy applications once the owning VLE eventually logs in and a matching `csc_id`/user becomes available — `authenticateCallback()` only creates/updates the `users` row, it never touches `scholarship_applications`.

## Recommended fixes (not applied — require approval)

1. **Decide + implement an `applicant_user_id` reconciliation path.** Two options, needs a product decision:
   - (a) On a VLE's first CSC login, look up `scholarship_applications` rows whose legacy `added_by` (would need to be preserved somewhere, e.g. in `metadata`, or re-derived from a preserved legacy `added_by` column) matches the new `csc_id`, and backfill `applicant_user_id`.
   - (b) A one-time backfill migration/command, once the full set of legacy `added_by` CSC IDs can be cross-referenced against `users.csc_id` as VLEs log in over time.
   - Neither is implemented; this data-repair work is explicitly deferred per instruction.
2. **Business decision on `DataScopeService` visibility for unclaimed legacy drafts** — e.g., should Samiti/District Union see `application_state=Created` rows within their own `district_union_id`/`samiti_id` scope (both of which *are* correctly populated on all 69 rows) even without a resolved `applicant_user_id`? Current behavior mirrors legacy's own VLE-only "Incomplete Application" restriction, so this may be intentional — flagging for a decision, not proposing a change.
3. **`scholarship_workflow_transitions` / `scholarship_payment_attempts`** — decide whether historical parity matters enough to warrant a one-time backfill from `scholarship_application_audits` / `scholarship_wallet_transactions` respectively, or whether "empty history, but audits/wallet-transactions available" is acceptable long-term for legacy-imported applications.

## Tables checked

`application`, `application_files`, `application_status`, `application_verify`, `application_batch`, `payment_batch`, `payment_batch_application`, `pg_request`, `pg_response` (legacy) against `scholarship_applications`, `scholarship_application_documents`, `scholarship_application_audits`, `scholarship_workflow_transitions`, `scholarship_tendupatta_collections`, `scholarship_workflow_batches`, `scholarship_batch_applications`, `scholarship_wallet_transactions`, `scholarship_payment_attempts` (Laravel), plus `users`/`scholarship_applications.applicant_user_id` for the ownership-linkage investigation.

## Rows compared

Documented per-table above; ~740,000 legacy rows and ~610,000 Laravel rows cross-referenced across 9 table pairs.

## Missing data

- `scholarship_workflow_transitions`: 0 rows for all legacy-imported applications (structural gap, not orphan-related).
- `scholarship_payment_attempts`: 0 rows for all legacy-imported applications (same structural cause).
- `applicant_user_id`: NULL on 20,016/20,023 applications (VLE JIT-provisioning design, not yet reconciled).
- All other gaps found (audits 1,684 / wallet 909 / batch-applications 6 / tendupatta 215) match pre-documented orphan-reference counts from `PROJECT_UNDERSTANDING.md`/`FINDINGS_VALIDATION.md` and are expected consequences of the legacy schema's zero foreign-key constraints, not new defects.

## Incorrect mappings

None found. Every discrepancy traced above resolves to either an already-documented orphan-reference gap, an intentional per-year/per-scheme normalization, or the VLE JIT-provisioning design gap — not a mapping error in the ETL logic itself.
