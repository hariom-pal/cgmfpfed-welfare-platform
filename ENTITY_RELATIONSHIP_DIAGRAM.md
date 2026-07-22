# Entity Relationship Diagram

## Scholarship Core

```mermaid
erDiagram
    USERS ||--o{ SCHOLARSHIP_APPLICATIONS : applicant
    USER_TYPE ||--o{ USERS : role
    USER_TYPE ||--o{ ROLE_PRIVILEDGE : has
    PRIVILEDGE ||--o{ ROLE_PRIVILEDGE : grants

    SCHEMES ||--o{ SCHOLARSHIP_APPLICATIONS : scheme
    ACADEMIC_SESSIONS ||--o{ SCHOLARSHIP_APPLICATIONS : session

    DISTRICTS ||--o{ DISTRICT_UNIONS : contains
    CIRCLES ||--o{ DISTRICT_UNIONS : supervises
    DISTRICT_UNIONS ||--o{ SAMITIS : contains
    SAMITIS ||--o{ PHADS : contains

    DISTRICTS ||--o{ BLOCKS : contains
    BLOCKS ||--o{ GRAM_PANCHAYATS : contains
    GRAM_PANCHAYATS ||--o{ VILLAGES : contains
    BLOCKS ||--o{ CITIES : contains
    CITIES ||--o{ WARDS : contains

    DISTRICTS ||--o{ SCHOLARSHIP_APPLICATIONS : district
    DISTRICT_UNIONS ||--o{ SCHOLARSHIP_APPLICATIONS : district_union
    SAMITIS ||--o{ SCHOLARSHIP_APPLICATIONS : samiti
    PHADS ||--o{ SCHOLARSHIP_APPLICATIONS : phad
    BLOCKS ||--o{ SCHOLARSHIP_APPLICATIONS : block
    GRAM_PANCHAYATS ||--o{ SCHOLARSHIP_APPLICATIONS : gram_panchayat
    VILLAGES ||--o{ SCHOLARSHIP_APPLICATIONS : village
    CITIES ||--o{ SCHOLARSHIP_APPLICATIONS : city
    WARDS ||--o{ SCHOLARSHIP_APPLICATIONS : ward

    SCHOLARSHIP_APPLICATIONS ||--o{ SCHOLARSHIP_APPLICATION_DOCUMENTS : documents
    SCHOLARSHIP_APPLICATIONS ||--o{ SCHOLARSHIP_APPLICATION_AUDITS : workflow_history
    SCHOLARSHIP_APPLICATIONS ||--o{ SCHOLARSHIP_TENDUPATTA_COLLECTIONS : collections
    SCHOLARSHIP_APPLICATIONS ||--o{ SCHOLARSHIP_WALLET_TRANSACTIONS : payments
    SCHOLARSHIP_WORKFLOW_BATCHES ||--o{ SCHOLARSHIP_BATCH_APPLICATIONS : contains
    SCHOLARSHIP_APPLICATIONS ||--o{ SCHOLARSHIP_BATCH_APPLICATIONS : batched
```

## Legacy Preservation

Legacy identifiers are preserved in dedicated columns:

- `districts.legacy_code`
- `district_unions.legacy_id`
- `district_unions.legacy_district_code`
- `district_unions.legacy_circle_id`
- `samitis.legacy_id`
- `samitis.legacy_district_union_id`
- `phads.legacy_id`
- `phads.legacy_code`
- `blocks.legacy_code`
- `gram_panchayats.legacy_code`
- `villages.legacy_code`
- `cities.legacy_code`
- `wards.legacy_code`
- `scholarship_applications.legacy_application_id`

`source_data_archives` remains an audit/migration trace, not the business relationship model.
