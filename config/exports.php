<?php

declare(strict_types=1);

use App\Domains\Scholarship\Export\ScholarshipApplicationExportDefinition;
use App\Services\Export\UserExportDefinition;

return [
    /*
     * Module key => ExportDefinitionInterface implementation.
     *
     * Add one line per future module (Beema, Reports, Payments) — the CSV export
     * framework (CsvExportService/ExportTemplateService) and the Settings > CSV
     * Export Configuration screen work off this list automatically, no other
     * code changes required.
     */
    'modules' => [
        'scholarship_applications' => ScholarshipApplicationExportDefinition::class,
        'users' => UserExportDefinition::class,
    ],
];
