# Visitor Analysis

Status: analysis only. No CI3 or Laravel implementation was changed.

## CI3 Files Reviewed

- `/var/www/html/scholarship/application/hooks/Visitor.php`
- `/var/www/html/scholarship/application/config/hooks.php`
- `/var/www/html/scholarship/application/models/User_model.php`
- `/var/www/html/scholarship/application/controllers/User.php`
- `/var/www/html/scholarship/application/controllers/Scholarship.php`
- `/var/www/html/scholarship/application/controllers/Applications.php`
- `/var/www/html/scholarship/application/views/layouts/default.php`
- `/var/www/html/scholarship/application/views/default.php`

## Hook Registration

`Visitor::check_permission()` is registered in CI3 as a `post_controller` hook:

- class: `Visitor`
- method: `check_permission`
- file: `application/hooks/Visitor.php`
- timing: `post_controller`

This means the hook is not the primary login guard. Production controllers call `User_model->isLogin()` in constructors or action methods. The Visitor hook then applies role and permission routing rules.

## Authentication Source

Production login is handled in `User::index()`.

Staff login sets these session values:

- `USER_ID`: `users.id`
- `USER_TYPE`: `users.user_type`
- `NAME`: `users.name`
- `EMAIL`: `users.email`
- `MOBILE`: `users.mobile`

CSC/VLE login in `User::connectLogin()` sets:

- `USER_ID`: CSC ID
- `NAME`: CSC full name
- `EMAIL`: CSC email
- `is_admin`: `0`
- `USER_TYPE`: literal string `VLE`

Logout unsets:

- `USER_ID`
- `EMAIL`
- `NAME`
- `IS_ADMIN`
- `USER_TYPE`

`User_model::isLogin()` only checks whether `USER_ID` exists.

## Visitor Responsibilities

`Visitor::check_permission()` performs route-level authorization using:

- current controller from `$this->ci->router->fetch_class()`
- current action from `$this->ci->router->fetch_method()`
- role from `$this->ci->session->userdata('USER_TYPE')`
- permissions from `role_priviledge` where `role_id = USER_TYPE`

It redirects unauthorized users to `base_url()` rather than returning a 403.

## Non-VLE Rules

Non-VLE users are staff roles with numeric `USER_TYPE`.

Always allowed without checking `role_priviledge`:

- `user/index`
- `user/connectLogin`
- `user/vle_profile`
- `user/profile`
- `user/resetpassword`
- `dashboard/index`
- `dashboard/connectLogin`
- `dashboard/vle_profile`
- `dashboard/profile`
- `dashboard/resetpassword`

Permission-gated rules:

| Controller/action | Required permission |
| --- | --- |
| `roles/*` | 4 |
| `user/add_user` | 1 |
| `user/edit_user` | 2 |
| `beneficiary/verify` | 5 |
| `beneficiary/verfied` | 8 or 11 |
| `beneficiary/rejected` | 9 or 12 |
| `beneficiary/completed` | 10 or 13 |
| `beneficiary/viewcomplete` | 10 or 13 |
| `beneficiary/export_all_application` | 32 |
| `beneficiary/export_all_verified` | 33 |
| `statewisedata/index` | 34 |
| `member/*` | 36 |

Always blocked for non-VLE:

- `beneficiary/add`
- `beneficiary/edit`
- `beneficiary/pending_payment`

Observed production issue:

- `$priv` is built only inside a loop. If a numeric role has no `role_priviledge` rows, `$priv` can be undefined.
- The hook references old `beneficiary`, `roles`, and `statewisedata` routes that are not the current scholarship listing routes.

## VLE Rules

For `USER_TYPE == 'VLE'`, only these controller names are allowed:

- `user`
- `dashboard`
- `application`
- `scholarship`
- `scheme`
- `failed`

Additional VLE restrictions:

- On `user`, only `vle_profile` is allowed.
- Any controller outside the allowlist redirects to `base_url()`.
- The method restriction block checks `applications`, `scholarship`, and `failed`, but the redirect inside the method mismatch block is commented out. In practice, VLE method-level restriction is weaker than the code suggests.

Allowed VLE scholarship/application method list in Visitor:

- `add_application`
- `add`
- `addrvy`
- `verified`
- `edit`
- `index`
- `pending`
- `underprocess`
- `completed`
- `rejected`
- `failed`
- `pending_otp`
- `complete`
- `delivered`
- `detail`
- `verifymobile`
- `generateotp`
- `verifyotp`
- `payment`
- `preview`

## Laravel Migration Target

Laravel should not create Blade-only variables to suppress missing data. Visitor behavior should be migrated into middleware/policies/services as:

- Login guard equivalent to `User_model::isLogin()`.
- Role session equivalent using authenticated `User.user_type`, plus VLE support.
- Permission lookup equivalent to `role_priviledge.role_id = user_type`.
- Route allowlist equivalent for VLE.
- Data access scope equivalent to `Application_model::checkaccess()`.

Current Laravel files observed:

- `app/Http/Middleware/EnsurePermission.php`
- `app/Models/User.php`
- `routes/web.php`
- `config/legacy_permissions.php`

Current Laravel gap:

- Routes are grouped by broader permission sets, but this does not exactly reproduce Visitor's controller/action matrix.
- VLE is mapped through permissions in seeding/config instead of exactly reproducing the literal production `USER_TYPE == 'VLE'` branch.
- Authorization must be paired with application visibility filtering; permission alone is not enough.
