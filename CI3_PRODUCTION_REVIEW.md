# CI3 Production Review - Phase 1

## Add Scholarship Workflow

Production entry point is `/scholarship/add_application`.

Files involved:
- `application/controllers/Scholarship.php`
- `application/views/select_scheme_for_add.php`
- `application/views/add_scholarship.php`
- `application/models/AwsS3upload_model.php`
- `application/models/Application_model.php`
- `application/models/User_model.php`
- `application/hooks/Visitor.php`
- `application/views/layouts/default.php`

Observed flow:
- VLE clicks Add Scholarship.
- `Scholarship::add_application()` is executed.
- It queries `schemes` where `status = 1`.
- It renders `select_scheme_for_add`.
- No application record is created at this step.
- The VLE clicks a scheme card linking to `/scholarship/add/{scheme_id}`.
- `Scholarship::add($id)` renders `add_scholarship` for GET requests.
- On POST with `submit = 1`, validation and file upload run first.
- Uploaded files are sent through `AwsS3upload_model::amazons3Upload()`.
- Only after validation/upload succeeds, CI3 inserts `application`.
- It then generates `application_id` as `S{districtunion:2}{samiti:3}{id:5}`.
- It inserts one `application_files` row per document.
- It redirects to `/scholarship/preview/{id}`.
- The application becomes editable/reviewable through preview/edit paths after the DB row exists.

Laravel implementation note:
- `applications.create` now renders a scheme-selection page.
- `applications.create.scheme` renders the actual application form.
- Laravel no longer opens an editable application form directly from the create entry point.

## Application Listing

Production `Scholarship::index()`:
- VLE: `application.added_by = USER_ID`.
- User type 2: `districtunion = current user districtunion`.
- User type 3: `samitiname = current user samiti`.
- User type 4: own district union, except district unions `5` and `32` are grouped.
- User type 5: all district unions in current user circle, with `5` and `32` grouped.
- Always filters `payment_txn_status = 1`.
- Optional filters include scheme, application number, district, district union, samiti, phad, date range, and age in days.
- Active scheme dropdown is loaded from `schemes` where `application_type = Normal` and `status = 1`.

Production `Scholarship::pending()`:
- User type 1 sees statuses `11,12`.
- VLE and Samiti see statuses `0,1`.
- User type 2 sees statuses `5,8`.
- User type 4 sees status `4`.
- User type 5 sees status `6`.
- Also filters `payment_txn_status = 1`.

Production `Scholarship::underprocess()`:
- VLE/Samiti see statuses `2,3,4,5,6,8,9,10,11,12,15,16`.
- District Union sees `0,1,2,3,4,6,9,10,11,12,15,16`.
- Higher roles widen by stage and district/circle scoping.

## Visitor Hook Dependency Map

Primary source: `application/hooks/Visitor.php`, registered as `post_controller` in `application/config/hooks.php`.

Session and login dependencies:
- `User_model::isLogin()` checks `USER_ID`.
- Controllers call `isLogin()` in constructors and redirect to `base_url()` when missing.
- Main session variables used across controllers/views: `USER_ID`, `USER_TYPE`, `NAME`, `CSC_ID`, `PAYMENT_BATCH_ID`, `application_id`, `language`.

Authorization dependencies:
- Non-VLE users load `role_priviledge` by `role_id = USER_TYPE`.
- `roles` requires permission `4`.
- `user/add_user` requires permission `1`.
- `user/edit_user` requires permission `2`.
- `beneficiary/verify` requires permission `5`.
- `beneficiary/verfied` requires permission `8` or `11`.
- `beneficiary/rejected` requires permission `9` or `12`.
- `beneficiary/completed` and `viewcomplete` require permission `10` or `13`.
- `beneficiary/export_all_application` requires permission `32`.
- `beneficiary/export_all_verified` requires permission `33`.
- `statewisedata/index` requires permission `34`.
- `member` requires permission `36`.
- VLE users are limited to user/dashboard/application/scholarship/scheme/failed controller families.
- VLE `user` routes allow only `vle_profile`.

View-level checks:
- `application/views/layouts/default.php` builds menus from `role_priviledge`.
- `manage_application.php`, `statuswise_application.php`, `manage_batch.php`, `view_batch.php`, and `payment.php` contain direct `USER_TYPE` checks for buttons, filters, batch actions, and exports.

Laravel migration approach:
- Permission checks are represented by middleware and `User::hasAnyPermission()`.
- Data visibility is represented by repository queries.
- VLE application visibility is scoped to the authenticated applicant user.
- Document access inherits application visibility instead of checking raw role IDs in views.

## File And Image Viewing

Production storage is AWS S3 first, with local `/uploads` fallback.

Files involved:
- `application/controllers/Scholarship.php`
- `application/models/AwsS3upload_model.php`
- `application/views/detail_scholarship.php`
- `application/views/edit_scholarship.php`
- `application/config/config.php`

Observed behavior:
- Uploads use `AwsS3upload_model::amazons3Upload()`.
- Region is `ap-south-1`.
- Bucket and upload folder come from CI3 config.
- Upload key format is `{awsUploadfolder}/{filename}`.
- Allowed extensions are `pdf`, `jpeg`, `jpg`, `png`.
- Maximum size for scholarship documents is `2000000` bytes.
- View links call `getmyfile(application_id, filetype)` or `gets3file(filename)`.
- These methods issue a five-minute S3 presigned URL.
- If S3 lookup fails, CI3 falls back to `/uploads/{normalized_filename}`.

Laravel migration approach:
- `DocumentService` is responsible for locating documents and serving/redirecting to secure access.
- S3 documents use temporary signed URLs.
- Local documents are streamed through authenticated controllers.
- Raw local paths and bucket URLs are not rendered in Blade views.
