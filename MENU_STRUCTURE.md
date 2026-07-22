# Menu Structure

Status: analysis only. No CI3 or Laravel implementation was changed.

## CI3 Files Reviewed

- `/var/www/html/scholarship/application/views/layouts/default.php`
- `/var/www/html/scholarship/application/views/default.php`
- `/var/www/html/scholarship/application/controllers/Scholarship.php`
- `/var/www/html/scholarship/application/controllers/Applications.php`
- `/var/www/html/scholarship/application/hooks/Visitor.php`

## Scholarship Layout Menu

Primary production scholarship layout: `views/layouts/default.php`.

Always visible:

- Scholarship Dashboard: `dashboard`
- Insurance Dashboard: external `https://beema.local.in/dashboard`
- Application menu
- Logout

Visible only for VLE:

- Add Application: `scholarship/scholarshipschemedetail`
- Incomplete Application: `failed`

Application submenu:

- All Applications: `scholarship`
- Add Application: `scholarship/scholarshipschemedetail` for VLE only
- Pending Applications: `scholarship/pending`
- Processing Applications: `scholarship/underprocess`
- Completed Applications: `scholarship/completed`
- Rejected Applications: `scholarship/rejected`
- Failed Applications: `scholarship/failed`

Visible when permission 38 exists:

- Batches: `batch`

Visible only for `USER_TYPE == 1`:

- Payment > Pending: `payment/pending`
- Payment > Completed: `payment/completed`
- Payment > Failed: `payment/failed`
- Payment > Upload UTR: `payment/upload`
- Samiti Wise Count: `report`

## Generic Layout Menu

Secondary production layout: `views/default.php`.

Always visible:

- Dashboard
- Application menu

Permission-gated:

- Manage User: visible when permission 1 or 2 exists
- Schemes: visible when permission 35 exists

Application submenu:

- Non-VLE: All Applications -> `application`
- VLE: My Applications -> `application`
- VLE: Add Application -> `application/add`

## Scheme-Based Application Navigation

The current production scholarship workflow is:

1. User clicks Application/Add Application.
2. Production opens scheme selection via `Scholarship::scholarshipschemedetail()`.
3. View rendered: `scholarship_scheme_detail`.
4. User selects a scheme.
5. Application list or add form is loaded for that selected scheme.

Production schemes are loaded from `schemes` where:

- `application_type = 'Normal'`
- `status = '1'`

Active production schemes in the archive:

- 1: Award Scheme for Meritorious Students
- 2: Education Proficiency Incentive Scheme
- 3: Scholarship Scheme for Professional Courses
- 4: Scholarship scheme for Non-Professional Courses

Production application listing controller:

- Current scholarship listing is `Scholarship::index()`.
- `Applications::__construct()` redirects to `scholarship`, so `Applications` is not the active current listing path.

Production listing view:

- `Scholarship::index()` renders `manage_application`.
- Status-specific actions render `statuswise_application`.

## Laravel Migration Target

Laravel must preserve this sequence:

`Applications menu -> Scheme selection -> Scheme-wise list`

Current Laravel observations:

- `routes/web.php` has `applications` directly mapped to `ScholarshipController::index`.
- `resources/views/scholarship/select_scheme.blade.php` exists.
- `app/Services/ScholarshipViewModelService::schemeSelection()` exists.
- `resources/views/components/sidebar.blade.php` links directly to `applications.index`.

Current gap:

- Sidebar navigation should not bypass scheme selection if the production workflow first displays available scholarship schemes.
- Blade should receive a prepared navigation view model. It should not query permissions or schemes directly.

Recommended Laravel target:

- Add/retain a controller action equivalent to `Scholarship::scholarshipschemedetail()`.
- Route Application menu to scheme selection.
- Route selected scheme to `applications.index?scheme=<id>` or a named route containing the scheme.
- Keep scheme query in service/repository: active Normal schemes only.
- Keep role-specific menu decisions in a menu view model service, not Blade.
