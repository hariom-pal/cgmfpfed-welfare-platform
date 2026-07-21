<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Contracts\Services\NotificationTemplateServiceInterface;

final class NotificationTemplateService extends MasterService implements NotificationTemplateServiceInterface
{
    public function __construct(NotificationTemplateRepositoryInterface $repository)
    {
        parent::__construct($repository);
    }
}
