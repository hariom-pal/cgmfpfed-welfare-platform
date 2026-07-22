# Migration Strategy

## Principles

- Preserve all production rows.
- Keep production IDs/codes where external integrations or old references need them.
- Store legacy values in explicit `legacy_*` columns.
- Use modern Laravel relationships for business logic and display.
- Keep `source_data_archives` as immutable audit evidence, not as the primary runtime model.

## Execution Steps

1. Load production SQL into legacy/source tables.
2. Archive every production row into `source_data_archives`.
3. Seed normalized core masters.
4. Run `2026_07_22_120000_normalize_production_geography_relationships`.
5. Backfill relationship IDs from legacy codes.
6. Validate counts and lookup parity.
7. Run application tests.
8. Compare selected production and Laravel application details screen values.

## Backfill Rules

District:

- Production `districts.district_code` -> Laravel `districts.legacy_code`.

District Union:

- Production `district_union.id` -> `district_unions.legacy_id`.
- Production `district_union.district_code` -> `district_unions.district_id`.
- Production `district_union.circle_id` -> `district_unions.circle_id`.

Samiti:

- Production `samiti.id` -> `samitis.legacy_id`.
- Production `samiti.district_union_id` -> `samitis.district_union_id`.

Phad:

- Production `phads.id` -> `phads.legacy_id`.
- Production `phads.phad_code` -> `phads.legacy_code`.
- Production `phads.samiti_id` -> `phads.samiti_id`.

Residential geography:

- `application.block` -> `blocks.legacy_code` -> `scholarship_applications.block_id`.
- `application.grampanchayat` -> `gram_panchayats.legacy_code` -> `scholarship_applications.gram_panchayat_id`.
- `application.village` -> `villages.legacy_code` -> `scholarship_applications.village_id`.
- `application.city` -> `cities.legacy_code` -> `scholarship_applications.city_id`.
- `application.ward` -> `wards.legacy_code` -> `scholarship_applications.ward_id`.

## Rollback Strategy

- The migration is forward-only for data semantics but includes a schema rollback.
- Existing legacy code columns on applications are retained, so rollback does not lose production references.
- `source_data_archives` remains unchanged and can rebuild normalized mappings.

## Validation Completed

Commands run:

- `php artisan migrate`
- `php artisan test`
- `./vendor/bin/pint --dirty`

Validated local counts after migration:

- `circles=6`
- `blocks=150`
- `gram_panchayats=11655`
- `villages=20613`
- `cities=166`
- `wards=3179`
- all `20023` scholarship applications have `block_id`
