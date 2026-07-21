<?php

declare(strict_types=1);

return [
    'scholarship_sql_path' => env('LEGACY_SCHOLARSHIP_SQL_PATH', base_path('scholarship.sql')),
    'table_prefix' => 'legacy_',
];
