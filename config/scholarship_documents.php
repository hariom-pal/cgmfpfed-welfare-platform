<?php

declare(strict_types=1);

return [
    'disk' => env('SCHOLARSHIP_DOCUMENT_DISK', 'public'),
    'legacy_disk' => env('SCHOLARSHIP_LEGACY_DOCUMENT_DISK', 's3'),
    's3_prefix' => trim((string) env('SCHOLARSHIP_S3_UPLOAD_FOLDER', 'beemaclaim'), '/'),
    'temporary_url_minutes' => (int) env('SCHOLARSHIP_DOCUMENT_TEMPORARY_URL_MINUTES', 5),
    'local_legacy_upload_path' => env('SCHOLARSHIP_LEGACY_UPLOAD_PATH', public_path('uploads')),
];
