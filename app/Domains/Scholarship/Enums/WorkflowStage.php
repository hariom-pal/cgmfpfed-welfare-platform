<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum WorkflowStage: string
{
    case Vle = 'vle';
    case Samiti = 'samiti';
    case Ic = 'ic';
    case DistrictUnion = 'district_union';
    case Hq = 'hq';
    case Accounts = 'accounts';
    case Completed = 'completed';
    case Closed = 'closed';
    case SourceSystem = 'source_system';
}
