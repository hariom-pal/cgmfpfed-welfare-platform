<?php

declare(strict_types=1);

namespace App\Domains\Scholarship\Enums;

enum ApplicationState: string
{
    case Created = 'created';
    case Submitted = 'submitted';
    case InWorkflow = 'in_workflow';
    case ReturnedForCorrection = 'returned_for_correction';
    case Rejected = 'rejected';
    case Completed = 'completed';
}
