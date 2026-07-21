<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\NotificationTemplateRepositoryInterface;
use App\Models\NotificationTemplate;

final class NotificationTemplateRepository extends MasterRepository implements NotificationTemplateRepositoryInterface
{
    public function __construct()
    {
        parent::__construct(new NotificationTemplate);
    }
}
