<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\DocumentTypeRepositoryInterface;
use App\Contracts\Services\DocumentTypeServiceInterface;

final class DocumentTypeService extends MasterService implements DocumentTypeServiceInterface
{
    public function __construct(DocumentTypeRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
