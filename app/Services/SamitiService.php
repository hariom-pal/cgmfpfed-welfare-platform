<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\SamitiRepositoryInterface;
use App\Contracts\Services\SamitiServiceInterface;

final class SamitiService extends MasterService implements SamitiServiceInterface
{
    public function __construct(SamitiRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
