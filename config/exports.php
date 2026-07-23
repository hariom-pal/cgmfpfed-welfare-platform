<?php

declare(strict_types=1);
use App\Domains\Scholarship\Export\ScholarshipApplicationExportDefinition;

return [
    /*
     * Module key => ExportDefinitionInterface implementation.
     *
     * Add one line per future module (Beema, Users, Reports, Payments) — the CSV
     * export framework (CsvExportService/ExportTemplateService) and the
     * Administration > CSV Export Configuration screen work off this list
     * automatically, no other code changes required.
     */
    'modules' => [
        'scholarship_applications' => ScholarshipApplicationExportDefinition::class,
    ],
];
