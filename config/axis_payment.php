<?php

declare(strict_types=1);

return [
    // Legacy writes one pipe-delimited .txt file per payment batch to a hardcoded server path
    // (/data/AxisSnorkel/In/) that an external scheduler process ("AxisSnorkel") picks up.
    // Both paths are configurable here so production can point at the real integration
    // directories without code changes.
    'output_path' => env('AXIS_PAYMENT_OUTPUT_PATH', storage_path('app/axis-scheduler/in')),

    // Reverse-feed reconciliation: production drops bank-response files here, and the
    // reconciliation command moves each processed file to the archive path afterward.
    'reverse_feed_source_path' => env('AXIS_REVERSE_FEED_SOURCE_PATH', storage_path('app/axis-scheduler/reversefeed')),
    'reverse_feed_archive_path' => env('AXIS_REVERSE_FEED_ARCHIVE_PATH', storage_path('app/axis-scheduler/reversefeed-processed')),

    // Automated reverse-feed reconciliation records payment results as this user (an
    // administrative account), since the reconciliation command runs unattended. Must be a
    // real user's email; the command refuses to run if this is unset or unresolvable.
    'reconciliation_actor_email' => env('AXIS_RECONCILIATION_ACTOR_EMAIL'),

    'vendor_code' => env('AXIS_PAYMENT_VENDOR_CODE', 'CGMINORCVT'),
    'debit_account_number' => env('AXIS_PAYMENT_DEBIT_ACCOUNT', '921010024383030'),
    'remitter_email' => env('AXIS_PAYMENT_REMITTER_EMAIL', 'test@gmail.com'),
    'state_name' => env('AXIS_PAYMENT_STATE_NAME', 'Chattisgarh'),
];
