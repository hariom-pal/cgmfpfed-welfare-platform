<?php

use App\Models\AcademicSession;
use App\Models\Block;
use App\Models\Caste;
use App\Models\Category;
use App\Models\Circle;
use App\Models\City;
use App\Models\Course;
use App\Models\District;
use App\Models\DistrictUnion;
use App\Models\DocumentType;
use App\Models\GramPanchayat;
use App\Models\NotificationTemplate;
use App\Models\Phad;
use App\Models\RejectionReason;
use App\Models\Religion;
use App\Models\Samiti;
use App\Models\Scheme;
use App\Models\Village;
use App\Models\Ward;
use App\Models\WorkflowStatus;

return [
    'academic-sessions' => [
        'label' => 'Academic Session',
        'model' => AcademicSession::class,
        'table' => 'academic_sessions',
        'route' => 'academic-sessions',
        'fields' => [
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 20],
            ['name' => 'start_date', 'label' => 'Start Date', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'End Date', 'type' => 'date', 'required' => true],
        ],
        'display_columns' => ['name', 'start_date', 'end_date'],
        'search_columns' => ['name'],
        'sort_columns' => ['name', 'start_date', 'end_date', 'is_active', 'created_at'],
    ],
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
    'circles' => [
        'label' => 'Circle',
        'model' => Circle::class,
        'table' => 'circles',
        'route' => 'circles',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
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
    'blocks' => [
        'label' => 'Block',
        'model' => Block::class,
        'table' => 'blocks',
        'route' => 'blocks',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
    ],
    'gram-panchayats' => [
        'label' => 'Gram Panchayat',
        'model' => GramPanchayat::class,
        'table' => 'gram_panchayats',
        'route' => 'gram-panchayats',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
    ],
    'villages' => [
        'label' => 'Village',
        'model' => Village::class,
        'table' => 'villages',
        'route' => 'villages',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
    ],
    'cities' => [
        'label' => 'City',
        'model' => City::class,
        'table' => 'cities',
        'route' => 'cities',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
    ],
    'wards' => [
        'label' => 'Ward',
        'model' => Ward::class,
        'table' => 'wards',
        'route' => 'wards',
        'fields' => [
            ['name' => 'legacy_code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
        ],
        'display_columns' => ['legacy_code', 'name'],
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
