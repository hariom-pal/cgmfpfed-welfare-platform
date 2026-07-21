<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\DocumentTypeRepositoryInterface;
use App\Models\DocumentType;

final class DocumentTypeRepository extends MasterRepository implements DocumentTypeRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new DocumentType);
    }
}
