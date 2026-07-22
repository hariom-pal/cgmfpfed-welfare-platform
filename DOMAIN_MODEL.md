# Domain Model

## Business Hierarchy

The scholarship domain uses two connected geography hierarchies.

Administrative hierarchy:

```text
Circle
  -> District Union
      -> Samiti / Primary Society
          -> Phad
```

Residential geography hierarchy:

```text
District
  -> Block
      -> Gram Panchayat
          -> Village
```

Urban geography branch:

```text
District
  -> Block
      -> City
          -> Ward
```

CI3 evidence:

- `User_model::getdistrictuniondata()` loads district unions by `district_code`.
- `User_model::getdistrictuniondatabycircle()` loads district unions by `circle_id`.
- `User_model::getsamitidata()` loads samitis by `district_union_id`.
- `User_model::getphaddata()` loads phads by `samiti_id`.
- `User_model::getblock()` loads blocks by `district_code`.
- `User_model::getgrampanchayatdata()` loads gram panchayats by `block_code`.
- `User_model::getvillagedata()` loads villages by `gp_code`.
- `User_model::getcitiesdata()` loads cities by `block_code`.
- `User_model::getwardsdata()` loads wards by `city_code`.

## Core Entities

`User`:

- Staff user or migrated CSC/VLE user.
- Has role via `user_type`.
- Has scope via district/circle/district union/samiti.

`Role` and `Permission`:

- Production tables are `user_type`, `priviledge`, and `role_priviledge`.
- VLE remains a special production role represented by CSC login and Laravel `csc.vle_role_id`.

`Scheme`:

- Four active Normal scholarship schemes in production.
- Applications are scheme-specific.

`ScholarshipApplication`:

- Main transaction aggregate.
- Belongs to scheme, applicant, district, district union, samiti, phad, and residential geography.
- Owns documents, collections, workflow audits, wallet transactions, and batch mappings.

`Document`:

- Production file types include `tpcard`, `aadharcard`, `haadharcard`, `admission_copy`, `passbook`, `admission_receipt`, `head_passbook`, and `phadbookfile`.
- Laravel keeps current/versioned documents in `scholarship_application_documents`.

`Approval Workflow`:

- Production status trail is `application_status`.
- Laravel equivalent is `scholarship_application_audits` plus current fields on `scholarship_applications`.

`Payment`:

- Production uses application payment fields plus payment gateway request/response and payment batch tables.
- Laravel equivalent is `scholarship_wallet_transactions` and workflow/payment batch tables.

## Data Visibility

Data visibility is role-scoped:

- VLE: own applications.
- Samiti: own samiti and district union.
- District Union: own district union, with production special pair 5/32.
- Investigation Committee: own district union, with production special pair 5/32.
- Circle: district unions in own circle.
- Super Admin: global.

The centralized Laravel source is `DataScopeService`.
