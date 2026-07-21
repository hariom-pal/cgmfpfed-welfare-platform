<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\CasteRepositoryInterface;
use App\Contracts\Services\CasteServiceInterface;

final class CasteService extends MasterService implements CasteServiceInterface
{
    public function __construct(CasteRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
