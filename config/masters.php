<?php

use App\Models\Caste;
use App\Models\Category;
use App\Models\Course;
use App\Models\District;
use App\Models\DistrictUnion;
use App\Models\DocumentType;
use App\Models\NotificationTemplate;
use App\Models\Phad;
use App\Models\RejectionReason;
use App\Models\Religion;
use App\Models\Samiti;
use App\Models\Scheme;
use App\Models\WorkflowStatus;

return [
    'schemes' => [
        'label' => 'Scheme',
        'model' => Scheme::class,
        'table' => 'schemes',
        'route' => 'schemes',
    ],
    'courses' => [
        'label' => 'Course',
        'model' => Course::class,
        'table' => 'courses',
        'route' => 'courses',
    ],
    'categories' => [
        'label' => 'Category',
        'model' => Category::class,
        'table' => 'categories',
        'route' => 'categories',
    ],
    'castes' => [
        'label' => 'Caste',
        'model' => Caste::class,
        'table' => 'castes',
        'route' => 'castes',
    ],
    'religions' => [
        'label' => 'Religion',
        'model' => Religion::class,
        'table' => 'religions',
        'route' => 'religions',
    ],
    'districts' => [
        'label' => 'District',
        'model' => District::class,
        'table' => 'districts',
        'route' => 'districts',
    ],
    'district-unions' => [
        'label' => 'District Union',
        'model' => DistrictUnion::class,
        'table' => 'district_unions',
        'route' => 'district-unions',
    ],
    'samitis' => [
        'label' => 'Samiti',
        'model' => Samiti::class,
        'table' => 'samitis',
        'route' => 'samitis',
    ],
    'phads' => [
        'label' => 'Phad',
        'model' => Phad::class,
        'table' => 'phads',
        'route' => 'phads',
    ],
    'document-types' => [
        'label' => 'Document Type',
        'model' => DocumentType::class,
        'table' => 'document_types',
        'route' => 'document-types',
    ],
    'workflow-statuses' => [
        'label' => 'Workflow Status',
        'model' => WorkflowStatus::class,
        'table' => 'workflow_statuses',
        'route' => 'workflow-statuses',
    ],
    'rejection-reasons' => [
        'label' => 'Rejection Reason',
        'model' => RejectionReason::class,
        'table' => 'rejection_reasons',
        'route' => 'rejection-reasons',
    ],
    'notification-templates' => [
        'label' => 'Notification Template',
        'model' => NotificationTemplate::class,
        'table' => 'notification_templates',
        'route' => 'notification-templates',
    ],
];
