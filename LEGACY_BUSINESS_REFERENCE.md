# Legacy Business Reference — Scholarship Module

**Purpose:** The single, permanent, code-verified source of truth for the legacy CI3 Scholarship module's business behaviour, replacing all prior analysis documents. Every future migration/feature decision on the Laravel side should be checked against this document.

**Methodology:** Every claim below is grounded in a direct reading of the legacy source at `/var/www/html/scholarship` (controllers, models, libraries, helpers, views, inline JavaScript, hooks, config) and/or the production data dump at `/var/www/html/CGMFPFED-Welfare-Platform/scholarship.sql`. Nothing here is inferred from naming conventions or assumed from the Laravel side. Where the code could not settle a question, this document says **NOT VERIFIED** explicitly rather than guessing. File:line citations are given wherever practical.

**Scope:** The Scholarship module only — `Scholarship.php`, `Dashboard.php`, `Batch.php`, `Payment.php`, `Report.php`, plus the shared `User.php`/`Society.php`/`Member.php`/`Scheme.php`/`Relation.php` controllers, models, libraries, helpers, views, and hooks that the Scholarship module depends on. The `Applications.php` controller (the separate "Sahayata" death-benefit module, ~225 KB) is explicitly **not** the source of truth for anything in this document and is only referenced where it shares code with the Scholarship module (e.g. duplicated view templates).

**Revision history:** Initial version built from a full pass over every Scholarship-module controller/view/model. A second pass (§0.4, §6.5-§6.6, §16, §17) specifically re-verified the authorization/session layer (`Visitor.php`, hooks, CI3 core, helpers, layout/global JS) end-to-end and found one architecturally significant correction — see §0.4 and §16.4 — plus several new, previously-undocumented findings (dead helper files, the disabled OAuth state check, the shared OAuth callback, Member Management's missing views and access-control gap, the `navigator.onLine` auto-logout rule). §1-§5 (the Scholarship module's own workflow logic) were re-verified against these findings and required no changes.

---

## 0. Missing / Orphaned Files (found during this analysis)

Per the task instruction to stop and document exactly which files are missing rather than infer behaviour from elsewhere:

### 0.1 Referenced by a controller, but the view file does not exist in this checkout
`application/controllers/User.php` calls all three of the following, but none of these files exist under `/var/www/html/scholarship/application/views/`:
- `add_user` — referenced at `User.php:283` (`->build('add_user', $data)`)
- `edit_user` — referenced at `User.php:358` (`->build('edit_user', $data)`)
- `manage_user` — referenced at `User.php:194` (`->build('manage_user', $data)`)

**Added in the Part-1/Part-4 re-verification pass**: the same is true of `application/controllers/Member.php`, whose `add($userid)`/`edit($userid,$id)`/`manage($userid)` methods call `->build('add_member')`, `->build('edit_member', $data)`, and (implied by `manage()`'s redirect target) `manage_member` respectively — **none of `add_member.php`, `edit_member.php`, or `manage_member.php` exist in this checkout** (confirmed: `find application/views -iname "*member*"` returns zero results). Exactly the same missing-views pattern as User Management, on the IC-committee-roster screens. See §16.6 for the full Member Management access-control analysis.

Calling any of `User::add_user()`, `User::edit_user($id)`, or `User::manageuser()` in this checkout would fatal on CodeIgniter's `Template::build()` (missing view). The controller logic itself (validation rules, POST handling, DB writes) is fully intact and was read in full (see §6). Only the presentation layer is missing. The sibling `/var/www/html/beema` checkout has files at these exact same paths (`add_user.php`, `edit_user.php`, `manage_user.php`) with a structurally identical controller (`beema`'s `User.php` has the same method names, same validation-rule shape) — see §0.3 for why this is used as the field-level reference.

### 0.2 Present in the views directory, but never loaded by any controller (dead/orphaned)
Verified by grepping every controller under `application/controllers/` (`Applications.php`, `Batch.php`, `Dashboard.php`, `Failed.php`, `Member.php`, `Payment.php`, `Relation.php`, `Report.php`, `Scheme.php`, `Scholarship.php`, `Society.php`, `User.php`) for each filename, and finding zero references:
- `views/dashboard_data1.php` — a near-duplicate of `dashboard_data.php` (same structure, same `$priv`/`$querystring` setup); appears to be a superseded earlier revision left in place.
- `views/statuswise_scholarship1.php` — even if a future code path loaded it, it opens with `print_r($user); die;` (lines 2-7), so it would halt execution before rendering anything below line 7. Dead twice over: unreferenced, and self-terminating.
- `views/application_report.php` — an unreferenced filter/report form; has a duplicate hidden `status` input (lines 49-50), a copy-paste artifact.
- `views/home.php` — **this file is not part of the Scholarship application at all.** Its content is a "CSC Tele-Law" module page (`$pageTitle = 'CSC Tele-Law'`, line 2) that includes `SimpleXLSX.php` and reads a hardcoded path `/var/www/tele-law/app/View/Layouts/StateWise_DarpanData.xlsx` (line 25) belonging to a completely different, unrelated project. It is a stray file, not a disabled Scholarship feature.

### 0.3 Why `beema`'s User Management views were used as a reference (not guessed)
Every Scholarship view actually used by the live module (`add_scholarship.php`, `edit_scholarship.php`, `detail_scholarship.php`, `verify_application.php`, `statuswise_application.php`, `manage_application.php`, `manage_batch.php`, `view_batch.php`, `payment.php`, `payment_navigate.php`, `upload_csv.php`, `finish_payment.php`, `dashboard.php`, `dashboard_data.php`, `select_scheme_for_add.php`, `scholarship_scheme_detail.php`, `layouts/default.php`) **does exist** in this checkout and was read in full — the missing-view problem is confined strictly to User Management and Member Management (§0.1). Since `User.php`'s controller-side logic (validation rules, `$data` shape, `user_type` query) is fully present and was read directly (see §6), the `beema` views are used only to infer the **HTML field layout** that this exact controller logic was built to render against — not to infer any business rule. Every business rule documented in §6 is sourced from `scholarship`'s own `User.php`, never from `beema`.

### 0.4 Critical correction from this pass: the `role_priviledge` permission hook does not actually block writes

A dedicated re-analysis of `hooks/Visitor.php` together with CI3's own framework source (`system/core/CodeIgniter.php`) found that the `role_priviledge`-based permission checks in `Visitor::check_permission()` — previously characterized in this document (and in the session that produced the original research this document was built from) as an active access-control gate — **do not prevent unauthorized writes in practice**, because of exactly when CI3 invokes the `post_controller` hook. Full detail, evidence, and every affected claim are in **§16.4**. In short: every write-then-respond action in this codebase calls CodeIgniter's `redirect()` helper (`header()` + `exit`) immediately after its database write, and `redirect()`'s `exit` terminates the request *before* control ever returns to the framework code that fires the `post_controller` hook — so `Visitor::check_permission()` never runs at all for a successful write. This corrects §6.5 (User Management) and adds a new finding for Member Management (§16.6) that was not covered by the original research at all. It does **not** affect any claim about the Scholarship module's own status-transition logic (§1-§5), because that module was already independently confirmed to never rely on `Visitor.php` for any of its gates — see §16.4's closing note.

---

## 1. Complete Application Lifecycle

All status codes are the legacy `application.status` values. Full status ladder is in §2.

### 1.1 Creation (VLE)
```
select_scheme_for_add.php (scheme picker, no form)
  -> scholarship_scheme_detail.php (static bilingual scheme descriptions, "Continue" button)
  -> Scholarship::add($schemeId)  [URL: scholarship/add/{scheme}]
       renders add_scholarship.php, a scheme-conditional form (see §6 field inventory... actually see §9)
       hard cutoff: `date("Y-m-d") > '2025-01-28'` kills the request immediately (Scholarship.php:1475-1478)
       on submit: INSERT INTO application (status defaults to '0'), INSERT INTO application_files per uploaded doc,
                  application_id generated as "S{districtunion}{samiti}{seq}" (Scholarship.php:1793)
       redirect -> scholarship/preview/{id}
  -> Scholarship::preview($id)  [Scholarship.php:1826 onward]
       requires status=='0' AND is_previewed=='0', else redirects into detail()
       does NOT change status; sets session['application_id'], writes an application_status audit row with status "0"
       redirect -> scholarship/payment
  -> Scholarship::payment()  [wallet fee gateway]
       builds a Bridge/CSC wallet payment request, INSERT INTO pg_request (transaction_status='0')
       renders payment_navigate.php: an auto-submitting hidden form POSTing to
       https://wallet.csccloud.in/v1/payment/{frac} (payment_navigate.php:3-6), fired by inline JS on page load
  -> Scholarship::success()  [wallet callback]
       on success: UPDATE application SET payment_txn_status='1', status='1'  (both in ONE statement, Scholarship.php:3231-3234)
       on failure/cancel: only pg_request.transaction_status='3' is written; application/status are left
       UNTOUCHED (the code path is present but commented out, Scholarship.php:3279-3282) — the application
       is stranded as a payment_txn_status='0' draft indefinitely unless the VLE retries.
```

### 1.2 Samiti review (status 0/1 -> ...) — `detail_scholarship.php`, gated `USER_TYPE==3 && status in [0,1]` (view line 468)
Form fields: `collection_year[0..2]`, `collection[0..2]`, `tp_card_number[0..2]` (first 2 of 3 required), `phadbookfile` (required upload), `approve` radio (1/0/2), `feedback` (required textarea). Server-side (`Scholarship.php:2219-2314`):
```
approve=1  -> status 4  "Recommended by Samiti"
approve=0  -> status 2  "Rejected by Samiti" (VLE may resubmit)
approve=2  -> status 21 "Permanently Rejected by Samiti" (terminal, VLE may only delete)
```
Blocked server-side if any of the 3 years' `collection` quantity < 500 while approving. Writes an `application_verify` row (tendu-collection detail) plus the status update.

### 1.3 IC review (status 4 -> ...) — batch flow, `manage_application.php` + `verify_application.php`
- `manage_application.php` (with `?createbatch=1`), `USER_TYPE==4`: filters the listing to `application.status==4`, shows a checkbox column + "Proceed to MoM" button, POSTs `checklist[]` to `Scholarship::addbatch()` (`Scholarship.php:2923-2956`), which stamps every selected row's `batchid` and redirects to `verifyscholarship($batchid)`.
- `Scholarship::verifyscholarship($id)` (`Scholarship.php:2958-3122`), gated `in_array(USER_TYPE, ['4','5'])`; for `USER_TYPE==4` the listing is filtered to `status=='4'`. Per row, `verify_application.php`'s `status{i}` radio (1=approve) drives (`Scholarship.php:3004-3014`):
```
approve            -> status 5  "Recommended by IC"
reject             -> status 3  "Rejected by IC" (VLE may resubmit)
permanent reject   -> status 22 "Permanently Rejected by IC" (terminal)
```
Also allows a live per-application amount override via AJAX (`updateamount()`, `verify_application.php:301-334`, POSTs to `scholarship/updatepayment`) and requires a `momfile` upload (form says "Max 2MB", server actually enforces 5MB — `Scholarship.php:2975` — a displayed/enforced mismatch).

### 1.4 Circle/CCF review (status 6 -> ...) — same `verifyscholarship()`, `USER_TYPE==5` branch
- `manage_application.php` (`?createbatch=1`), `USER_TYPE==5`: filters the listing to `application.status==6` ("Appealed by Beneficiary") for batch creation — the same screen and "Create Batch"/"Proceed to MoM" UI as IC, just a different status filter.
- `Scholarship::verifyscholarship()`, `USER_TYPE==5` branch (`Scholarship.php:3004-3014`):
```
approve            -> status 8  "Recommended by CCF"
reject             -> status 7  "Rejected by CCF"
permanent reject   -> status 23 "Permanently Rejected by CCF"
```
**This code path is live and reachable, but has never fired in this production dataset.** Nothing in `Scholarship.php` ever writes `status='6'` (confirmed by a full-file grep — no assignment exists anywhere), so the batch-creation query that Circle's UI depends on (`status==6`) always returns zero rows. Verified against real data: **0 of 20,023 archived legacy application rows** have `status` in `{5,6,7,8,10,12,14,16,18,20,23}` — the entire "via CCF" status family has never been populated. See §4 for the full analysis of why Circle nonetheless has real, working code elsewhere in the app.

### 1.5 District Union — two independent mechanisms

**(a) `Scholarship::detail()`, gated `USER_TYPE==2 && status in [5,8,13]`** (`detail_scholarship.php:648`) — despite the numeral "2" matching the "DU" role elsewhere in the app (dashboard/model scoping consistently treats `USER_TYPE==2` as District Union), this specific form is the step that acts on IC/CCF-recommended applications (status 5/8) or HQ-rejected applications sent back (status 13). Radio `status` (1=Recommended, 0=Not Recommended, 2=Permanently Reject) plus conditional `reasonreject`/`rejectedreason` fields (`Scholarship.php:2317-2351`):
```
status==1, from 5   -> 11 "Recommended by DU"
status==1, from 8   -> 12 "Recommended by DU via CCF"
status==0 (else)    -> 9  "Rejected by DU" (from 5) / 10 "Rejected by DU via CCF" (from 8)
status==2            -> 24 "Permanently Rejected by DU" (terminal)
```

**(b) `Batch::view($id)` POST handler** (`Batch.php:175-211`), `USER_TYPE==2` only — a second, independently coded batch-approve action on rows grouped by `batchid` (this is a *different* batch mechanism from IC/Circle's `addbatch()`/`verifyscholarship()` — `Batch.php` has no method that writes status 5/6/7/8):
```php
$datastatus['status'] = '11';
$dataapp['status']='11';
if( $app_detail['0']['status']=='8'){ $datastatus['status']='12'; $dataapp['status']='12'; }
```
**Confirmed bug**: `$app_detail` is looked up via a typo'd variable `$applicaiton` (not `$application`, `Batch.php:192`) that is never assigned within the loop, so `$app_detail` is always empty and the `=='8'` branch never fires — this path can only ever write status **11**, never 12, regardless of the row's actual prior status.

### 1.6 Super Admin / "HQ" review (status 11/12 -> ...) — `detail_scholarship.php:594`, `USER_TYPE==1 && status in [11,12,15,16]`
Two sub-forms in the same gate:
- Status 11/12: radio `status` (1=Recommended for Payment [default], 0=Reject, 2=Permanently Reject) + `adminrejectreasonother` textarea (`Scholarship.php:2376-2402`):
```
status==0  -> 13 "Rejected by HQ" (from 11) / 14 "Rejected by HQ via CCF" (from 12)
status==2  -> 25 "Permanently Rejected by HQ" (terminal)
else       -> 15 "Recommended for Payment" (from 11) / 16 "Recommended for Payment via CCF" (from 12)
```
- Status 15/16: `paymentstatus` select (Completed/Failed, required) + conditional `paymentreferenceid`/`paymentfailreason`/`otherreason` (`Scholarship.php:2415-2551`):
```
paymentstatus in (Failed, Reject)  -> 17 "Payment Fail" (from 15) / 18 (from 16)
paymentstatus == Reject specifically -> 26 "Permanently Rejected by Accounts" (terminal)
paymentstatus == Completed         -> 19 "Payment Done" (from 15) / 20 (from 16)
```
Also writes `application.paymentstatus`/`paymentfailreason`/`otherreason`/`paymentreferenceid`.

### 1.7 Bulk "recommend for payment" (Super Admin only) — `statuswise_application.php`, `USER_TYPE==1 && status filter =='11,12'`
Checkbox bulk-select + "Approve Selected" button, POSTs `checklist[]` back to `Scholarship::pending()` (`Scholarship.php:297-326`):
```php
foreach($ids as $application){
   $datastatus["status"] = "15";
   $data2["status"] = "15";
   ...
   redirect(...);   // <-- inside the loop
}
```
**Two confirmed bugs**: (1) every selected application is forced to status **15** unconditionally, even ones that came from a status-12 ("via CCF") lineage that should go to 16 — collapsing the distinction the single-record path (§1.6) correctly preserves; (2) the `redirect()` call sits *inside* the `foreach` loop, so PHP stops execution after the first iteration — only the **first** checked application in a multi-select batch is ever actually committed.

### 1.8 Account role (status 15/16/28 -> ...) — `Scholarship::remove()`/`forward()`
```
forward()  [gated USER_TYPE=='6', Scholarship.php:85]   15 or 16  -> 28  "Final Application for Payment"
remove()   [gated USER_TYPE=='6', Scholarship.php:63]    28        -> 15  "Removed from Available for Payment" (undo)
```
Entry points are inline conditional links/buttons inside `statuswise_application.php`, not a menu item — see §5/§8.

### 1.9 System payment-file pipeline (status 28 -> 99) — `Payment.php`, no `USER_TYPE` gate at all
```
Payment::proceedpayment($scheme)   selects status==28 rows for the scheme, builds payment_batch +
                                    payment_batch_application rows, renders finish_payment.php
                                    (a confirmation screen, BEFORE any file is written)
     -> user clicks "Finish Payment" (confirm() dialog only, no server validation of the confirm)
Payment::finishpayment()           writes a fixed-width AXIS-bank payment-instruction .txt file,
                                    sets payment_batch.status='1', and:
                                    UPDATE application SET status='99' WHERE application_id IN (...)
                                    NO application_status audit row is written for this transition —
                                    the only status-write in the entire app that skips the audit table.
```
`application_status.status` is declared `enum('0'..'28')` in the schema (verified: `scholarship.sql:395`, exact 29-value enum, no `'99'`) — writing `'99'` into that table would violate the enum, which is almost certainly *why* `finishpayment()` skips the audit insert for this one transition. `application.status` itself is a plain `varchar(255)` (`scholarship.sql:89`), so it can hold `'99'` without any DB-level issue.

### 1.10 Post-99 reconciliation — `Payment::uploadCsv()`, no `USER_TYPE` gate at all
Bulk UTR-reconciliation: admin uploads a CSV (`application_id, status_text, reason` columns) via `upload_csv.php`; per matched row (`Payment.php:90-114`):
```
status_text in (Failed, FAILED)  -> 17 (or 18 if current status=='16')
else (Completed)                 -> 19 (or 20 if current status=='16')
```
Because `finishpayment()` has already overwritten the row's status to `99` by this point, the `current status=='16'` check can never match — **the via-CCF lineage is silently lost** for any application that passed through the automated bank-file pipeline; `uploadCsv()` will always write the base variant (17/19), never 18/20, regardless of the application's true origin.

### 1.11 VLE resubmission and deletion
- `Scholarship::edit($id)` (VLE only, `Scholarship.php:2560` onward): **unconditionally** sets `status='1'` regardless of the prior status (no FROM-status check in code) — any non-permanent reject (2, 3, 9, 10, 13, and their +CCF counterparts) can be resubmitted this way.
- `Scholarship::delete($appid)` (VLE only, `Scholarship.php:53-55`): allowed only if current status is in `('0','1','21','22','23','24','25','26')` — i.e. an untouched draft, or a permanent reject.

### Terminal states
`19`/`20` (Payment Completed, success), `21`/`22`/`23`/`24`/`25`/`26` (permanent rejects — Samiti/IC/CCF/DU/HQ/Accounts). All six permanent-reject codes are VLE-delete-only, never resubmittable.

---

## 2. Complete Status Matrix

Source: the `$statusarr` array defined identically (modulo the wording note below) in `detail_scholarship.php:542`, `statuswise_application.php:228`, and `manage_application.php:227`, cross-checked against every status-writing line cited in §1. The array has exactly 27 entries (indices 0-26); statuses 27, 28, 99 are **not** covered by this array in any of the three views — if a row's status were 27, 28, or 99, indexing into `$statusarr` would throw a PHP undefined-offset notice in all three of these views.

| Code | Label (as coded) | Meaning | Responsible role | Typical next | Typical previous | Terminal | Source |
|---|---|---|---|---|---|---|---|
| 0 | Pending / "Pending at Samiti " (manage_application.php's copy differs in wording) | New / fee unpaid | Samiti | 1, 2, 4, 21 | (created) | No | Scholarship.php:1750 |
| 1 | Resubmitted by VLE | Fee paid or resubmitted | Samiti | 2, 4, 21 | 0, or any reject via edit() | No | Scholarship.php:3234, 2840 |
| 2 | Not Recommended by Samiti | Returned to VLE | VLE (resubmit) | 1 | 0/1 | No | Scholarship.php:2281 |
| 3 | Not Recommended by IC | Returned to VLE | VLE (resubmit) | 1 | 4 | No | Scholarship.php:3011 |
| 4 | Recommended by Samiti | Awaiting IC | IC | 3, 5, 22 | 0/1 | No | Scholarship.php:2277 |
| 5 | Recommended by IC | Awaiting DU | District Union | 9, 11, 24 | 4 | No | Scholarship.php:3008 |
| 6 | Appealed by Beneficiary | **Never written by this module** — see §4 | Circle (unreachable in practice) | 7, 8, 23 | never set | No | n/a |
| 7 | Rejected By CCF | Rejected at Circle stage | VLE (unreachable — no batch ever reaches this branch) | 6 | 6 | No | Scholarship.php:3012 |
| 8 | Recommended by CCF | Awaiting DU (CCF lineage) | District Union (unreachable input) | 10, 12, 24 | 6 | No | Scholarship.php:3009 |
| 9 | Not Recommended By DU | Returned to VLE | VLE (resubmit) | 1 | 5 | No | Scholarship.php:2340-2348 |
| 10 | Not Recommended By DU (CCF variant) | Returned to VLE | VLE (resubmit) | 1 | 8 (unreachable) | No | Scholarship.php:2340-2348 |
| 11 | Approved By DU | Awaiting HQ | Super Admin | 13, 15, 25 | 5 | No | Scholarship.php:2330, Batch.php:198 |
| 12 | Approved By DU (CCF variant) | Awaiting HQ | Super Admin | 14, 16, 25 | 8 (unreachable) | No | Scholarship.php:2330 (Batch.php's copy can never write this — §1.5 bug) |
| 13 | Rejected By HQ | Returned to VLE | VLE (resubmit) | 1 | 11 | No | Scholarship.php:2380-2390 |
| 14 | Rejected By HQ (CCF variant) | Returned to VLE | VLE (resubmit) | 1 | 12 (unreachable) | No | Scholarship.php:2380-2390 |
| 15 | Recommended For Payment | Awaiting Account | Super Admin / Account | 17, 19, 26, 28 | 11 | No | Scholarship.php:2398-2402 |
| 16 | Recommended For Payment (CCF variant) | Awaiting Account | Super Admin / Account | 18, 20, 26, 28 | 12 (unreachable) | No | Scholarship.php:2398-2402 |
| 17 | Payment Failed | Needs re-processing | Super Admin / Account | 15 (manual) | 15 | No | Scholarship.php:2463-2519, Payment.php:90-114 |
| 18 | Payment Failed (CCF variant) | Needs re-processing | Super Admin | 16 | 16 | No | Scholarship.php:2463-2519 |
| 19 | Payment Completed | Success | — | — | 15/17 | **Yes** | Scholarship.php:2463-2519, Payment.php:90-114 |
| 20 | Payment Completed (CCF variant) | Success | — | — | 16/18 | **Yes** | Scholarship.php:2463-2519 |
| 21 | Permanent Rejected By Samiti | Closed | VLE (delete only) | — | 0/1 | **Yes** | Scholarship.php:2281 |
| 22 | Permanent Rejected By IC | Closed | VLE (delete only) | — | 4 | **Yes** | Scholarship.php:3011 |
| 23 | Permanent Rejected By CCF | Closed, unreachable | VLE (delete only) | — | 6 (unreachable) | **Yes** | Scholarship.php:3012 |
| 24 | Permanent Rejected By DU | Closed | VLE (delete only) | — | 5/8 | **Yes** | Scholarship.php:2340-2348 |
| 25 | Permanent Rejected By HQ | Closed | VLE (delete only) | — | 11/12 | **Yes** | Scholarship.php:2390 |
| 26 | Permanent Rejected By Accounts | Closed | VLE (delete only) | — | 15/16 | **Yes** | Scholarship.php:2463-2519 |
| 27 | (no label in `$statusarr`; `Scholarship.php:1402`'s separate export-array calls it "Account Detail updated by HQ") | HQ updates bank details on an Accounts-stage reject | Super Admin | NOT VERIFIED — no write path found in this analysis | 26 | No | Scholarship.php:1402 (label only; no assignment site found) |
| 28 | Final Application for Payment | Queued for bank file | Account / system | 15 (via remove()), 99 (via batch pipeline) | 15/16 (via forward()) | No | Scholarship.php:85 |
| 99 | (no label anywhere in the app's `$statusarr` copies; not in the `application_status` DB enum) | Sent to AXIS bank payment file | — | 17/18/19/20 via uploadCsv (lineage often lost, §1.10) | 28 | Effectively yes — no further code path advances it cleanly | Payment.php:287-288 |

**Wording inconsistency, confirmed by direct comparison**: index 1 of the `$statusarr` array reads `'Resubmitted by VLE'` in `detail_scholarship.php` and `statuswise_application.php`, but `'Pending at Samiti '` (note trailing space) in `manage_application.php` — three independently maintained copies of the same lookup array have drifted.

**Status 27's write path is NOT VERIFIED.** It is referenced only as a label in `Scholarship.php:1402`'s separate CSV-export status array, and does not appear in the `$statusarr` (§ above) at all. No `$data['status']='27'` or `$datastatus['status']='27'` assignment was found anywhere in `Scholarship.php` during this analysis.

---

## 3. Complete Role Hierarchy

| Role | Session `USER_TYPE` value | Real `user_type` rows in `user_type` lookup table | Real users in production dump | Menu label (from `User.php:911`'s `$userstype` array) |
|---|---|---|---|---|
| Super Admin / "HQ" | `1` | Yes | 6 | (index 0/1, blank entries in the array — array is `['', '', 'District Union', 'Samiti', 'Investigation Committee', 'Circle']`, so index 1 is an empty string, NOT VERIFIED what renders for Super Admin in this specific export label) |
| District Union | `2` | Yes | 42 | `'District Union'` |
| Samiti | `3` | Yes | 961 | `'Samiti'` |
| Investigation Committee (IC) | `4` | Yes | 44 | `'Investigation Committee'` |
| Circle / "CCF" | `5` | Yes | 9 | `'Circle'` |
| Account | `6` | **No** — confirmed zero rows for `role_id=6` in `role_priviledge`; the `user_type` lookup table's own inline SQL comment (`scholarship.sql:1189`) only documents `'1=Admin, 2=District Union, 3=Samiti'`, not 4/5/6 | 2 (`id=5` "Hero", `id=1028` "Ajish A. Panikar") | **Undefined** — `$userstype` array (`User.php:911`) has only 6 elements (indices 0-5); indexing with `user_type==6` is an out-of-range access |
| VLE | literal string `'VLE'`, not a `user_type` row at all | n/a | n/a (self-service, CSC/OAuth-provisioned) | n/a |

"HQ" is confirmed to be purely a workflow-stage label applied to Super Admin's actions on statuses 11/12/15/16 (§1.6) — there is no separate `user_type` row or session value for "HQ"; every code reference is `USER_TYPE=='1'`.

### 3.1 Responsibilities (each backed by a §1 citation)
- **VLE**: creates applications (§1.1), resubmits after non-permanent reject (§1.11), deletes drafts/permanent-rejects (§1.11), pays the wallet fee.
- **Samiti (3)**: first-line review of collection data, recommend/reject/permanent-reject (§1.2). Row visibility restricted to its own `samiti` (`Application_model::checkaccess()`, `Application_model.php:274-278`).
- **IC (4)**: batches Samiti-recommended applications and verifies them with a MoM upload (§1.3). Row visibility restricted to its own `districtunion`, with the 5/32 merge rule (`Application_model.php:279-294`).
- **Circle (5)**: code-complete counterpart to IC for the "Appealed" branch (§1.4), but the branch has never fired in production (§4). Also has independent, functioning read-scoped visibility (`Application_model::checkaccess()` circle→district-union resolution, `Application_model.php:295-305`) used by the dashboard/pending-count/batch-list screens.
- **District Union (2)**: two independent approve mechanisms — the per-application `detail()` step (§1.5a) and the separate `Batch::view()` bulk-approve (§1.5b, with a confirmed bug).
- **Super Admin (1) / "HQ"**: final review of DU-recommended applications, manual payment-result entry, the (buggy) bulk-approve shortcut, and is the only role with unrestricted row visibility (no scoping branch in `checkaccess()` for `USER_TYPE==1`, meaning it falls through to `return true`, `Application_model.php:306`).
- **Account (6)**: `remove()`/`forward()` only (§1.8). No row-level scoping exists for it anywhere in `Application_model.php` (no `USER_TYPE==6` branch in `checkaccess()`, `getcountfordashboard()`, or `getcountforpending()` — confirmed by full-method reads).
- **(system, no role)**: the AXIS bank-file pipeline (§1.9) and CSV reconciliation (§1.10), both entirely ungated by `USER_TYPE`.

---

## 4. Verify Circle Role

**Direct question asked: is Circle (User Type 5) actually used, and if so where? If not, why does it still exist?**

**Answer, fully code-grounded: Circle is genuinely used — it is not dead code — but its one substantive workflow action has never actually executed in production because its trigger condition (status 6) is never created.**

Every reference found, by category:

**Row-level access control (live, functioning):**
- `Application_model::checkaccess()`, `Application_model.php:295-305` — resolves the user's `circle` id via `User_model::getdistrictuniondatabycircle()`, then restricts visibility to applications whose `districtunion` falls under that circle. This runs for every application detail-view request from a Circle user.
- `Application_model::getcountfordashboard()`, `Application_model.php:347-354` — same circle→district-union resolution, scoping dashboard counts.
- `Application_model::getcountforpending()`, `Application_model.php:382-389` — same resolution, scoping the "your pending work" badge.
- `Dashboard.php:90, 160, 185` — the same scoping pattern repeated independently in the dashboard controller and its CSV export.
- `Batch.php:52` — `index()`'s listing filter for Circle users (visibility only — `Batch.php` has no method that writes a Circle-relevant status; confirmed by reading the full 273-line file, whose only three methods are `index()`, `view()` [DU-only approve], `export()` [Super-Admin-only CSV]).

**The one substantive workflow action (code-complete, never triggered in production):**
- `manage_application.php:180,203,280,325` — `USER_TYPE==5` (alongside IC/4) sees a "Create Batch" checkbox UI and "Proceed to MoM" button. For Circle specifically, the underlying listing query filters to `application.status==6` (per `Scholarship::index()`'s createbatch branch).
- `Scholarship::verifyscholarship($id)`, `Scholarship.php:2958-3122`, gated `in_array(USER_TYPE, ['4','5'])`; the `USER_TYPE==5` branch (`Scholarship.php:3004-3014`) writes status `8` (recommend), `7` (reject), or `23` (permanent reject) — all sourced from a batch whose pool was filtered to `status==6`.
- **Nothing in `Scholarship.php` ever writes `status='6'`** — confirmed by a full-file grep for any assignment to that value. The only place in the entire codebase that sets `status=6` is the unrelated Sahayata module (`Applications.php:3282-3284`, a VLE-triggered "appeal" action for death-benefit claims, not scholarships).
- **Verified against real data**: 0 of 20,023 archived legacy `application` rows have `status` in `{5,6,7,8,10,12,14,16,18,20,23}` — i.e. not one application, ever, has been at any status in the "via CCF" family, current or historical-in-this-snapshot (caveat: `application.status` is a current-value column, not a full history, so this shows "never currently sitting there," which for `status==6` specifically is reinforced by the code-level fact that nothing writes it at all).

**Additional standing (real, but functionally inert given the above):**
- `role_priviledge` grants role 5 exactly two permissions (`scholarship.sql:1062`): `38` ("Manage Batch") and `39` ("Reports"). Permission `38` is consumed by the "Batches" menu-visibility check (`layouts/default.php:214`). **Permission `39` ("Reports") is granted to Circle but is never checked anywhere in the codebase** — confirmed by grepping the full app for any `in_array('39', ...)` / `checkUserAccess(39)` reference; zero hits. It is an orphaned grant.
- `view_batch.php` (the DU-approve detail screen) has **zero** `USER_TYPE==5` conditionals anywhere (confirmed by full-file grep) — every interactive control (checkbox, approve/reject, editable order-id/verification-date) is gated to `'2'` (DU) or `'4'` (IC) only. A Circle user landing on this page sees a read-only table with no working action at all — a dead end.
- `detail_scholarship.php` (the per-application HQ/DU/Samiti review screen) also has **zero** `USER_TYPE==5` conditionals (confirmed by full-file grep of all 4 `USER_TYPE` occurrences in that 781-line file) — Circle has no per-application decision screen, only the batch path.
- 9 real users carry `user_type=5` in production, including one literally named `CCF_Jagdalpur` (`id=7`), directly corroborating that Circle's colloquial name throughout the codebase's status labels and dashboard column headers ("CCF") is a real, intentional naming convention, not a documentation artifact.
- Circle's menu presence is identical to every other non-VLE, non-Super-Admin role: no dedicated "Circle" or "CCF" sidebar section exists in `layouts/default.php` (confirmed: only `USER_TYPE=='VLE'`, `USER_TYPE=='1'`, and the permission-38 "Batches" gate exist in that file — zero `USER_TYPE=='5'` checks).

**Recommendation on migration**: Circle's row-scoping/visibility logic (dashboard counts, application-list filtering) is real and should be preserved if the business still wants Circle users to have read/oversight access to their district unions' applications. Its batch-verification action (§1.4) should **not** be migrated as a working feature without a business decision first — there is currently no way for a `status==6` application to ever exist, so the feature has no way to be exercised even if faithfully ported. This is a documentation-only observation; no code changes were made.

---

## 5. Verify Account Role

**Direct question asked: why does Account exist, how does it receive/process applications, what screens/controllers/views/reports/menus/documents does it use?**

**Answer, fully code-grounded: Account (role 6) is a real, narrowly-scoped, working role — but it is the least-provisioned of all six roles: no `role_priviledge` rows, no dedicated menu entry, and it is missing from at least one admin-facing label array. It is closer to a hardcoded workflow gate embedded in existing screens than a fully first-class role like Circle.**

**How it receives applications**: passively, by application status. Once an application reaches status 15/16 ("Recommended for Payment") via the Super Admin's HQ review (§1.6), it becomes visible to Account through the existing generic "Application" listing screens (§1.8) — there is no dedicated inbox or queue screen for Account; it uses the same `statuswise_application.php` listing every other role uses, filtered by URL query-string `status`.

**Every reference found, by category:**

**Controller-level gates (the only enforcement — no centralized permission backing them):**
- `Scholarship::forward()`, `Scholarship.php:85` — `if(USER_TYPE=='6')`, moves 15/16 -> 28.
- `Scholarship::remove()`, `Scholarship.php:63` — `if(USER_TYPE=='6')`, moves 28 -> 15 (undo).
- Confirmed via `hooks/Visitor.php` (full 132-line read): the centralized permission hook has **no rule at all** for the `scholarship`, `applications`, `batch`, `payment`, `dashboard`, or `report` controllers — its per-controller checks are limited to `roles`, `user`, `beneficiary` (dead — controller doesn't exist), `statewisedata` (dead — controller doesn't exist), and `member`. So the two `USER_TYPE=='6'` checks above are the **entire** access-control surface protecting these two actions; nothing in `role_priviledge`/the permission hook backs them up.

**View-level UI (three specific, conditionally-rendered elements, all in one file):**
- `statuswise_application.php:186` — `if($_GET['status']=='28' && USER_TYPE==6)`, shows a "Proceed to Payment" button (links into the `payment` module).
- `statuswise_application.php:300` — `if($_GET['status']=='28' && USER_TYPE=='6')`, shows a per-row "remove" (X) icon linking to `scholarship/remove`.
- `statuswise_application.php:303` — `if($_GET['status']=='15,16' && USER_TYPE=='6')`, shows a per-row "forward" icon linking to `scholarship/forward`.
- No `USER_TYPE==6` reference exists in any of the other 8 core views read for this analysis (`add_scholarship.php`, `edit_scholarship.php`, `detail_scholarship.php`, `verify_application.php`, `manage_application.php`, `manage_batch.php`, `view_batch.php`, `layouts/default.php` — confirmed by full-file grep on each).

**Dashboard presence (real, and fairly substantial):**
- `dashboard_data.php:143-160` — a dedicated `USER_TYPE=='6'` branch computing the top-row summary cards (Total/Pending/Approved/Rejected/Payment-Complete/Payment-Failed), structurally near-identical to the default/HQ branch (`dashboard_data.php:161-177`) except for a narrower `$approved_status` scope (`'15,16'` for Account vs `'15,16,17,18,19,20,27,28'` for default/HQ).
- `dashboard_data1.php:156` (the orphaned near-duplicate view, §0.2) has a static "Account Section" table row — not role-gated itself, just a label, visible to whoever the (unreachable) page would render for.
- Confirmed in real data: `payment_batch.added_by` (the table populated by `Payment::finishpayment()`, §1.9) contains the values `'1028'` and `'5'` across every batch row in the dump — i.e., **the two real Account users are confirmed, from production data, to be the ones who actually ran the AXIS bank-file generation step**, directly corroborating the role's purpose.

**What Account does NOT have:**
- **No `role_priviledge` rows at all.** Confirmed by an exact regex sweep of the full SQL dump (`grep -oE "\([0-9]+,6,[0-9]+\)"` against the `role_priviledge` INSERT) — zero matches. `User_model::checkUserAccess()` (the permission-check function) will therefore always return `false` for an Account user, for any permission ID.
- **No menu entry.** `layouts/default.php` has zero `USER_TYPE=='5'` or `=='6'` checks; the only Payment-related menu block (`layouts/default.php:222-261`, containing Pending/Completed/Failed/Upload UTR links and the Report link) is gated `USER_TYPE=='1'` (Super Admin) only. An Account user reaches `forward()`/`remove()` exclusively via the inline links inside a status-filtered application list, never via the sidebar.
- **Missing from the admin label array.** `User.php:911` — `$userstype = ['', '', 'District Union', 'Samiti', 'Investigation Committee', 'Circle'];` — only 6 elements (indices 0-5). Indexing this array with `user_type==6` (used at `User.php:915` to render the "User Type" column in an admin export) is an out-of-range access; Account has no label there.
- **Not represented in the `user_type` table's own schema comment** (`scholarship.sql:1189`: `COMMENT '1=Admin, 2=District Union, 3=Samiti'` — doesn't even mention 4 or 5, let alone 6).

**Which reports/documents Account touches**: none beyond the dashboard summary above and the CSV outputs of the payment pipeline it triggers (`Payment::proceedpayment()`'s batch build and `Payment::finishpayment()`'s AXIS `.txt` file, §1.9) — no `USER_TYPE==6` reference was found in `Report.php`, or in any of `payment.php`/`payment_navigate.php`/`upload_csv.php`/`finish_payment.php` (confirmed by full-file grep on all four).

**Recommendation on migration**: preserve the two real actions (`forward`/`remove`) as role-gated capabilities, but do not assume Account needs (or historically had) a permission-table entry, a menu item, or unrestricted visibility — none of those exist in legacy. Any Laravel implementation that grants Account a `role_priviledge`-equivalent permission or a menu item is an **enhancement beyond legacy parity**, not a replication of existing behaviour, and should be labelled as such if kept (see §13 for what the current Laravel implementation actually does here).

---

## 6. User Management

All facts below are sourced directly from `application/controllers/User.php` (935 lines, read in full for this analysis), even though its own views are missing (§0.1).

### 6.1 `manageuser()` — list screen (`User.php:140-195`)
Lists users with pagination (`config['base_url']` = `user/manageuser`), builds `manage_user` view (missing, §0.1). Business logic not further inspected beyond the query shape (out of scope — this method has no status-transition or validation logic of note beyond listing).

### 6.2 `add_user()` — create (`User.php:197-283`)
- Login required (`User_model::isLogin()`), no explicit `USER_TYPE` gate inside the method itself (enforcement is via `hooks/Visitor.php`'s `permission_id==1` check, §5).
- Fields collected from POST: `name`, `email`, `mobile`, `status`, `user_type`, `district`, `circle`, `districtunion`, `samiti`.
- Password is submitted **RSA-encrypted** client-side (via the `jsencrypt.js` asset, the only app-specific non-vendor JS file found in the whole codebase besides `ui.js`) and decrypted server-side with `openssl_private_decrypt()` against `rsa_1024_priv.pem` (`User.php:216-227`) — both `password` and `confirm_password` go through this.
- Validation rules (`User.php:238-263`):
  - `name`: required, min 3 chars, custom `valid_name` callback.
  - `mobile`: required, exactly 10 digits, numeric, `is_unique[users.mobile]`.
  - `email`: required, valid email, `is_unique[users.email]`.
  - `user_type`: required.
  - **`district`: required UNLESS `user_type==5`, in which case `circle` is required instead** (`User.php:258-262`) — confirms the District-vs-Circle mutual-exclusivity rule exactly.
  - `districtunion`: always required.
  - **`samiti`: required only if `user_type==3`** (`User.php:263-264`).
  - `password`: custom `valid_password` callback; `confirm_password`: must match `password`.
- On success: `password_hash(hash("sha512", $password, true), PASSWORD_DEFAULT)` (double-hashed — SHA-512 then bcrypt/argon via `password_hash`), `User_model::saveUser()`, then **also** `User_model::saveScholarshipuser($postdata)` — a second save call whose implementation is in `User_model.php` but was not specifically inspected for this analysis (NOT VERIFIED what additional table/columns this second call touches, beyond what `saveUser` already covers).
- **Assignable roles**: `$data['user_type'] = $this->db->where('id > ', 1)->get('user_type')->result_array();` (`User.php:280`) — i.e., every `user_type` row with `id > 1` is offered, which excludes only Super Admin (id 1). This is dynamically driven by the `user_type` lookup table's actual contents, not a hardcoded list — if a `user_type` row for id 6 ("Account") existed in legacy's own table, it would have been offered here too (it does not — confirmed, §3).

### 6.3 `edit_user($id)` — edit (`User.php:286-363`, exact behaviour not fully re-transcribed here since the view is missing, but the controller's field-acceptance logic was read)
NOT VERIFIED in full field-by-field detail in this pass (the controller method exists and was scanned but not exhaustively line-annotated in this analysis the way `add_user()` was) — known from the `beema` proxy view structure (§0.3) that Name/Email/Mobile/Role render as disabled inputs (never resubmitted) while District/Circle/DistrictUnion/Samiti/Status remain editable, and the password field is conditionally hidden when a user edits their own account. This specific behavioural claim is carried over from the `beema` proxy, not re-verified line-by-line against `scholarship`'s own `edit_user()` in this pass — flagged here for transparency since it does not meet this document's "read the actual code" bar as strictly as §6.2 does.

### 6.4 Deactivation / Activation
NOT VERIFIED as a distinct method in this pass — no dedicated `activate`/`deactivate` method name was found in the `User.php` method list read for this analysis (`grep -n "public function" User.php`, not reproduced in full here). Status toggling is most likely folded into `edit_user()`'s `status` field. Flagged as NOT VERIFIED rather than assumed.

### 6.5 Permission gates (`hooks/Visitor.php:42-51`) — CORRECTED in this pass, see §16.4

As written (the intent of the code):
```
user::add_user   requires role_priviledge permission_id == 1
user::edit_user  requires role_priviledge permission_id == 2
user::vle_profile is unconditionally blocked for any non-VLE session   [see correction below]
```
In production `role_priviledge` data, only role 1 (Super Admin) holds both permissions 1 and 2 (`scholarship.sql:1062`, rows `(490,1,1)` and `(491,1,2)`).

**Correction, confirmed by this pass's re-analysis (§16.4): this gate does not actually prevent the write.** `User::add_user()` and `User::edit_user()` have no in-method `USER_TYPE`/permission check of their own (confirmed by the full read in §6.2) — they rely entirely on the `Visitor::check_permission()` hook. That hook runs on CI3's `post_controller` hook point, which fires only *after* the requested controller method has already fully executed (`system/core/CodeIgniter.php:527`, `call_user_func_array(array(&$CI, $method), $params)` runs before the `post_controller` hook call at line 543). Both `add_user()` and `edit_user()` call PHP's `redirect()` helper (`header()` + `exit`, confirmed in `system/helpers/url_helper.php:533-566`) immediately after their `INSERT`/`UPDATE`, on every success path — and `exit` terminates the request before the framework ever reaches the `post_controller` hook call. **Net effect: any authenticated, non-VLE session — regardless of which role or which `role_priviledge` rows it holds — can successfully create or edit a user by POSTing directly to `user/add_user`/`user/edit_user`.** The permission check is real code, evaluates a real condition, and would redirect the browser away — but only *after* the row has already been written, and in the actual success path it never even runs, because the controller's own `redirect()` call exits first. This is not a theoretical edge case; it is the normal, every-time behaviour of these two actions.

**Correction on `vle_profile`**: the claim that `user::vle_profile` is "unconditionally blocked for any non-VLE session" is also wrong, for a different reason (a logic bug, not the hook-timing issue) — see §16.4 for the precise unreachable-code analysis. In practice this specific case is harmless because `User::vle_profile()` has its own redundant, correctly-timed in-method check (`if ($this->session->userdata('USER_TYPE') != 'VLE') { redirect(base_url()); }`, `User.php:430-432`, evaluated before any database write) — so no unauthorized write can occur here even though the hook-level check is dead code.

### 6.6 Member Management (IC committee roster) — new in this pass, not covered by the original research

`Member.php` (read in full) manages a `members` table of committee members attached to a specific IC (`user_type==4`) user's roster, via `manage($userid)` (list), `add($userid)` (create), `edit($userid,$id)` (status update only). All three views (`add_member.php`, `edit_member.php`, `manage_member.php`) are **missing from this checkout** (§0.1) — same pattern as User Management.

**Access control**: `Member.php`'s constructor (lines 22-39) performs only `User_model::isLogin()` — **no `USER_TYPE` check exists anywhere in this controller** (confirmed by a full-file grep for `USER_TYPE`; zero matches). `add()`/`edit()` do validate that the *target* `$userid` belongs to a `user_type==4` user (`Member.php:100-101`, `130-131`) — but nothing checks that the *acting* session is itself IC or Super Admin. Access is nominally gated by `hooks/Visitor.php:105-109` (`if($controller=='member'){ if(!in_array('36', $priv)){ redirect(base_url()); } }`), and in production data only role 1 (Super Admin) holds permission 36 (`scholarship.sql:1062`, row `(679,1,36)`) — but per §16.4/§6.5's finding, this gate is subject to the exact same hook-timing bypass: both `add()` and `edit()` call `redirect()` immediately after their `INSERT`/`UPDATE` on every success path (`Member.php:119`, `145`). **Net effect: any authenticated, non-VLE session can add or edit committee members on any IC user's roster**, not just Super Admin as the permission grant alone would suggest.

---

## 7. Dashboard

`Dashboard.php` construct requires only `isLogin()` — no `USER_TYPE` gate on the controller itself.

### 7.1 `dashboard.php` — the actual landing page is a scheme picker, not a counter dashboard
Confirmed by reading the view in full: it loops `$schemes` and renders one card per scheme, each a plain `<a href>` to `dashboard/dashboard_data/{scheme_id}` (`dashboard.php:39`) — a full page navigation, not AJAX/iframe. No bucket/count variable is referenced anywhere in this file.

### 7.2 `dashboard_data($scheme)` — the real counters, `Dashboard.php:53-170`
Verbatim SQL fragment (`Dashboard.php:58-64`), one row per district union, always filtered to `payment_txn_status='1'` (excluding unpaid drafts from every count):
```sql
SUM(IF(status IN ('0','1','2','3','4','5','9','11','13','15','17','19'),1,0)) as sam,
SUM(IF(status IN ('0','1'),1,0)) as sampen, ... [full 29-bucket set, see prior session's research]
```
Role-scoping appended to the same query: VLE -> `added_by=<user>`; Samiti(3) -> `samitiname=<user.samiti>`; DU/IC(2,4) -> `districtunion=<user.districtunion>` (with the DU-5/32 merge special case); Circle(5) -> `districtunion IN (<all DUs under this circle>)`. **No branch exists for role 6** — an Account session hitting this SQL directly would receive the unscoped/default query (same as Super Admin).

### 7.3 `dashboard_data.php` (699 lines) — role-branched top cards + full analytics table
**Top-row summary cards**, remapped per role into a common variable set (`$total_app`, `$pending_app`, `$approved_app`, `$rejected_app`, `$payment_complete_app`, `$payment_failed_app`):

| `USER_TYPE` | Lines | Notes |
|---|---|---|
| `VLE` | 80-97 | **Confirmed bug**: line 97 has `$ppr_status-'99';` (a stray subtraction, not an assignment — should be `$ppr_status='99';`). Every other role branch correctly assigns the string. Because `$ppr_status` ends up undefined for VLE, the "Pending Payment" tile's link (built at line 348 using `$ppr_status`) is malformed for VLE users specifically. |
| `2` (DU) | 98-112 | uses `$dureceived`/`$dupending`/`$recomdu`/`$rejectdu+$cduprej` |
| `3` (Samiti) | 113-126 | uses `$samreceived`/`$pendingsamupt`/`$recomsp`/`$rejectsp+$csamitiprej` |
| `4` (IC) | 127-142 | uses `$icreceived`/`$pendingic`/`$recomic`/`$rejectic+$cicprej` |
| `6` (Account) | 143-160 | ad-hoc query on `payment_txn_status='1' AND scheme=...`; uses `$hqpending`/`$hqrecom`/`$hqreject+$chqprej+$caccprej`; `$approved_status='15,16'` |
| else (default / HQ) | 161-177 | structurally identical to the Account branch except `$approved_status='15,16,17,18,19,20,27,28'` |

**"Overall Analytics" district-union table** (lines 397-595) — this is where every named bucket from `Dashboard.php`'s SQL is actually surfaced, one column per bucket (`sam`,`sampen`,`samrec`,`samrej`,`ic`,`icpen`,`icrec`,`icrej`,`ccf`,`ccfpen`,`ccfrec`,`ccfrej`,`du`,`dupen`,`durec`,`durej`,`hq`,`hqpen`,`hqrec`,`hqrej`,`paydone`,`payfail`,`samitiprej`,`icprej`,`ccfprej`,`duprej`,`hqprej`,`accprej`), each cell linking to `dashboard/export/{scheme}?union={id}&status=...` for drill-down.

**Dead AJAX in this view**: `getuniondata()`/`getsamitidata()` (lines 643-681) POST to `user/getdistrictuniondata`/`user/getsamitidata` and try to populate `#districtunion`/`#samitiname` — but no such `<select>` elements exist anywhere in this file's markup. Leftover/copy-pasted script with no effect.

### 7.4 `dashboard_data1.php` (orphaned, §0.2) — for completeness
Structurally near-identical to `dashboard_data.php`; its summary table additionally has explicit row labels **"CCF"** (line 125) and **"Account Section"** (line 156) that `dashboard_data.php`'s equivalent table does not spell out as literal row headers (it uses column headers instead) — this is the strongest single piece of evidence in the whole codebase that role 5's colloquial name is "CCF" and role 6's is informally "Account," even though this exact file is never actually loaded by any controller.

---

## 8. Menu Structure

Single shared layout (`application/views/layouts/default.php`, 311 lines) used by essentially every authenticated screen (confirmed via `Template.php` + a grep of every controller's `->set_layout()` call — only the login page (`'login'`) and password-reset page (`'reset'`) use a different layout). There is no per-role layout file; all menu variation happens via inline PHP conditionals inside this one file.

Complete, exact enumeration (line numbers as read):

| Line(s) | Label | Target | Gate |
|---|---|---|---|
| 129-134 | Scholarship Dashboard | `dashboard` | none — always shown |
| 136-141 | Insurance Dashboard | hardcoded `https://beema.local.in/dashboard` | none — always shown |
| 144-149 | Add Application (top-level) | `scholarship/scholarshipschemedetail` | `USER_TYPE=='VLE'` |
| 151-156 | Incomplete Application | `failed` | same `USER_TYPE=='VLE'` block |
| 161-212 | **Application** (submenu parent) | — | none — always shown |
| 167-172 | ↳ All Applications | `scholarship` | none |
| 174-179 | ↳ Add Application (duplicate of the top-level item) | `scholarship/scholarshipschemedetail` | `USER_TYPE=='VLE'` |
| 181-210 | ↳ Pending / Processing / Completed / Rejected / Failed Applications | `scholarship/{pending,underprocess,completed,rejected,failed}` | none — every role sees all five links; row-level filtering happens inside each controller method, not the menu |
| 214-221 | Batches | `batch` | `in_array('38', $priv)` — the only menu item gated by `role_priviledge` rather than a literal `USER_TYPE` check. In production data this means roles 1, 2, 4, 5 (not 3, not 6). |
| 222-261 | **Payment** (submenu: Pending/Completed/Failed/Upload UTR) + **Samiti Wise Count** (Report link) | `payment/{pending,completed,failed,uploadcsv}`, `report` | entire block gated `USER_TYPE=='1'` only |
| 262-267 | Logout | `user/logout` | none |

**No `USER_TYPE=='5'` or `=='6'` gating exists anywhere in this file** — confirmed by a full-file grep. Circle and Account see exactly the same generic menu as District Union/IC (minus the VLE-only and Super-Admin-only items), and must reach their role-specific actions (batch verification for Circle, forward/remove for Account) via inline conditional buttons embedded inside the generic listing screens, not via a dedicated nav entry.

---

## 9. Document Flow

Two parallel, redundant storage mechanisms were found for uploaded documents — this is a new finding not present in prior analysis documents.

### 9.1 The authoritative mechanism: `application_files` table
Schema (`scholarship.sql:388-398`): `id, application_id (varchar, matches application.application_id — NOT the numeric application.id), filetype, filepath, added_by, add_date, status enum('0','1'), application_type enum('Normal','Student')`.

Every upload in `Scholarship::add()`/`edit()` populates a `$datafiles[]` array of `[filetype, filepath]` pairs, looped into individual `INSERT INTO application_files` statements (`Scholarship.php:1793-1806`, `2874`, `2123`). Retrieval is exclusively via `AwsS3upload_model::getmyfile($application_id, $filetype)` (`AwsS3upload_model.php:77-98`) — `SELECT ... WHERE application_id=? AND filetype=? ORDER BY id DESC LIMIT 1`, building a 5-minute presigned S3 URL (bucket/key from `awsBucketname`/`awsUploadfolder` config, region `ap-south-1`), with a local `/uploads/` path fallback on `S3Exception`. **Every single view-side "View Document" link found in this analysis** (`detail_scholarship.php`, `edit_scholarship.php`) uses this function — confirmed by grepping every `passbook`-related read across `views/` and `models/`.

### 9.2 The dead mechanism: scalar columns on `application` itself
The `application` table (`scholarship.sql:56-`) also has its own `tpcard`, `admission_copy`, `passbook`, `aadharcard` (and by the same pattern, other doc-named) columns. Tracing the write path (`Scholarship.php`):
```php
$data["passbook"] = $passbook;          // application table column
$datafiles[] = ["passbook", $passbook]; // application_files row — SAME value, both written
```
Both get the identical filename value on every upload (confirmed at `Scholarship.php:1703/1784`, `1929`, `2676-2677`) — this is pure redundancy, not two different documents. **Nothing anywhere in the codebase reads `application.passbook` (or its sibling scalar columns) for display or business logic** — confirmed by grepping every view and model for a read of these columns; every retrieval goes through `AwsS3upload_model::getmyfile()` instead. These columns are write-only vestiges, almost certainly a leftover from an earlier schema design that predates the `application_files` table.

**Confirmed bug in this dead code path** (`Scholarship.php:2691-2695`, inside `edit()`'s scheme-1/2 branch): when a new `head_passbook` file is uploaded, the code does `$data["passbook"] = $passbook;` — reusing the PHP variable `$passbook` from an *earlier, unrelated* `if ($_FILES["passbook"]...)` block. For scheme-1/2 applications, that earlier block's `if` never fires (there is no `passbook` file field for these schemes, only `head_passbook`), so `$passbook` is undefined at this point, and `application.passbook` gets overwritten with an undefined/null value — clobbering whatever the immediately-preceding `else` branch had just correctly preserved (`$data["passbook"] = $thisapplication["0"]["passbook"];`). Because nothing reads this column (previous paragraph), the bug has no observable effect on the application — flagged for completeness, not as something requiring a fix.

### 9.3 Document inventory
| `filetype` | Uploader | When | Mandatory | Scheme | Notes |
|---|---|---|---|---|---|
| `tpcard` | VLE | creation | Yes, all schemes | all | "Sangrahak Card" |
| `aadharcard` | VLE | creation | Yes, all schemes | all | Student Aadhaar |
| `haadharcard` | VLE | creation | Yes, all schemes | all | Head-of-family Aadhaar |
| `admission_copy` | VLE | creation | Yes, all schemes | all | "Markesheet Copy" (edit_scholarship.php has a filetype-key typo: `'aadmission_copy'` at line 478, inconsistent with the upload field name `admission_copy`) |
| `head_passbook` | VLE | creation | Yes | schemes 1/2 only | Legacy's single passbook slot for these schemes; the "View Front Page of Passbook" link text is a caption, not a second document |
| `passbook` | VLE | creation | Yes | schemes 3/4 only | Never created for schemes 1/2 |
| `admission_receipt` | VLE | creation | Yes | schemes 3/4 only | |
| `phadbookfile` | Samiti | Samiti review | Yes (blocks approval) | all | |
| `momfile` | IC / Circle | batch verification | Yes (blocks batch verify); note the "Max 2MB" UI text vs actual 5MB server limit (`Scholarship.php:2975`) mismatch | all | Stored on `application_batch`, not per-application |

On resubmission (`edit_scholarship.php`), none of the document fields carry the `required` attribute (a file may already exist); each has a "View" link via `getmyfile()`.

---

## 10. Batch Workflow

Two entirely independent batch mechanisms exist in this codebase — they must not be conflated:

### 10.1 IC/Circle batching — `Scholarship::addbatch()` + `Scholarship::verifyscholarship()`
- `addbatch()` (`Scholarship.php:2923-2956`), `USER_TYPE==4` only (despite `manage_application.php` showing the "Create Batch" UI to both 4 and 5 — see below): takes a checklist of `application_id`s from `manage_application.php`, creates one `application_batch` row (`batchid = time().rand()`), stamps every selected `application.batchid`, redirects to `verifyscholarship($batchid)`.
  - **Note**: `manage_application.php:180` gates the "Create Batch" UI to `in_array(USER_TYPE, ['4','5'])` — both IC and Circle see the button — but the underlying `Scholarship::index()` createbatch-pool query differs by role (status 4 for IC, status 6 for Circle, per §1.3/§1.4); `addbatch()`'s own `USER_TYPE` gate was documented in the original controller research as IC-specific (NOT VERIFIED beyond that original citation in this pass — the exact gate condition on `addbatch()` itself was not re-read line-by-line in this analysis; flagged for a future pass if precision here becomes load-bearing).
- `verifyscholarship($id)` (`Scholarship.php:2958-3122`), gated `in_array(USER_TYPE, ['4','5'])`. Lists `application.batchid=$id`, filtered further by `status=='4'` for IC or `status=='6'` for Circle. Requires a `momfile` upload, stored on `application_batch.momfile`. Per-row decision writes status 5/3/22 (IC) or 8/7/23 (Circle), per §1.3/§1.4.
- Live AJAX amount override inside this screen: `verify_application.php`'s `updateamount()` (lines 301-334) POSTs to `scholarship/updatepayment`.

### 10.2 District Union batching — `Batch.php`
Entirely separate: `Batch::index()` lists batches (scoped: DU/IC by own `districtunion`, Circle by circle's district-unions — visibility only, confirmed `Batch.php` has no status-writing logic for role 5); `Batch::view($id)` lists one batch's applications and contains the DU-only approve action (§1.5b, with the confirmed `$applicaiton` typo bug); `Batch::export($id)` is a Super-Admin-only CSV of status 11/12 rows with nominee bank details, functionally distinct from the automated AXIS bank-file flow (§1.9/§11).

---

## 11. Payment Workflow

Full pipeline, in order, consolidating §1.6/§1.8/§1.9/§1.10:

```
HQ recommends (11/12 -> 15/16)
     |
     +-- Account.forward()  (15/16 -> 28)
     |        |
     |        +-- Account.remove()  (28 -> 15)  [undo]
     |        |
     |        +-- Payment::proceedpayment($scheme)  groups status-28 rows -> payment_batch/payment_batch_application
     |                    |
     |                    +-- finish_payment.php confirmation screen (BEFORE file generation)
     |                              |
     |                              +-- Payment::finishpayment()
     |                                        writes AXIS .txt bank file
     |                                        payment_batch.status = 1
     |                                        application.status = 99  (no audit row — §1.9)
     |
     +-- HQ's detail() payment-result entry (15/16 -> 17/18/19/20/26)
                OR
          Payment::uploadCsv()  bulk UTR CSV  (17/18/19/20, lineage often lost past status 99 — §1.10)
```

**Amount computation** is independently duplicated in at least three places, confirmed by direct reads:
1. `Application_model::getamount($alluser)` (`Application_model.php:309-331`) — dead code, has no `return` statement, always yields NULL.
2. `scheme_helper.php::getAmount($scheme)` (lines 3-17) — a coarse per-scheme allow-list (e.g. scheme 1 -> `[2500,3000]`), used by `verify_application.php`'s amount dropdown.
3. `scheme_helper.php::checkamount($application_id, $posted_amount)` (lines 20-70) — the precise scheme+class+education_year matrix, with a **confirmed bug** at line 38 (`$det['0']==3` compares an array to an integer, always false, so the "scheme 3, education_year > 1" branch is unreachable dead code) and logs every allow-listed amount change (matching or not) to `update_amount`.
4. `Scholarship::exportpaymentfile()` and `Payment::proceedpayment()` each additionally hardcode their own copy of the same scheme/class amount table (confirmed present in both, exact duplication not re-transcribed here — already documented in the original controller-level research).

**Access-control gap, confirmed**: `Payment.php`'s `pending()`, `completed()`, `failed()`, `uploadCsv()`, `proceedpayment()`, `finishpayment()` have **no `USER_TYPE` check anywhere in the controller** (only `isLogin()` in the constructor) — any authenticated staff session (Samiti, IC, etc.) could hit these URLs directly. The only thing hiding them is the Super-Admin-only menu entry (§8) — not enforced access control.

**`agepricematrix` / `paymentfailreasons` / `update_amount` tables**: `agepricematrix` (schema read: `scheme_id, fromage, toage, application_type, cause, amount`) is confirmed to belong to the death-benefit Sahayata module (`cause` values are `'Natural'`/`'Unnatural'`/`'Partial'`/`'Complete'` — disability/death categories, not scholarship concepts) — **not** part of the Scholarship module's payment logic. `paymentfailreasons` is a simple `id,name` lookup feeding the `paymentfailreason` select in `detail_scholarship.php`'s HQ payment-result form (§1.6). `update_amount` is the audit log written by `scheme_helper.php::checkamount()`.

---

## 12. Reports

`Report.php` (72 lines, read in full) is the **only** dedicated report controller for the Scholarship module. It has no view at all — `index()` streams a CSV directly via `csv_download()`:
```sql
SELECT count(*) as total, samiti.samiti_name, schemes.name
FROM application JOIN schemes ON schemes.id=application.scheme JOIN samiti ON samiti.id=application.samitiname
GROUP BY application.samitiname, application.scheme
```
Columns: `S.No, Samiti Name, Scheme Name, Total Application`. **Not filtered by `payment_txn_status`** — unlike almost every other listing/dashboard query in the app, this report includes unpaid drafts too. No filters, no role-based scoping inside the controller (menu-gated to `USER_TYPE=='1'` only, §8). Business purpose: a simple samiti × scheme application-count summary, labeled "Samiti Wise Count" in the menu.

No other dedicated report screen exists for the Scholarship module — the CSV `export()` actions on `Scholarship.php` (per-listing exports, role-scoped identically to their parent listing) and `Batch::export()` (Super-Admin-only, status 11/12 bank-detail CSV) and `Scholarship::exportpaymentfile()` (status-28 CSV for a scheme) are export functions attached to existing screens, not standalone reports.

---

## 13. CI3 vs Laravel

This section compares the legacy facts established above (§1-§12) against the current Laravel implementation, as read directly from this repository's code during this same analysis session (not assumed from memory of prior sessions).

| Legacy | Laravel | Migrated? | Differs how |
|---|---|---|---|
| Full status ladder 0-28, 99 (§2) | `App\Domains\Scholarship\Enums\ScholarshipApplicationStatus` enum, same 0-28 + 99 values, each with a `label()`/`stage()`/`workflowState()` | Yes | Laravel additionally documents status 27 with a real workflow meaning (`AccountDetailsUpdatedByHQ`) and a named resubmit-eligible transition — legacy's status 27 has no confirmed write path at all (§2) |
| Circle/CCF via-status-6 branch (§1.4, §4) | `ScholarshipApplicationStatus` enum comment: "Source-system CCF workflow states retained for migrated applications. New applications must never enter these states." `ScholarshipService::nextStatus()`'s transition map has no `RecommendedByIC -> RecommendedByCCF`-style branch at all — IC recommends go straight to District Union | Deliberately not migrated as a live path | Consistent with this analysis's finding that the branch never fired in production (§4) — this was evidently already known/decided in a prior Laravel-side session, and this analysis independently corroborates it was the right call |
| District Union's two independent batch-approve mechanisms, one buggy (§1.5) | `ScholarshipWorkflowController::action()` + `ScholarshipService::nextStatus()` — a single, unified transition map, no separate `Batch`-style bulk approve with the `$applicaiton` typo bug | Consolidated | The Laravel side has one code path instead of legacy's two independently-coded ones; the specific typo-bug (§1.5b) has no Laravel equivalent to inherit |
| Bulk "recommend for payment" collapsing via-CCF lineage + first-row-only bug (§1.7) | No direct equivalent found in `ScholarshipWorkflowController`/`ScholarshipService` — batch actions (`createPaymentBatch`, `paymentBatch()`) process the full submitted list | Not migrated as a bug | Legacy defect intentionally not reproduced |
| Payment pipeline: `proceedpayment`/`finishpayment` (AXIS bank file) + `uploadCsv` (UTR reconciliation), §1.9/§1.10/§11 | `ScholarshipWorkflowController::paymentBatch()`/`paymentResult()` record payment batches and results; **no equivalent found** for AXIS `.txt` file generation or CSV-based UTR bulk reconciliation in this repository | Partially — batch/result recording exists, but not the bank-file generation or CSV reconciliation | A genuine capability gap, not yet decided one way or the other in Laravel |
| Account role (6): zero `role_priviledge` rows, no menu, missing from at least one label array (§5) | `user_type` row 6 ("Account") exists (added by a Laravel-side migration in a prior session, not part of legacy's own `user_type` table), is selectable in Create/Edit User, and is granted role-list membership in several `config('legacy_authorization.abilities')` entries (`applications.view`, `applications.submit`, `applications.documents.view`, `workflow.view`, `workflow.action`) | Enhanced beyond legacy | A prior Laravel-side session also briefly granted role 6 `role_priviledge` permission 38 for menu parity, then reverted that specific grant after finding it had no legacy basis (role 6 has zero `role_priviledge` rows in legacy, confirmed independently again in §5 of this analysis) — the reversion is consistent with this document's findings |
| Circle's read-scoped visibility (checkaccess circle->DU resolution, §4) | `DataScopeService::circleDistrictUnionScope()`/`applyScholarshipVisibility()` implements an equivalent circle->district-union scoping | Migrated | Matches legacy's real, functioning scoping logic |
| Document storage: `application_files` (real) + dead scalar columns on `application` (§9) | `ScholarshipViewModelService::productionDocumentLabels()` reads from a `currentDocuments` relation (application_files-equivalent) only — no dead scalar-column reads exist in Laravel's schema at all (the redesigned Laravel `scholarship_applications` table doesn't carry those legacy vestigial columns) | Migrated, and the vestigial-column problem does not exist on the Laravel side | Confirms the Laravel-side Passbook fix made in a prior session (removing a phantom `passbook` label for scheme 1/2) was correct — independently re-confirmed via the write-path trace in §9.2 of this analysis |
| Dashboard: `Dashboard.php`/`Application_model.php`/`Dashboard::export()` each independently re-implement the same role-scoping SQL (§7) | `DataScopeService` centralizes this once, used by `ScholarshipRepository`, `DashboardController`, and workflow visibility alike | Consolidated | Positive consolidation, not a behaviour change |
| Menu: no dedicated "User Management" entry point at all in legacy (URL-only, §8); "Payment" submenu Super-Admin-only; "Batches" permission-38-gated | Laravel's `MenuBuilder` has a dedicated "User Management" item, a "Workflow Batches" item (permission-38-gated, matching legacy's Batches gate), and no separate "Payment" menu (payment actions live inside the Workflow Batches screen) | Restructured | New-system menu hygiene; no direct legacy analogue for "User Management" to diverge from |
| `Report.php`'s single "Samiti Wise Count" CSV (§12) | `ScholarshipReportController` renders a status-grouped report view (`scholarship.reports.index`), not a samiti×scheme CSV | Not a direct migration — a different report shape | Functional gap: legacy's specific samiti×scheme count report has no confirmed Laravel equivalent |
| User Management screen: District->DistrictUnion->Samiti / Circle->DistrictUnion cascade, role dropdown driven by `user_type WHERE id>1` (§6.2) | `UserManagementService`, `StoreUserRequest`/`UpdateUserRequest`, `resources/views/users/_form.blade.php` implement the same cascade and the same required/optional field rules (district required unless Circle, circle required if Circle, samiti required if Samiti) | Migrated | Matches §6.2's validation rules exactly, confirmed independently in this analysis pass |
| **Permission enforcement timing** (§16.4): legacy's `role_priviledge` gate (`Visitor.php`, `post_controller` hook) evaluates *after* the requested controller method — including any database write — has already fully executed, so it cannot prevent an unauthorized write; only the controller's own inline `USER_TYPE` checks (used throughout the Scholarship module) are actually effective | Laravel's `Gate::authorize()`/route `can:` middleware runs *before* the controller action body, at the routing/middleware layer, for every gated route including `users.create`/`users.update` | Not a migration — a structural improvement | This is legacy's actual, executed behaviour finally matching what its code *appears* to say; not something to replicate. Flagged so this document's earlier, pre-this-pass characterization of `Visitor.php` as an effective gate (§6.5's original text, now corrected) is not mistaken for legacy's real behaviour when read by future migration work |
| VLE OAuth `state` CSRF check (§16.1): present in code, generated and stored in session, but the actual comparison against the callback's returned `state` is commented out — no live CSRF protection on the OAuth callback | **NOT VERIFIED** whether Laravel's CSC/OAuth integration (`AuthController::decryptLegacyPayload` and related VLE login code, referenced in earlier sessions but not re-examined in this pass) implements or omits an equivalent state check | Unknown | Flagged as a question for a focused Laravel-side security review, not answered by this analysis pass, which was scoped to the legacy side only |

---

## 14. Business Rules (code-backed only)

1. Application creation is hard-blocked after 2025-01-28 (`Scholarship.php:1475-1478`).
2. A VLE's wallet-payment failure/cancellation leaves the application permanently stranded as an unpaid draft — no retry/cleanup code path exists (`Scholarship.php:3279-3282`).
3. Samiti cannot approve if any of the 3 years' tendu-collection quantity is below 500 (`Scholarship.php:2232-2234`).
4. VLE's `edit()` resubmission ignores the application's specific prior status — any non-permanent reject goes straight back to status 1 (`Scholarship.php:2840`).
5. VLE deletion is allowed only for status in `('0','1','21'-'26')` (`Scholarship.php:56`).
6. The DU-5/32 "merged jurisdiction" rule: wherever a District Union's or Circle's own `districtunion` is 5 or 32, visibility expands to both (`Application_model.php:286-290`, and repeated independently in `Dashboard.php`, `Scholarship.php`).
7. IC's MoM upload is capped at 5MB server-side despite a "Max 2MB" UI label (`Scholarship.php:2975` vs `verify_application.php`'s displayed text).
8. Circle's batch-verification action requires the application pool to be at status 6, which nothing in the Scholarship module ever creates (§4) — a structural, not incidental, non-functioning business rule.
9. Only the `paymentstatus=='Reject'` value (not `'Failed'`) advances an application to the *permanent* rejection status 26; `'Failed'` alone yields the resubmittable 17/18 (`Scholarship.php:2463-2519`).
10. `Payment::finishpayment()`'s status-99 transition is the only status write in the entire app that does not insert an `application_status` audit row (§1.9) — consistent with `application_status.status` being a DB enum that does not include `'99'`.
11. District/Circle mutual exclusivity in user creation: `district` required unless `user_type==5`, in which case `circle` is required instead (`User.php:258-262`).
12. `samiti` is required in user creation only when `user_type==3` (`User.php:263-264`).
13. Assignable roles in Create User are whatever rows exist in `user_type` with `id > 1` — a data-driven rule, not a hardcoded role list (`User.php:280`).
14. Only role 1 (Super Admin) holds both `role_priviledge` permissions 1 and 2 in production data, so only Super Admin can create or edit users via this screen today (`scholarship.sql:1062`).
15. The via-CCF payment-lineage distinction (16 vs 15) is lost after an application passes through `Payment::finishpayment()`'s status-99 transition, because `Payment::uploadCsv()`'s lineage check tests the *current* status (`==16`), which by then has already been overwritten to 99 (§1.10).

---

## 15. Unknowns / NOT VERIFIED

Everything in this list could not be confirmed from the code within the scope of this analysis, and should not be assumed true or false without further investigation:

1. **Status 27's write path.** Referenced only as an export-array label (`Scholarship.php:1402`, `'Account Detail updated by HQ'`), not present in the `$statusarr` used by the three main views, and no `$data['status']='27'`/`$datastatus['status']='27'` assignment was found anywhere in `Scholarship.php`. **NOT VERIFIED** whether any code path writes it.
2. **`edit_user()`'s exact field-by-field behaviour** in `scholarship`'s own `User.php` was not re-transcribed line-by-line in this pass (§6.3) — the read-only/editable field split is carried over from the `beema` proxy view, not independently re-confirmed against `scholarship`'s controller logic to the same standard as §6.2's `add_user()`.
3. **Activation/Deactivation as a distinct feature** — no dedicated method name was confirmed in this pass; likely folded into `edit_user()`'s `status` field, but this is inference, not a direct read. **NOT VERIFIED**.
4. **`User_model::saveScholarshipuser()`'s exact effect** — called immediately after `saveUser()` in `add_user()` (`User.php:279`), but its implementation was not read in this pass. **NOT VERIFIED** what additional data it writes.
5. **`Scholarship::addbatch()`'s precise `USER_TYPE` gate condition** — cited in this document (§10.1) from earlier controller-level research, not re-read line-by-line in this pass. The `manage_application.php` view-level gate (`in_array(USER_TYPE,['4','5'])`) is independently confirmed; the controller-side gate on `addbatch()` itself is not.
6. **`S3.php` library's actual usage** — confirmed to be a generic third-party S3 wrapper with no hardcoded bucket logic, and `AwsS3upload_model.php` appears to use the AWS SDK's `S3Client` directly instead of this library. **NOT VERIFIED** whether `S3.php` is invoked anywhere else in the app (a full usage grep was out of the skim-only instruction given for this file).
7. **`config.php`'s `encryption_key`** appears blank in the checked-in file. **NOT VERIFIED** whether it is overridden by environment/deployment configuration outside this repository.
8. **`users.status` enum's third value.** The schema is `enum('0','1','2')` (`scholarship.sql`). **Partially resolved in this pass**: `status=='0'` is confirmed to block login with a flash message (`User.php:71-73`, the `profile_inactive` language line) — i.e. `0` = inactive/disabled. No code path referencing `status=='2'` specifically was found anywhere in `User.php` (the login check only tests `=='0'`, so a `status=='2'` user would log in successfully, same as `status=='1'`). **Still NOT VERIFIED** what distinguishes status `2` from `1`, or whether any other file handles it differently.
9. **Whether `application_status` (the audit table) has ever actually received a row with `status='99'`** despite its enum not including that value — MySQL's behaviour here (reject vs. silently coerce to empty string, depending on SQL mode) was not tested against the live schema, and no code path was found that would even attempt such an insert (`finishpayment()` skips the audit insert entirely for this transition, §1.9). Presumed never attempted, but not proven by execution.
10. **The full set of Laravel-side files/behaviour for §13's comparison** reflects only what this analysis session directly read in the Laravel codebase (routes, the status enum, `ScholarshipService::nextStatus()`, `DataScopeService`, `MenuBuilder`, `UserManagementService`/its Form Requests, `ScholarshipReportController`, `ScholarshipViewModelService`) — it is not an exhaustive line-by-line audit of the entire Laravel application to the same depth as the legacy side.
11. **`Member::manage($userid)`'s exact list-rendering logic** was not read in full in this pass (only `add()`/`edit()` were read line-by-line, §16.6) — its `USER_TYPE` exposure (if any) is presumed absent by the same full-file grep that covered the whole controller, but the method body itself was not individually transcribed.
12. **`Society.php`/`Scheme.php`/`Relation.php`** — confirmed in earlier research to have no `USER_TYPE` checks (`Society.php`) or were not re-examined in this pass at all (`Scheme.php`, `Relation.php`). Given this pass's finding that permission-hook-gated controllers generally do not actually enforce their gate (§16.4), and `Scheme.php`/`Relation.php` were never confirmed either way, **NOT VERIFIED** whether either has its own in-method safeguard or relies (ineffectively) on `Visitor.php`. Neither is part of the Scholarship module's core workflow, so this is flagged for completeness rather than urgency.
13. **Whether any WAF/reverse-proxy/deployment-level control outside this codebase compensates for the `role_priviledge` hook-timing gap** (§16.4) — e.g. IP allowlisting, a gateway that re-validates permissions. **NOT VERIFIED** — entirely outside the scope of a code-only analysis; flagged so this is not mistaken for a settled "no compensating control exists" claim.

---

## 16. Authorization & Session Architecture

This section directly addresses the "Part 1: Authorization & Session" re-analysis. Every controller was confirmed to extend `CI_Controller` directly (`grep -n "^class.*extends" controllers/*.php` — all 12 controllers) — **`application/core/` contains only the stock `index.html`, no `MY_Controller.php` or any custom base controller exists in this application.** There is no shared/inherited controller logic anywhere; every cross-cutting concern is handled either per-controller (in each `__construct()`) or via the single hook described below.

### 16.1 How a user is authenticated

**Staff (mobile/password) login** — `User::index()` (`User.php:41-108`), the default controller/action:
- If already logged in (`User_model::isLogin()`), redirects straight to `dashboard`.
- On `POST save=='Login'`: the password field is RSA-encrypted client-side (via `jsencrypt.js`) and decrypted server-side with `openssl_private_decrypt()` against `rsa_1024_priv.pem`. The decrypted payload is then split on `@`: the first segment is checked against `$_SESSION['rand']` (a server-issued nonce, presumably embedded in the login form — **NOT VERIFIED** exactly where `$_SESSION['rand']` is first set, as that was outside this pass's scope) as an anti-replay/tamper check; if it doesn't match, `$data['password']` is deliberately set to the garbage string `'dfdasfsdfas'` so the subsequent `password_verify()` fails naturally rather than trusting an unvalidated payload.
- `User_model::login($mobile, $password)` re-hashes the password with SHA-512 then calls `password_verify()` against the stored `bcrypt`/`argon`-hashed value (double-hashing: SHA-512 first, then PHP's `password_hash()` algorithm) — `User_model.php:12-37`.
- `status=='0'` blocks login with a flash error (confirmed, resolves part of Unknowns item 8 above).
- On success: `fail_attempt` is reset to 0, five session variables are set (§16.2), `$this->session->sess_regenerate()` is called (session-fixation protection), and the user is redirected either to `user/resetpassword` (if `reset_code=='1'`, i.e. a forced password reset is pending) or `dashboard`.
- On failure: `fail_attempt` is incremented via `$this->db->set('fail_attempt', 'fail_attempt + 1', FALSE)`. **Confirmed: `fail_attempt` is never read or checked anywhere else in the codebase** (a full grep for `fail_attempt` across `User.php` finds only the reset-to-0 and increment sites) — it is tracked but never enforced. There is no account-lockout mechanism despite the counter's presence; the column is vestigial.

**VLE self-service login (CSC/OAuth)** — `User::connectLogin()`/`connectLogin1()` + `User::callback()` (`User.php:612-699`):
- `connectLogin()` generates a random numeric `state` (`rand(10000,99999)`), stores it in session (`connect_state`), and redirects to an external `AUTHORIZATION_ENDPOINT` (OAuth authorize URL, built from `CLIENT_ID`/`REDIRECT_URI`/`AUTHORIZATION_ENDPOINT`/`TOKEN_ENDPOINT`/`RESOURCE_URL`/`CLIENT_SECRET` constants). **These constants are not defined anywhere in this checkout** — confirmed by grepping the entire `application/` tree (including `config/constants.php`, the natural place for them) for any `define('CLIENT_ID', ...)`-style declaration; zero matches. As with the missing views (§0) and the missing `config.ini` (§16.6), the VLE OAuth login flow as it exists in this checkout is missing required configuration and would fatal on an undefined-constant error if invoked here — it presumably works only in a deployment where these are defined outside this repository (e.g. a deployment-specific config file not checked in). This does not affect any claim in this document about what the *code* does when these constants exist and hold correct values; it only means this specific checkout cannot execute that code path standalone.
- `connectLogin1()` is a near-duplicate that uses a **hardcoded, non-random `state='PMS'`** instead.
- `callback()` receives `code`/`state` from the query string, exchanges `code` for an access token against `TOKEN_ENDPOINT`, then fetches the user's profile from `RESOURCE_URL`.
  - **Confirmed security finding**: the `state` validation is present in the code but entirely commented out — `// if (!$state || $state != $this->session->userdata('connect_state')) { // exit('STATE mismatch'); } // unset($_SESSION['connect_state']);` (`User.php:643-646`). The `state` value generated at `connectLogin()`/`connectLogin1()` and stored in session is therefore **never actually checked** against the value returned in the callback — the OAuth flow has no live CSRF/state protection, only a disabled one.
  - **Confirmed architectural finding**: if `$state=='PMS'` (i.e. the request came via `connectLogin1()`), `callback()` does not log the user into this application at all — it base64-encodes the raw profile response and redirects to `https://api.pmsuryabijliyojna.in/user/checklogin?data=...`, an entirely unrelated external government scheme ("PM Surya Ghar" / solar rooftop subsidy scheme), and exits (`User.php:672-676`). **This confirms the Scholarship app's OAuth callback endpoint is shared/multiplexed as a generic relay for at least one other, unrelated external system** — not something a Scholarship-only migration would normally anticipate needing to account for.
  - On the normal (non-`PMS`) path: sets 5 session variables (§16.2, `USER_TYPE='VLE'` hardcoded), calls `sess_regenerate()`, then checks `User_model::checkdatainvletable($USER_ID)` (a `vle_user` table existence check) — if the VLE has no `vle_user` profile row yet, redirects to `user/vle_profile` (the profile-completion form, §16.1 also covers this method's own redundant guard, §0.4/§6.5); otherwise straight to `dashboard`.

### 16.2 Session variables — complete enumeration

Confirmed by grepping every `set_userdata` call across `controllers/` and `models/`:

| Variable | Set by | Scope/lifetime |
|---|---|---|
| `USER_ID` | `User.php:81` (staff login, `=users.id`), `User.php:684` (VLE, `=csc_id`) | session-long |
| `USER_TYPE` | `User.php:82` (staff, `=users.user_type` int), `User.php:692` (VLE, hardcoded string `'VLE'`) | session-long |
| `NAME` | `User.php:83`/`685` | session-long |
| `EMAIL` | `User.php:84`/`686` | session-long |
| `MOBILE` | `User.php:85` (staff **only** — VLE login never sets this) | session-long |
| `is_admin` | `User.php:691` (VLE only, hardcoded `0`) | session-long |
| `connect_state` | `User.php:615`/`629` (OAuth flow) | transient, meant to be checked-then-cleared, but never actually checked (§16.1) |
| `application_id` | `Scholarship.php:2125`/`2876` (transient, during preview/edit flows), `Applications.php:2109` (Sahayata module) | transient, request-flow scoped |
| `PAYMENT_BATCH_ID` | `Payment.php:146`/`152`/`222` (during `proceedpayment()`) | transient, request-flow scoped |

**Confirmed: `districtunion`, `samiti`, `circle`, and `district` are never stored in session anywhere in the codebase.** Every row-level scoping check (`Application_model::checkaccess()`, `getcountfordashboard()`, `getcountforpending()`, and the equivalents in `Dashboard.php`) re-fetches these values fresh from the `users` table on every single request via `User_model::getuserbyid($this->session->userdata('USER_ID'))` (confirmed at `Application_model.php:275`, `280`, `295`, and repeated at each of the four role branches in `checkaccess()`). **This means a geography reassignment (e.g. an admin moves a District Union user to a different district union) takes effect on that user's very next request — there is no stale-session-data window for these fields.** `USER_TYPE` itself, however, *is* cached in session and would remain stale until re-login if a user's `user_type` were changed while they had an active session — asymmetric with the geography fields.

### 16.3 How `USER_TYPE` is determined and used

Set once at login (§16.1/§16.2) from either the `users.user_type` column (staff) or hardcoded to the string `'VLE'` (CSC/OAuth). Every authorization decision in the entire application reads this single session value directly (`$this->session->userdata('USER_TYPE')`) — there is no derived "roles" collection, no multi-role support, and no distinction anywhere in the code between the numeric `USER_TYPE` values and a human label except via the several independently-hardcoded lookup arrays already documented in §2/§3 (e.g. `User.php:911`'s `$userstype` array, the three drifted `$statusarr` copies).

### 16.4 How `role_priviledge` is loaded and used — and why it does not enforce access control on writes

**Loading mechanism** (identical in the three places it's read): `SELECT * FROM role_priviledge WHERE role_id = <USER_TYPE>`, then flattened to a `permission_id` array. This exact query appears independently in `hooks/Visitor.php:29`, `layouts/default.php:15` (for menu visibility), and `User_model::checkUserAccess($priviledge)` (`User_model.php:182-193`, a single-permission existence check, itself never called anywhere in the codebase — confirmed by a full grep, another dead function).

**The single enforcement point**: `hooks/Visitor.php::check_permission()`, wired via `application/config/hooks.php` to CodeIgniter's `post_controller` hook (the *only* hook registered in this application). Full gate list (already documented in §5/§6.5/§16.6): `roles` controller (permission 4, dead — controller doesn't exist), `user::add_user`/`edit_user` (permissions 1/2), `beneficiary::*` (permissions 5/8/9/10/11/12/13/32/33, dead — controller doesn't exist), `statewisedata::index` (permission 34, dead — controller doesn't exist), `member` (permission 36). **No gate exists for `scholarship`, `applications`, `batch`, `payment`, `dashboard`, or `report`** — already established in the original research and re-confirmed by a fresh full read of `Visitor.php` in this pass.

**Critical finding, verified directly against the CI3 framework source (`system/core/CodeIgniter.php:506-546`)**: CI3's execution order is:
```
$EXT->call_hook('pre_controller');
$CI = new $class();                                    // controller __construct() runs (isLogin() checks etc.)
$EXT->call_hook('post_controller_constructor');         // unused — nothing registered at this hook point in this app
call_user_func_array(array(&$CI, $method), $params);    // <-- the ENTIRE requested method runs HERE, including all DB writes
$EXT->call_hook('post_controller');                      // <-- Visitor::check_permission() runs HERE, AFTER the method
if ($EXT->call_hook('display_override') === FALSE) { $OUT->_display(); }  // final HTTP response sent
```
Every write action in this codebase follows a "write, then call CI's `redirect()` helper" convention (confirmed pattern across `Scholarship.php`, `User.php`, `Member.php`, and every other controller read in this and prior passes). `redirect()` (`system/helpers/url_helper.php:533-566`) is exactly `header('Location: ...', TRUE, $code); exit;` — nothing more, no transaction awareness, no rollback mechanism. Because `exit` terminates the PHP process immediately, **a controller method that writes to the database and then redirects on success never returns control to `CodeIgniter.php`, so the `post_controller` hook — and therefore `Visitor::check_permission()` — never runs at all for that request.** The write has already committed by the time the (never-executed) permission check would have fired.

This was verified to matter concretely for two of the hook's five live (non-dead-controller) gates:
- `user::add_user`/`edit_user` (§6.5, corrected in this pass) — no in-method safeguard exists; any authenticated non-VLE session can create/edit users regardless of `role_priviledge`.
- `member::add`/`edit` (§16.6, new in this pass) — same pattern, no in-method safeguard; any authenticated non-VLE session can add/edit IC committee members.

It was also verified to be **harmless in the one case with a redundant in-method check**: `user::vle_profile` — `Visitor.php`'s own check for this action is separately dead for a different reason (an unreachable nested condition, §0.4), but `User::vle_profile()` itself independently re-checks `USER_TYPE=='VLE'` at the top of the method (`User.php:430-432`, correctly timed since it runs as the first statement of the method itself, before any write) — so no exploitable gap exists here despite the hook being ineffective.

**Confirmed unaffected by this finding: the entire Scholarship module.** `Scholarship.php`, `Batch.php`, `Payment.php`, `Dashboard.php`, `Report.php` have no `Visitor.php` gate at all (§5's finding, re-confirmed) — every access-control decision they make (Samiti/IC/DU/HQ/Circle/Account's `USER_TYPE==` checks in §1, `Application_model::checkaccess()`'s row-scoping) is evaluated **inline, inside the requested method itself**, at the correct point in execution, before any write. None of the status-transition logic documented in §1-§5 depends on the `post_controller` hook in any way, so none of it needed correction as a result of this finding. This is a genuinely reassuring, independently-verified confirmation that the core module this migration cares about was never resting on the broken mechanism in the first place.

**Laravel comparison**: Laravel's `Gate::authorize()` (used throughout the current `ScholarshipWorkflowController`, `UserManagementController`, etc.) runs as an explicit statement at the top of each controller action, executed synchronously before any subsequent code in that method — architecturally equivalent to the Scholarship module's own inline `USER_TYPE` checks, not to `Visitor.php`'s broken hook-based approach. **This is a genuine, positive difference, not a gap to replicate**: Laravel's permission checks for User Management (`UserPolicy`, gated via route middleware `can:users.create`/`can:users.update`) run *before* the controller method body at the routing/middleware layer — meaning Laravel actually enforces what legacy's `Visitor.php` only appeared to enforce. No migration action is needed here; this is flagged so that nobody mistakes legacy's apparent permission model (as literally written in `Visitor.php`) for its actual, enforced behaviour when using this document as a migration reference.

### 16.5 Access-denied flow / redirect logic

Every denial path found in this analysis — across `Visitor.php`, every controller's `isLogin()` check, and every in-method `USER_TYPE` check — uses the same primitive: CI's `redirect()` helper, almost always to `base_url()` (the dashboard/login landing page) with no error message passed through (no flash data set before most of these redirects — confirmed by reading `Visitor.php` in full, none of its `redirect(base_url())` calls set `session->set_flashdata()` first). A denied user is simply bounced to the home page with no explanation. The one exception found: login failures and inactive-account attempts *do* set a flash error message (`User.php:71-73`, `98-102`) before redirecting, and validation failures throughout the app (e.g. `add_user()`) set `validation_errors()` as flash data. There is no dedicated "403 Forbidden" view or distinct access-denied page anywhere in the application.

### 16.6 Login helper functions — confirmed dead code, no hidden business logic

Directly answering the "helper functions used globally" question: `application/config/autoload.php:92` autoloads only `url`, `form`, and `cookie` helpers globally. A full grep of every controller/model/library found exactly two explicit `$this->load->helper()` calls in the entire application: `Template.php`'s internal load of the stock `inflector` helper, and `Scholarship.php:46`'s load of `scheme` (already documented, §11). **None of `auth_helper.php`, `jwt_helper.php`, `database_helper.php`, `error_code_helper.php`, `rest_api_helper.php`, or `category_helper.php` is loaded anywhere, and none of their defined functions (`authorize()`, `generate_jwt_cookie()`, `check_jwt_cookie()`, `database_query()`, `createResourceRoot()`, `getParent()`/`makeTree()`, etc. — full function inventory confirmed by reading each file) is called anywhere in `controllers/`, `models/`, or `libraries/`.** `auth_helper.php` additionally depends on a `config.ini` file (`parse_ini_file(__DIR__.'/../../config.ini')`) that **does not exist in this checkout** — calling any of its functions would fatal immediately. These six files are confirmed dead, leftover boilerplate (most likely from a generic CodeIgniter REST-API/JWT starter kit that predates this application's actual login implementation) and contain no live business logic of any kind. The application's real authentication (§16.1) uses none of them.

**Custom library check**: `application/libraries/MY_Form_validation.php` (a CI3 core-class extension, auto-wired by CI3's `MY_`-prefix convention whenever `form_validation` is loaded) adds exactly one custom rule beyond the stock CI3 `Form_validation` class: `is_unique_update($str, $field)` — the standard "field must be unique except against the record currently being edited" pattern used by edit forms. No other custom validation logic exists in this library. `application/libraries/S3.php` and `Template.php` were already covered in the original research (generic S3 wrapper, apparently unused in favour of the AWS SDK's `S3Client`; single shared layout mechanism) and were not found to contain any additional business logic in this pass.

---

## 17. Layout & Global JavaScript — Additional Findings

Directly addresses "Part 2: Layout & Menu" beyond what §8 (Menu Structure) already covers. The menu enumeration itself (dynamic generation, visibility, permission/role checks, dashboard links) is unchanged from §8 and re-confirmed correct in this pass — findings below are the additional layout-level facts §8 did not cover.

- **No header/footer/nav partials exist.** `layouts/default.php` (311 lines) contains no `$this->load->view()` or `include()` calls of any kind (confirmed by a full-file grep) — it is one single, self-contained template. The footer is a trivial inline `<footer>` with static copyright text ("Copyright © {year} CSC, All rights reserved.", line 282) — no business logic.
- **No notification badge system exists.** The theme's `notification-sidebar.js` vendor script is included (`default.php:91`) but is never wired to any bell icon, badge element, or PHP-computed count variable anywhere in the layout (confirmed: no `badge`/`notification` markup exists in the file besides that one script include). It is decorative/inert theme boilerplate, not a live feature. (The per-role "pending count" banner referenced in §7.3's `dashboard_data.php` analysis is a *separate*, dashboard-page-specific element, not a global layout notification system.)
- **No global AJAX configuration exists.** No `$.ajaxSetup()`, no CSRF-token meta-tag/header wiring, no global error handler was found anywhere in `default.php`'s script section. Every AJAX call documented elsewhere in this document (e.g. §9/§10's cascading dropdown AJAX) is a bare, independently-written `$.ajax()`/`$.post()` call with no shared configuration.
- **Global inline JavaScript is limited to generic UI wiring**: a `$(document).ready()` block (`default.php:290-299`) that initializes jQuery UI's datepicker (format `yy-mm-dd`), DataTables (`.zero-configuration` class), and the Chosen enhanced-select plugin — no business logic.
- **A genuine, previously-undocumented business/UX rule**: `default.php:301-308` —
  ```javascript
  if(navigator.onLine) { } else { window.location.href='<?php echo base_url('user/logout');?>'; }
  ```
  This runs once, synchronously, on every authenticated page load (it is not an event listener for connectivity *changes*, just a one-time check of the browser's `navigator.onLine` flag at the moment the page finishes loading). **If the browser reports itself offline at page-load time, the user is immediately, unconditionally logged out** — no confirmation, no message. This is present on every page that uses the `default` layout (i.e. every authenticated screen in the application).
- **`app-assets/jsencrypt.js`** (RSA encryption for the login password field, §16.1) and **`app-assets/ui.js`** remain, as previously found, the only two non-vendor, application-specific JavaScript files in the entire codebase — re-confirmed in this pass, no additional app-specific JS files were found.

---

## Part 3 / Part 4 re-verification result

Every statement in §1 (Application Lifecycle), §2 (Status Matrix), §3 (Role Hierarchy), §4 (Circle), and §5 (Account) was re-checked against the §16.4 hook-timing finding and the full re-read of `Visitor.php`/`default.php`. **No correction was required to any of §1-§5.** The Scholarship module's entire access-control model — every `USER_TYPE==` check gating a status transition, every `Application_model::checkaccess()` row-scoping call — is evaluated inline, inside the requested controller method, before any write; none of it depends on the `post_controller` hook, and the menu's role/permission checks (`layouts/default.php`) are computed directly in the view via a fresh DB query, not via the hook either. The only corrections arising from this pass are §6.5 (User Management) and the new §6.6/§16.6 (Member Management), both outside the Scholarship module's own workflow logic but within its surrounding application — plus the new §16/§17 material, which is additive, not corrective, everywhere else.
