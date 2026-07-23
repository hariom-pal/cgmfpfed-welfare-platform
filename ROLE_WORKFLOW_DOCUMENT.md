# Scholarship Welfare Platform — Role & Workflow Reference

**Status:** Primary business reference for all future feature development.
**Scope:** Scholarship module only (`Scholarship.php`, `Dashboard.php`, `Batch.php`, `Payment.php`, `Report.php` in the legacy CI3 app at `/var/www/html/scholarship`, and their Laravel equivalents). The death-benefit "Sahayata" module (`Applications.php`) and the sibling Beema app are out of scope except where they explain a shared code path.
**Sources:** Legacy CI3 controllers/models read directly (line references below), `scholarship.sql` production dump, and the current Laravel codebase (`app/Domains/Scholarship`, `app/Services`, `app/Http/Controllers`, `config/legacy_authorization.php`).
**Last verified:** 2026-07-24, against the live application database.

---

## 1. Complete Role Hierarchy

| Role ID | Legacy `user_type` | Laravel `config('legacy_authorization.roles')` | Real production rows? | Notes |
|---|---|---|---|---|
| 1 | Super Admin | Super Admin | Yes | Also referred to as "HQ" in workflow-stage contexts — HQ is **not** a separate `user_type`, it is a status-stage label mapped onto Super Admin's `detail()` actions on statuses 11/12. |
| 2 | District Union | District Union | Yes | |
| 3 | Samiti | Samiti | Yes | |
| 4 | Investigation Commitee *(sic, legacy spelling)* | Investigation Commitee | Yes | "IC" throughout this document. |
| 5 | Circle | Circle | Yes | Also referred to as "CCF" in status labels (`status IN (6,7,8,10,12,14,16,18,20)`). Its live approval action (`verifyscholarship`, `USER_TYPE==5` branch) is **dead code in the scholarship module** — see §3 and §11. |
| 6 | *(no lookup row before this session — see §11)* | Account | 2 legacy rows only (`users.user_type=6`, ids 5 and 1028); zero `role_priviledge` rows | Real, functioning gate in code (`Scholarship::remove()`/`forward()`, both hardcoded `USER_TYPE=='6'`) but with no name and no permission-based menu/UI — effectively an undocumented internal role in the legacy system. Formalized as "Account" in this session (migration `2026_07_24_000100_add_account_role.php`). |
| — | `VLE` (string, not numeric) | VLE | Yes (self-service CSC-OAuth) | Not a `user_type` row at all — session value is the literal string `'VLE'`. Self-registers via CSC/OAuth, never created through the staff User Management screens. |
| — | "Phad" | — | n/a | Geography entity (tendu-leaf collection depot under a Samiti), **not a role** and not a `users` column. |

Hierarchy, by review order for a scholarship application (see §3 for the full lifecycle including all rejection branches):

```
VLE (applicant/self-service)
  └─ Samiti (3)
       └─ IC (4)
            └─ District Union (2)     ← HQ review happens on statuses DU recommends
                 └─ Super Admin (1) acting as "HQ"
                      └─ Account (6) — post-HQ payment queue
                           └─ Payment batch pipeline (system, no user_type gate) → status 99
```

Circle (5) sits outside this chain in practice (§3, §11) despite being defined in the enum/status-label ladder as a parallel "CCF" branch to IC/DU.

---

## 2. Responsibility of Each Role

| Role | Responsibility | Key legacy actions |
|---|---|---|
| **VLE** | Village Level Entrepreneur — the applicant-facing self-service identity. Creates applications, pays the ₹50 wallet fee to submit, resubmits after a non-permanent rejection, deletes drafts/permanent-rejects. | `Scholarship::add/preview/payment/success/edit/delete` |
| **Samiti (3)** | First-line reviewer. Verifies tendu-patta collection data (3 years of quantity/TP-card entries), uploads `phadbookfile`, recommends/rejects/permanently-rejects. Scoped to its own `samitiname`. | `Scholarship::detail()` USER_TYPE==3 branch |
| **IC (4)** | Investigation Committee — batches Samiti-recommended applications (`addbatch`), then verifies each batch (`verifyscholarship`) with a MoM (Minutes of Meeting) file upload. Recommend/reject/permanent-reject per row. | `Scholarship::addbatch/verifyscholarship` |
| **Circle (5)** | Defined as the next reviewer after IC in the status ladder ("CCF"), scoped by the district unions under its circle. In the *scholarship* module its actual approval branch (`verifyscholarship` USER_TYPE==5, gated on `status=='6'`) is unreachable because nothing ever sets `status=6` for scholarships (§11). Functionally, Circle today only has dashboard/report **visibility** over its district unions' applications, not an approval action. | `Dashboard.php` circle scoping; `Scholarship::verifyscholarship` (dead branch) |
| **District Union (2)** | Two distinct functions: (a) marks IC/Circle-recommended applications "documents submitted" (`detail()`, status 5→11 or 8→12), which is a completeness pass-through, not a substantive decision; (b) a second, independently-coded batch-approval path (`Batch::view()` POST) that also writes 11/12 but has a bug that means it can only ever write 11 (see §11). Scoped to its own `districtunion` (with the special DU-5/32 merged-jurisdiction rule, see below). | `Scholarship::detail()` USER_TYPE==2; `Batch::view()` |
| **Super Admin (1) / "HQ"** | Final substantive review of DU-recommended applications (status 11/12 → 13/25 reject, or 15/16 recommend-for-payment). Also personally records ad-hoc payment results (status 15/16 → 17/18/19/20/26) and runs the bulk "recommend for payment" shortcut. Only role with unrestricted visibility (no scoping). Owns Payment/Report menu access. | `Scholarship::detail()` USER_TYPE==1 branches (two of them); `Scholarship::pending()` bulk action; `Payment.php` (ungated, but menu-restricted to role 1) |
| **Account (6)** | Post-HQ payment-queue role. `remove()` reverts a "Final for Payment" (28) application back to "Recommended for Payment" (15); `forward()` advances 15/16 to 28 ("Final Application for Payment"), i.e. the last checkpoint before the AXIS bank payment-file is generated. Has no menu, no `role_priviledge` rows, and no display name in legacy — purely a hardcoded `USER_TYPE=='6'` gate. | `Scholarship::remove()/forward()` |
| **(system, no role)** | `Payment::proceedpayment()`/`finishpayment()` build the AXIS bank payment-instruction file and move status 28 → 99. Neither method has a `USER_TYPE` check — see the access-control gap in §11. | `Payment.php` |

**"DU 5/32 merged jurisdiction" rule** (appears independently in `Scholarship.php`, `Dashboard.php`, and `Application_model.php`): wherever a District Union's or Circle's own `districtunion` value is `5` or `32`, its visibility scope is expanded to `districtunion IN (5, 32)` instead of just its own value. This is preserved in Laravel's `DataScopeService::districtUnionScope()`/`circleDistrictUnionScope()`.

---

## 3. Application Lifecycle (every path, including every rejection branch)

Status codes below are the legacy `application.status` enum values, identical to Laravel's `ScholarshipApplicationStatus` enum (`app/Domains/Scholarship/Enums/ScholarshipApplicationStatus.php`).

### 3.1 Creation & submission (VLE)
```
(new)
  → add()                       status = 0   "Pending / Under Samiti Verification"
  → preview()                   status stays 0 (confirms & locks in before payment)
  → payment() → success()       status = 1, payment_txn_status = 1   (wallet fee paid)
  → payment() → PG fail/cancel  status UNCHANGED, payment_txn_status stays 0 (orphaned draft)
```
`add()` is hard-blocked after 2025-01-28 (`date("Y-m-d") > '2025-01-28'` dies immediately) — a scheme-closure cutoff baked directly into the controller.

### 3.2 Samiti review (status 0/1 → …)
```
approve=1   → 4   "Recommended by Samiti"
approve=2   → 21  "Permanently Rejected by Samiti"   (terminal; VLE may only delete, not resubmit)
approve=0   → 2   "Rejected by Samiti"               (VLE may resubmit via edit())
```
Blocked if any of the 3 years' tendu-collection quantity < 500 while approving.

### 3.3 IC review (status 4 → …, via `addbatch()` + `verifyscholarship()`)
```
approve            → 5   "Recommended by IC"
reject             → 3   "Rejected by IC"            (VLE may resubmit)
permanent reject   → 22  "Permanently Rejected by IC" (terminal)
```

### 3.4 Circle/CCF review — **dead path for scholarships** (status 6 → …)
```
approve            → 8   "Recommended by CCF"
reject             → 7   "Rejected by CCF"
permanent reject   → 23  "Permanently Rejected by CCF"
```
Unreachable: nothing in `Scholarship.php` ever sets `status=6`. Only the unrelated Sahayata module (`Applications.php`) implements a VLE "appeal" action that sets status 6. See §11.

### 3.5 District Union review (status 5 or 8 → …, via `detail()`, and independently via `Batch::view()`)
```
status==5, input=1   → 11  "Recommended by DU"
status==8, input=1   → 12  "Recommended by DU via CCF"
status==5, input=0   → 9   "Rejected by DU"           (VLE may resubmit)
status==8, input=0   → 10  "Rejected by DU via CCF"   (VLE may resubmit)
input=2 (any)        → 24  "Permanently Rejected by DU" (terminal)
```
`Batch::view()`'s independent DU-approve path has a variable-name typo (`$applicaiton` vs `$application`) that means the `applicationstatus=='8'` check never fires, so that path can only ever write `11`, never `12` — a real, reportable bug (§11).

### 3.6 Super Admin / "HQ" review (status 11 or 12 → …, via `detail()`)
```
status==0 input      → 13  "Rejected by HQ"            (VLE may resubmit)
status==2 input      → 25  "Permanently Rejected by HQ" (terminal)
approve, from 11     → 15  "Recommended for Payment"
approve, from 12     → 16  "Recommended for Payment via CCF"
```

### 3.7 Super Admin payment-result entry (status 15 or 16 → …, via `detail()`)
```
paymentstatus=Failed/Reject, from 15   → 17  "Payment Failed"
paymentstatus=Failed/Reject, from 16   → 18  "Payment Failed via CCF"
paymentstatus=Reject (specifically)    → 26  "Permanently Rejected by Accounts"
paymentstatus=Completed, from 15       → 19  "Payment Completed"
paymentstatus=Completed, from 16       → 20  "Payment Completed via CCF"
```
Also independently reachable via `Payment::uploadCsv()` (bulk UTR CSV reconciliation, **ungated by `USER_TYPE`**) using the same 15/16→17/18/19/20 logic.

### 3.8 Account role (status 15/16/28 → …)
```
forward()   status 15 or 16 → 28   "Final Application for Payment"
remove()    status 28       → 15   "Removed from Available for Payment" (reverts to 15, i.e. undo)
```

### 3.9 System payment-file pipeline (status 28 → 99, no `USER_TYPE` gate)
```
Payment::proceedpayment()   builds payment_batch / payment_batch_application rows (no status change)
Payment::finishpayment()    writes AXIS bank file, payment_batch.status=1, application.status=99
```
Status 99 is an **orphaned sentinel** — not in the `application_status` DB enum (`'0'..'28'`) and not in the legacy status-label array (`$statusarr[99]` is an out-of-range lookup). No `application_status` audit row is written for this transition (the only status write in the app that skips the audit table). See §11.

### 3.10 Bulk shortcut (Super Admin only, status → 15 unconditionally)
`Scholarship::pending()`'s bulk-approve checklist writes `status=15` for every selected application **regardless of whether it came from an 11 or 12 lineage**, silently collapsing the via-CCF distinction that the single-record path in §3.6 correctly preserves. Also, its `redirect()` call sits inside the loop, so in practice only the first checked application is ever committed. Both are real bugs, documented (not fixed) — see §11.

### Terminal (closed) states
`21` (Samiti), `22` (IC), `23` (CCF, unreachable), `24` (DU), `25` (HQ) — permanent rejects, deletable by VLE, never resubmittable.
`19`/`20` (Payment Completed) is also effectively terminal (success case).

### Resubmittable (non-permanent reject) states
`2`, `3`, `9`, `10`, `13` (and their via-CCF counterparts `7`* [dead], `10`/`14` not directly listed but implied by the ladder) — VLE's `edit()` unconditionally resets any of these back to `1` regardless of which stage rejected it.

---

## 4. Status Matrix

| Code | Legacy Label | Meaning | Responsible Role (to act) | Typical Next | Typical Previous | Terminal? |
|---|---|---|---|---|---|---|
| 0 | Pending | New application, fee unpaid or awaiting Samiti | Samiti | 1, 2, 4, 21 | — (created) | No |
| 1 | Resubmitted | Fee paid / resubmitted after non-permanent reject | Samiti | 2, 4, 21 | 0, or any reject code via `edit()` | No |
| 2 | Rejected by Samiti | Returned to VLE for correction | VLE (resubmit) | 1 | 0/1 | No |
| 3 | Rejected by IC | Returned to VLE for correction | VLE (resubmit) | 1 | 4 | No |
| 4 | Recommended by Samiti | Awaiting IC | IC | 3, 5, 22 | 0/1 | No |
| 5 | Recommended by IC | Awaiting DU | District Union | 9, 11, 24 | 4 | No |
| 6 | Appealed by Beneficiary | *Dead in scholarship module* | Circle (unreachable) | 7, 8, 23 | never set | No |
| 7 | Rejected by CCF | *Dead* | VLE (unreachable) | 6 | 6 | No |
| 8 | Recommended by CCF | *Dead* | District Union (unreachable input) | 10, 12, 24 | 6 | No |
| 9 | Rejected by DU | Returned to VLE | VLE (resubmit) | 1 | 5 | No |
| 10 | Rejected by DU via CCF | Returned to VLE | VLE (resubmit) | 1 | 8 (dead) | No |
| 11 | Recommended by DU | Awaiting HQ | Super Admin | 13, 15, 25 | 5 | No |
| 12 | Recommended by DU via CCF | Awaiting HQ | Super Admin | 14, 16, 25 | 8 (dead) | No |
| 13 | Rejected by HQ | Returned to VLE | VLE (resubmit) | 1 | 11 | No |
| 14 | Rejected by HQ via CCF | Returned to VLE | VLE (resubmit) | 1 | 12 (dead) | No |
| 15 | Recommended for Payment | Awaiting Account | Super Admin / Account | 17, 19, 26, 28 | 11 | No |
| 16 | Recommended for Payment via CCF | Awaiting Account | Super Admin / Account | 18, 20, 26, 28 | 12 (dead) | No |
| 17 | Payment Failed | Needs re-processing | Super Admin (retry via `uploadCsv`/`detail`) | 15 (manual re-recommend) | 15 | No |
| 18 | Payment Failed via CCF | Needs re-processing | Super Admin | 16 | 16 | No |
| 19 | Payment Completed | Success | — | — | 15/17 | **Yes** |
| 20 | Payment Completed via CCF | Success | — | — | 16/18 | **Yes** |
| 21 | Permanently Rejected by Samiti | Closed | VLE (delete only) | — | 0/1 | **Yes** |
| 22 | Permanently Rejected by IC | Closed | VLE (delete only) | — | 4 | **Yes** |
| 23 | Permanently Rejected by CCF | Closed, unreachable | VLE (delete only) | — | 6 (dead) | **Yes** |
| 24 | Permanently Rejected by DU | Closed | VLE (delete only) | — | 5/8 | **Yes** |
| 25 | Permanently Rejected by HQ | Closed | VLE (delete only) | — | 11/12 | **Yes** |
| 26 | Permanently Rejected by Accounts | Closed | VLE (delete only) | — | 15/16 | **Yes** |
| 27 | *(Laravel-only, "Account Details Updated by HQ")* | HQ corrected bank details on a returned-by-accounts application | Super Admin | 1 (Laravel) | 26 | No |
| 28 | Final Application for Payment | Queued for bank file | Account / system | 15 (via `remove()`), 99 (via batch pipeline) | 15/16 (`forward()`) | No |
| 99 | *(orphaned sentinel — not in DB enum)* | Sent to AXIS bank payment file | — | 17/18/19/20 (via `uploadCsv`, lineage often lost — §11) | 28 | Effectively yes (no further legacy code path advances it cleanly) |

Laravel formalizes status 27 (`AccountDetailsUpdatedByHQ`) as an explicit resubmission-from-accounts-reject path that legacy does not name distinctly — see §11.

---

## 5. Role vs Status Matrix (who can act on what)

| Status | VLE | Samiti (3) | IC (4) | Circle (5) | DU (2) | Super Admin (1) | Account (6) |
|---|---|---|---|---|---|---|---|
| 0, 1 | create/pay | **act** | – | – | – | – | – |
| 2, 3, 9, 10, 13 | **resubmit** | – | – | – | – | – | – |
| 4 | – | – | **act** | – | – | – | – |
| 5 | – | – | – | – | **act** | – | – |
| 6 (dead) | – | – | – | *(act, unreachable)* | – | – | – |
| 8 (dead) | – | – | – | – | **act (unreachable input)** | – | – |
| 11, 12 | – | – | – | – | – | **act** | – |
| 15, 16 | – | – | – | – | – | **act** (payment result) | **act** (`forward`) |
| 17, 18 | – | – | – | – | *(visible, follow-up)* | **act** (re-recommend) | – |
| 21–26 | **delete** | – | – | – | – | – | – |
| 28 | – | – | – | – | – | – | **act** (`remove`) |
| 99 | – | – | – | – | – | *(only via `uploadCsv`, ungated)* | – |

Visibility (read-only) differs from action rights and is scoped per §2/§6: Samiti sees only its own `samitiname`; DU/IC see only their own `districtunion` (with the 5/32 merge); Circle sees all district unions under its circle; Super Admin sees everything; Account sees only the finance-stage subset (15/16/17/18/28/99 in Laravel's `DataScopeService::financeStatuses()`).

---

## 6. Dashboard Mapping

Legacy `Dashboard::dashboard_data($scheme)` computes one giant per-district-union bucket row via a single raw SQL aggregate (`Dashboard.php:58-64`), always filtered to `payment_txn_status='1'` (i.e. drafts that never paid the wallet fee are excluded from every dashboard count):

| Bucket | Statuses | Represents | Responsible Role |
|---|---|---|---|
| `sam` / `sampen` / `samrec` / `samrej` | all / 0,1 / 3-19(rec chain) / 2 | Samiti total/pending/recommended/rejected | Samiti |
| `ic` / `icpen` / `icrec` / `icrej` | 3-19(rec chain) / 4 / 5-19 / 3 | IC total/pending/recommended/rejected | IC |
| `ccf` / `ccfpen` / `ccfrec` / `ccfrej` | 6-20(via-ccf) / 6 / 8-20 / 7 | Circle/CCF (dead in practice) | Circle |
| `du` / `dupen` / `durec` / `durej` | 5,8-20 / 5,8 / 11-20 / 9,10 | DU total/pending/recommended/rejected | DU |
| `hq` / `hqpen` / `hqrec` / `hqrej` | 11-20 / 11,12 / 15-20 / 13,14 | HQ (Super Admin) total/pending/recommended/rejected | Super Admin |
| `paydone` / `payfail` | 19,20 / 17,18 | Final payment outcome | — |
| `csamitiprej` … `caccprej` | 21…26 | The six permanent-reject counters, one per role | all |

Role-scoping appended to the same query: VLE → `added_by = <user>`; Samiti(3) → `samitiname = <user.samiti>`; DU/IC(2,4) → `districtunion = <user.districtunion>` (5/32 merge rule); Circle(5) → `districtunion IN (<all DUs under this circle>)`.

A second, role-specific "your pending work" badge (`getcountforpending()`) is computed separately per role:

| Role | `pendingstatus` shown | Statuses counted |
|---|---|---|
| VLE | `2,3,9,10` | resubmit-needed |
| Samiti (3) | `0,1` | new + resubmitted |
| IC (4) | `4` | Samiti-recommended awaiting IC |
| DU (2) | `5,8,17,18` | IC/CCF-recommended awaiting DU, **plus payment-fail statuses surfaced for follow-up** |
| Circle (5) | `6` | appealed (dead) |
| Super Admin (1) | *(none — uses `hqpending` 11/12 bucket instead)* | — |

**Laravel equivalent** (`DashboardController`, `ScholarshipRepository::filteredQueryFor()`): consolidated into a single per-user scoped query with named status buckets — `pending` (`underProcessValues()`), `pending_vle` (status 0 with no wallet payment), `recommended` (`approval_state=recommended`), `rejected` (`rejectedValues()`), `completed` (`completedValues()`), `payment_failed` (`failedValues()`) — driven by `DataScopeService::applyScholarshipVisibility()` for role scoping rather than duplicating the SQL three times as legacy does (`Dashboard.php`, `Application_model.php`, and `Dashboard::export()` each independently re-implement the same scoping logic). This is a genuine, positive consolidation, not a behavior change — see §11.

Master-data cards (Schemes/Courses/Categories/Districts/District Unions/Samitis, `masters.manage`-gated) and Academic Sessions have no legacy equivalent; they are new administrative conveniences layered on top of the same role-scoped application counts.

---

## 7. Menu Mapping per Role

### Legacy (`application/views/layouts/default.php`)
| Item | Gate |
|---|---|
| Scholarship Dashboard / Insurance Dashboard | everyone |
| Add Application / Incomplete Application | `USER_TYPE=='VLE'` only |
| Application (All/Pending/Processing/Completed/Rejected/Failed) | everyone (row visibility enforced inside each controller method, not the menu) |
| Batches | `role_priviledge` permission **38** (roles 1, 2, 4, 5 in production data — **not** Samiti) |
| Payment (Pending/Completed/Failed/Upload UTR), Report → Samiti Wise Count | hardcoded `USER_TYPE=='1'` only |
| Logout | everyone |
No menu entries exist anywhere for User/Society/Member/Scheme/Relation management — those screens are reachable only by typing the URL, and in this specific checkout their views are missing entirely (§9), so they would fatal if invoked.

### Laravel (`app/Services/MenuBuilder.php`, current, post this session's Module 1 cleanup)
Order: **Dashboard → User Management → Masters → Scholarship Applications → Beema → Reports → Workflow Batches → Settings**, each item independently gated:

| Item | Gate |
|---|---|
| Dashboard | always |
| User Management | `users.view` ability (permissions 1 or 2) |
| Masters | `masters.manage` ability (role 1 only) |
| Scholarship Applications (+ status children, + "Add Application" for VLE) | `applications.view` ability (roles 1,2,3,4,5,6,VLE) |
| Beema (external link) | always |
| Reports | `reports.view` ability (role 1, 5, or permissions 16/34/39) |
| Workflow Batches | permission **38** directly (`PermissionService::has`, mirrors legacy's Batches gate — roles 1, 2, 4, 5 in production data; Account/6 correctly has **no** row for this permission, matching legacy exactly, so it never sees this menu item either — see §11) |
| Settings → CSV Export Configuration | `masters.manage` ability |

The legacy Super-Admin-only "Payment" placeholder submenu (Pending/Completed/Failed/Upload UTR, never implemented in Laravel — payment results are recorded via the Workflow Batches screen's `paymentResult()` action instead) and the "Other Modules" wrapper it lived in were removed in this session (Module 1) since they contained no working routes; Workflow Batches was promoted to a top-level item since it *is* a real, tested feature.

---

## 8. User Management

### Legacy (CI3, confirmed via the sibling `beema` app's `add_user.php`/`edit_user.php` — scholarship's own views are missing in this checkout, see §9)

**Create user** fields: Name, Email, Mobile, User Type (role select), Status, then a District→District Union→Samiti cascade for every role except Circle, which instead gets Circle→District Union (District hidden); Samiti field appears only for role 3; Password + confirm.

**Edit user** fields: Name/Email/Mobile/Role are rendered as read-only/disabled (never resubmitted — HTML `disabled` inputs are not sent on submit); District/Circle/District Union/Samiti **remain editable**; Password field is hidden entirely when a user is editing their own account; Status remains editable.

**Gating** (`Visitor.php` hook): `add_user` requires permission **1**; `edit_user` requires permission **2**. Only Super Admin holds both in production `role_priviledge` data.

**Permission model**: `role_priviledge` (role_id, permission_id) — a flat many-to-many; no per-user override, no time-bound grants.

### Laravel (post this session's Modules 2/3/4 work)
- `App\Services\UserManagementService::ASSIGNABLE_ROLES = [2, 3, 4, 5, 6]` — Super Admin (1) and VLE are excluded from staff creation/edit, matching legacy (Super Admin isn't self-service-created; VLE is CSC-provisioned, never through this screen).
- `resources/views/users/_form.blade.php` replicates the exact legacy field set and conditional visibility: District field visible/required for all roles except Circle; Circle field visible/required only for Circle; District Union always required, filtered client-side by whichever of District/Circle is active; Samiti visible/required only for role 3, filtered by the selected District Union.
- Create mode: Name/Email/Mobile/Role are editable inputs. Edit mode: Name/Email/Mobile/Role are rendered `disabled` (read-only), matching legacy; District/Circle/District Union/Samiti/Status remain editable; Password field is present but optional on edit ("leave blank to keep the current password") rather than being conditionally hidden for self-edit — a minor, intentional simplification (self-edit-hides-password is a UX nicety, not a security boundary, since the field is optional either way) — see §11.
- `StoreUserRequest`/`UpdateUserRequest` validation: `district_id` required unless role=5 (Circle); `circle_id` required if role=5; `samiti_id` required if role=3; role restricted to `Rule::in(ASSIGNABLE_ROLES)` rather than a DB `exists:` check (avoids the seeder/`RefreshDatabase` fragility documented in this session's test suite).
- `UserManagementService::geographyAttributes()` populates both the legacy scalar columns (`district`, `districtunion`, `samiti`, `circle`) — read by `DataScopeService` for row-level visibility — and the modern FK columns (`district_union_master_id`, `samiti_master_id`, `circle_master_id`), keeping both in sync on every create/update, matching legacy's flat-scalar model while still supporting the modern relational Masters.
- `UserPolicy`: `viewAny`→`users.view`; `create`→`users.create`; `update`→`users.update` **and** target is neither Super Admin nor VLE (mirrors the "Super Admin/VLE aren't editable here" legacy convention, which legacy enforces only by omission — there's no `edit_user` screen path for those roles at all).
- Role display everywhere (index table, edit-mode read-only field) now reads from `RoleService::name()` (config-driven) rather than the `UserType` Eloquent relation, avoiding a dependency on the `user_type` lookup table being seeded — a lesson learned earlier this session (`exists:`/relation-based lookups against seeder-only tables silently fail under `RefreshDatabase` in tests).

---

## 9. Payment Flow

```
Super Admin recommends (11/12 → 15/16)   "Recommended for Payment"
      │
      ├─ Account.forward()  (15/16 → 28)   "Final Application for Payment"
      │        │
      │        ├─ Account.remove()  (28 → 15)   undo / send back to queue
      │        │
      │        └─ Payment::proceedpayment($scheme)   groups status-28 rows into payment_batch
      │                   │
      │                   └─ Payment::finishpayment()
      │                             writes fixed-width AXIS bank file to /data/AxisSnorkel/In/
      │                             payment_batch.status = 1
      │                             application.status = 99   (NO application_status audit row)
      │
      └─ Super Admin.detail() payment-result entry (15/16 → 17/18/19/20/26)
                 OR
         Payment::uploadCsv()  bulk UTR-reconciliation CSV, same 17/18/19/20 targets
                 (checks "current status==16" for via-CCF branch — breaks once status is 99, see §11)
```

Amount computation: a scheme/class/education-year amount matrix is **independently duplicated three times** — `Scholarship::exportpaymentfile()`, `Payment::proceedpayment()`, and the shared `getAmount()` helper (`application/helpers/scheme_helper.php`) — all of which must be kept in sync manually in legacy. `Scholarship::updatepayment()` allows a manual amount override, validated against `getAmount()`, logged to `update_amount`.

**Access control gap** (documented, not fixed — see §11): none of `Payment::pending()/completed()/failed()/uploadCsv()/proceedpayment()/finishpayment()` have a `USER_TYPE` check in code; they are restricted only by the menu being Super-Admin-only and by nobody else knowing the URL.

**Laravel equivalent**: `ScholarshipWorkflowController::paymentBatch()` (creates a `ScholarshipWorkflowBatch`, `workflow.action` ability-gated — roles 1,2,3,4,5,6 or permissions 6/20/21/27/28/38, a real authorization check unlike legacy's ungated controller) and `paymentResult()` (records success/failure with reference/reason, same ability gate). There is no Laravel equivalent yet of the AXIS bank-file generation or CSV-based UTR bulk reconciliation — those remain legacy-only, undocumented as a migration gap rather than assumed equivalent.

---

## 10. Document Flow

Legacy resolves documents per application from the `application_files` table (columns: `application_id`, `filetype`, `filepath`/S3 key, `status`) via `AwsS3upload_model::getmyfile()`, keyed by a fixed set of `filetype` strings written during `Scholarship::add()`/`edit()`'s upload handling. There is **no `application.passbook` column** — the phrase "Front Passbook" in the UI is link caption text, not a distinct document type; see the Module 5 finding below.

| `filetype` (document_type in Laravel) | Who uploads | When | Mandatory? | Applies to | Notes |
|---|---|---|---|---|---|
| `tpcard` | VLE | at creation | Yes | all schemes | Sangrahak (collector) card |
| `aadharcard` | VLE | at creation | Yes | all schemes | Student's Aadhaar |
| `haadharcard` | VLE | at creation | Yes | all schemes | Head of family's Aadhaar |
| `admission_copy` | VLE | at creation | Yes | all schemes | Marksheet copy |
| `head_passbook` | VLE | at creation | Yes | **schemes 1 & 2 only** | Head-of-family bank passbook (photo + bank details page). Legacy's `detail_scholarship.php` renders exactly one passbook slot for these schemes, whose link text happens to read "View Front Page of Passbook" — a caption, not a second document. |
| `passbook` | VLE | at creation | Yes | **schemes 3 & 4 only** | Student's own bank passbook. Never created for schemes 1/2 — the scheme-1/2 upload branch in `Scholarship.php` never writes this `filetype`. |
| `admission_receipt` | VLE | at creation | Yes | **schemes 3 & 4 only** | |
| `phadbookfile` | Samiti | at Samiti review | Yes (blocks approval if missing) | all schemes | Phad (collection depot) book scan |
| `momfile` | IC | at batch verification | Yes (blocks batch verify) | all schemes | Minutes of Meeting, stored on `application_batch`, not per-application |

**Root cause of the Module 5 investigation ("Front Passbook shown in CI3 but missing in Laravel"):**
Laravel's `ScholarshipViewModelService::productionDocumentLabels()` had been incorrectly emitting **both** a `passbook` label and a `head_passbook` label for scheme-1/2 applications. Verified against real production data (`php artisan tinker`): all 17,589 scheme-1/2 applications have **zero** `passbook`-typed rows in `application_files` (structurally never created) while 17,586/17,589 have `head_passbook`; the inverse holds for the 2,434 scheme-3/4 applications (100% `passbook`, 0% `head_passbook`). The phantom `passbook` slot for schemes 1/2 always rendered "Not uploaded" because no such document could ever exist for those schemes. **Fixed** in this session (`app/Services/ScholarshipViewModelService.php`) by removing the erroneous `passbook` label from the scheme-1/2 branch, leaving only the correct `head_passbook` label. No database or storage changes were needed — this was purely a label-generation bug, not a data-loss or storage-path bug.

---

## 11. Known Differences (Legacy vs Laravel)

These are disclosed, reasoned decisions or pre-existing legacy defects — not silent redesigns. Development-rule compliance: none of the underlying business rules were changed; Laravel either (a) faithfully replicates a legacy behavior, (b) consolidates duplicated legacy logic into one place without changing outcomes, or (c) knowingly declines to replicate a legacy **defect**, each flagged below.

1. **Circle/CCF workflow branch (statuses 6,7,8,10,12,14,16,18,20) is dead code in legacy scholarship, and Laravel does not implement it for new applications.** Confirmed independently by this session's research: nothing in `Scholarship.php` ever sets `status=6`; the "appeal" action that does exists only in the unrelated Sahayata module. Laravel's `ScholarshipApplicationStatus` enum keeps these cases (commented "Source-system CCF workflow states retained for migrated applications. New applications must never enter these states.") purely so historically migrated records display correctly — this predates this session and is independently validated as correct by the fresh research, not a gap introduced now.

2. **Account role (6) formalized — permissions verified to hold zero invented grants.** Legacy has it as a bare `USER_TYPE=='6'` gate with no `user_type` lookup row and zero `role_priviledge` rows (2 real users only; confirmed by directly parsing the `role_priviledge` INSERT in `scholarship.sql` — permission 38 there belongs only to roles 1, 2, 4, 5, never 6). This session added `user_type` row 6 ("Account") and made it selectable in Create/Edit User. Its access is granted through exactly one mechanism: role-membership in the `applications.view`/`applications.submit`/`applications.documents.view`/`workflow.view`/`workflow.action` abilities (`config/legacy_authorization.php`), which is a direct translation of legacy's hardcoded `USER_TYPE=='6'` check in `Scholarship::remove()`/`forward()` — a role-based code gate, not a `role_priviledge` permission grant, and therefore a different mechanism from "permissions" in the literal legacy sense. It also reuses `DataScopeService`'s pre-existing but previously-unreachable `workflow_stage=accounts`/finance-status scoping (written in an earlier session, never wired to any ability until now).
   An earlier draft of this migration additionally granted role 6 `role_priviledge` permission 38 ("Manage Batch") for Workflow Batches menu parity with roles 1/2/4/5 — that was a genuine invented permission with no legacy basis and was caught and removed during a follow-up validation pass (migration `2026_07_24_000200_remove_invented_account_role_permission.php`). Account now correctly has **zero** `role_priviledge` rows, matching legacy exactly, and consequently sees **no menu items at all** (no Workflow Batches, no Payment, no Reports) — exactly mirroring legacy, where Account has no menu entry point and can only reach its two actions via a directly-typed URL. The underlying route access (`Gate::authorize('workflow.action')`) still works via the role-membership mechanism above, so Account can still functionally reach the Laravel equivalent of `forward()` (the `RecommendedForPayment:recommend` → `FinalApplicationForPayment` transition in `ScholarshipService::nextStatus()`) — it simply has no menu link to it, same as legacy.
   **Known gap, not fixed:** legacy's `remove()` (a 28→15 revert/undo of `forward()`) has no Laravel equivalent at all — `ScholarshipService::nextStatus()`'s transition map has no reverse transition from `FinalApplicationForPayment`. This is a pre-existing feature gap (see Recommendation 3), not a permissions issue.

3. **User Management field parity was corrected mid-session.** An earlier pass in this session had simplified the Create/Edit User form to a flat District Union picker with no District field, which does not match legacy's District→DistrictUnion→Samiti cascade (confirmed via `beema/application/views/add_user.php`/`edit_user.php`, the best-available proxy since scholarship's own `add_user.php`/`edit_user.php` views are missing from this checkout — see §9's view-completeness finding). This was corrected in this session's Module 4 work: the District field and its cascade were re-added.

4. **Edit-mode password field:** legacy hides the password field entirely when a staff user edits their *own* account; Laravel always shows it but makes it optional ("leave blank to keep current password") regardless of whose account is being edited. Functionally equivalent (nothing forces a password change either way) but not pixel-identical UX — flagged, not fixed, since it is not a business-rule difference.

5. **Legacy defect NOT replicated — `Batch::view()`'s DU-approve typo.** The DU batch-approval path in `Batch.php:192` references an unassigned variable `$applicaiton` (typo for `$application`), so its via-CCF branch (`applicationstatus=='8' → status=12`) can never fire — it always writes `11`. Laravel's consolidated `ScholarshipWorkflowController::action()`/`ScholarshipService::transition()` correctly branches on the actual prior status. This is a legacy bug, not a business rule; per the task's instruction to document (not silently fix) real differences, it is called out here rather than replicated.

6. **Legacy defect NOT replicated — bulk "recommend for payment" collapses via-CCF lineage and only processes the first row.** `Scholarship::pending()`'s bulk action (a) forces every selected application to status 15 even if it should be 16, and (b) calls `redirect()` inside its own loop, so only the first checked application in a multi-select batch is ever actually committed. Laravel has no direct equivalent of this specific bulk-shortcut screen; its `paymentBatch()`/batch actions process the full submitted list and preserve status lineage. Documented as a known legacy defect, intentionally not reproduced.

7. **Status 99 is an orphaned/undocumented sentinel in legacy** (not in the `application_status` DB enum, no audit row written, out-of-range in the legacy status-label array, and it breaks `Payment::uploadCsv()`'s via-CCF lineage detection once reached). Laravel's enum formally documents status 99 (`PaymentBatchSubmitted`) with a real label and workflow-state/stage mapping, and (new, status 27 `AccountDetailsUpdatedByHQ`) gives a named, resubmit-eligible path for HQ-corrected bank details after an Accounts-stage permanent reject (26) — legacy has no equivalent named state for this, it simply falls back to unstructured manual intervention. This is Laravel formalizing an ambiguous/underspecified legacy state, not changing what data means.

8. **Access-control gap NOT replicated as a gap.** Legacy's `Payment.php` (all actions) and `Society.php`/`Report.php` have **no `USER_TYPE` check at all** — any logged-in staff account (Samiti, IC, etc.) could hit `/payment/uploadcsv` or `/payment/finishpayment` directly; only the Super-Admin-only menu item hides it socially. Laravel's `ScholarshipWorkflowController` enforces `Gate::authorize('workflow.action')` (role/permission-checked) on every mutating action — a stricter, correct authorization boundary, not a business-rule change (nobody's legitimate access is reduced; the gap being closed was never an intended permission, just an oversight).

9. **Dashboard/visibility scoping consolidated, not changed.** Legacy independently re-implements the same VLE/Samiti/DU-IC/Circle scoping logic (including the DU-5/32 merge rule) in `Dashboard.php`, `Application_model.php`, and `Dashboard::export()` — three separate copies that could drift. Laravel centralizes this once in `DataScopeService`, used by the repository, the dashboard, and workflow visibility checks alike. Outcomes are identical; duplication is removed per the "no duplicate queries" development rule.

10. **Menu restructuring (this session's Module 1).** Legacy has no equivalent of a "User Management" menu item at all (it's URL-only, ungated by the layout). Laravel's Module 1 work moved "User Management" to immediately after Dashboard, removed the non-functional "Other Modules"/"Payment" placeholder wrapper (zero working routes), and promoted the real "Workflow Batches" feature to a top-level item. This is new-system menu hygiene with no legacy analogue to preserve or diverge from.

11. **View-completeness gap in this specific legacy checkout.** `Society.php` (District/DU/Samiti/Phad master-data CRUD), `Member.php` (IC committee roster), `Roles.php`, `Scheme.php`, `Relation.php`, and the user-management views (`add_user.php`, `edit_user.php`, `manage_user.php`) all exist as controllers but their Blade-equivalent CI3 views are **missing from this specific checkout** of `/var/www/html/scholarship` (present only in the sibling `beema` app, confirming this checkout was cloned from beema and never got its own copies). Any attempt to actually load those legacy pages in this checkout would fatal on `$this->template->build()`. This session used `beema`'s copies as the best-available proxy for field-level behavior (Modules 2/4), since no other source of truth exists for the intended UI.

---

## 12. Recommendations (documentation only — not implemented)

1. **Reconsider Circle's role.** Since its only substantive approval action is dead code in the scholarship module, and its production purpose today is read-only district-union-scoped visibility, consider either (a) formally documenting Circle as a "read-only regional oversight" role rather than a workflow-approval role, or (b) if CCF review is intended to come back, wiring a real trigger for status 6 (there currently is none for scholarships).

2. **Close the Account role's legacy permission gap properly**, i.e. define what Account should see/do as a deliberate product decision (this session made a pragmatic, disclosed interim choice — see §11 item 2) rather than leaving it implicitly inherited from `DataScopeService`'s pre-existing scoping.

3. **Decide whether to build the AXIS bank payment-file generation and CSV-based UTR bulk-reconciliation flow in Laravel**, or keep it a legacy-only, manually-bridged process. Currently Laravel has no equivalent of `Payment::proceedpayment()/finishpayment()/uploadCsv()`; production payment finalization for Laravel-originated applications has no automated path yet. Separately, Laravel also has no equivalent of legacy's `Scholarship::remove()` (a 28→15 revert of `forward()`, letting Account undo a final-for-payment queueing) — `ScholarshipService::nextStatus()`'s transition map is one-directional and has no reverse case from `FinalApplicationForPayment`. Low-risk to add if Account ever needs to correct a mistaken `forward()` in production.

4. **Add an explicit database CHECK/enum for `scholarship_applications.status`** covering 0-28 and 99 (Laravel's enum already documents 99; consider whether the DB column should constrain it too), to prevent a future accidental write of an undefined status code, mirroring but fixing legacy's `application_status` enum gap.

5. **If Society/Member/Role/Scheme/Relation CRUD is ever needed as a literal legacy-parity feature**, note that this checkout has no working legacy view to compare against — any "replicate CI3 behaviour" work for those specific screens will need to rely on the `beema` proxy (as Modules 2/4 did) or fresh product requirements, not a working legacy reference in this codebase.

6. **Reconcile the "HQ" terminology.** It is used throughout dashboards, status labels, and this document as shorthand for "Super Admin acting on DU-recommended applications," but is not a distinct role anywhere in code. Confirm this is acceptable as permanent terminology, or rename status labels to say "Super Admin" for clarity to new engineers who might otherwise search for a non-existent "HQ" `user_type`.

7. **Formalize the finance-stage document/amount-matrix duplication** (`Scholarship::exportpaymentfile()`, `Payment::proceedpayment()`, `scheme_helper.php::getAmount()`) into a single source of truth if/when the payment-file pipeline is ported to Laravel, per Recommendation 3.
