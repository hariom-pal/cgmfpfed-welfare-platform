<?php

declare(strict_types=1);

return [
    'roles' => [
        1 => 'Super Admin',
        2 => 'District Union',
        3 => 'Samiti',
        4 => 'Investigation Commitee',
        5 => 'Circle',
        'VLE' => 'VLE',
    ],

    'permissions' => [
        1 => 'Add User',
        2 => 'Edit User',
        4 => 'Add Roles',
        5 => 'Verify Application',
        6 => 'Assess Verified Application',
        8 => 'View Verfied Application',
        9 => 'View All Rejected Application',
        10 => 'View All Assessed Application',
        14 => 'Create/Update/View Camp Type',
        15 => 'Add/View Price List',
        16 => 'Access Report',
        20 => 'Create Assessment Camp',
        21 => 'View Assessmemt Camp',
        22 => 'Add Camp Location',
        23 => 'Edit Camp Location',
        24 => 'Delete Camp Location',
        25 => 'Edit Assessment Camp',
        26 => 'Delete Assessment Camp',
        27 => 'Create Distribution Camp',
        28 => 'Start Distribution Access',
        29 => 'View Distributed Application',
        30 => 'View Distribution Camp',
        31 => 'Access Dashboard',
        32 => 'Export All Applications',
        33 => 'Export Verified Applications',
        34 => 'Export Statewise Data',
        35 => 'Manage Schemes',
        36 => 'Manage Members',
        37 => 'Manage Relation',
        38 => 'Manage Batch',
        39 => 'Reports',
        40 => 'Society Data',
    ],

    'vle_effective_permissions' => [5, 8, 9, 10, 32, 33],

    'abilities' => [
        'dashboard.view' => ['roles' => '*'],
        'applications.view' => ['roles' => [1, 2, 3, 4, 5, 'VLE']],
        'applications.create' => ['roles' => ['VLE']],
        'applications.update' => ['roles' => [1, 2, 3, 4, 5, 'VLE']],
        'applications.submit' => ['roles' => [1, 2, 3, 4, 5, 'VLE']],
        'applications.documents.view' => ['roles' => [1, 2, 3, 4, 5, 'VLE']],
        'workflow.view' => ['roles' => [1, 2, 3, 4, 5], 'permissions' => [6, 20, 21, 27, 28, 38]],
        'workflow.action' => ['roles' => [1, 2, 3, 4, 5], 'permissions' => [6, 20, 21, 27, 28, 38]],
        'reports.view' => ['roles' => [1, 5], 'permissions' => [16, 34, 39]],
        'masters.manage' => ['permissions' => [35]],
        'settings.manage' => ['permissions' => [1, 2, 4]],
    ],

    'vle_route_prefixes' => [
        'dashboard',
        'applications.',
    ],
];
