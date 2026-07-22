# Session Flow

Status: analysis only. No CI3 or Laravel implementation was changed.

## CI3 Files Reviewed

- `/var/www/html/scholarship/application/controllers/User.php`
- `/var/www/html/scholarship/application/models/User_model.php`
- `/var/www/html/scholarship/application/controllers/Payment.php`
- `/var/www/html/scholarship/application/controllers/Scholarship.php`
- `/var/www/html/scholarship/application/hooks/Visitor.php`
- `/var/www/html/scholarship/application/views/layouts/default.php`
- `/var/www/html/scholarship/application/views/default.php`

## Staff Login Session

Production staff login is handled by `User::index()`.

After successful password validation, CI3 sets:

- `USER_ID`: staff user ID from `users.id`
- `USER_TYPE`: numeric role from `users.user_type`
- `NAME`: staff name
- `EMAIL`: staff email
- `MOBILE`: staff mobile

Then it regenerates the session and redirects to:

- `user/resetpassword` when `reset_code == 1`
- otherwise `dashboard`

## VLE Login Session

Production VLE login is handled through CSC Connect in `User::connectLogin()`.

It sets:

- `USER_ID`: CSC ID
- `NAME`: CSC full name
- `EMAIL`: CSC email
- `is_admin`: `0`
- `USER_TYPE`: `VLE`

Production then checks/creates records using `User_model::checkdatainvletable($csc_id)`.

## Login Check

`User_model::isLogin()` returns true only when:

- `session USER_ID != ''`

There is no role check inside `isLogin()`.

## Logout Flow

`User::logout()` checks `$_SESSION['USER_ID']`.

If present, it clears:

- `USER_ID`
- `EMAIL`
- `NAME`
- `IS_ADMIN`
- `USER_TYPE`

Then it regenerates the session and redirects to the production scholarship logout URL.

## Other Session Values Found

`PAYMENT_BATCH_ID`:

- Set and cleared in `Payment.php`.
- Tracks the currently prepared payment batch.

`connect_state`:

- Set in CSC Connect start flow.
- Used for OAuth state handling, although one validation block is commented.

`language`:

- Read by multiple controllers through `$this->session->get_userdata('language')`.
- Controllers default to English when the expected variable is missing.

## Session Values Requested but Not Found as CI3 Scholarship Session Keys

The following names were not found as active uppercase session keys in the reviewed scholarship code:

- `DISTRICT_ID`
- `PHAD_ID`
- `SOCIETY_ID`
- `SAMITI_ID`
- `DISTRICT_UNION_ID`
- `IC_ID`
- `ROLE_ID`
- `logged_in`
- `login_user`

Production does not copy most geography scope into session. It reads the current user row from `users` using `USER_ID` and then uses these columns:

- `users.district`
- `users.circle`
- `users.districtunion`
- `users.samiti`

## Role and Scope Resolution

Production scope is resolved at request time:

- `USER_TYPE` determines role branch.
- `USER_ID` loads current user through `User_model::getuserbyid()`.
- Geographic scope comes from the `users` row.
- Permission scope comes from `role_priviledge.role_id = USER_TYPE`.

## Laravel Migration Target

Laravel should use authenticated user state rather than Blade session lookups:

- `auth()->id()` equivalent to `USER_ID`
- `auth()->user()->user_type` equivalent to `USER_TYPE`
- user columns `district`, `circle`, `districtunion`, `samiti` for data scope
- `role_priviledge` for permission checks

Migration warnings:

- Do not introduce synthetic session keys like `DISTRICT_ID` or `ROLE_ID` unless the production source actually has them.
- Do not resolve geography scope in Blade.
- Do not suppress missing variables by creating empty Blade defaults.
- Build an authenticated user context service containing identity, role, permissions, and geographic scope.
