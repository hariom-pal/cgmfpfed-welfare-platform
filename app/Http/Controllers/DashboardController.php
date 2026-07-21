<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\MasterRegistry;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(MasterRegistry $registry): View
    {
        $cards = collect($registry->all())->map(function (array $master): array {
            $model = app($master['model']);

            return [
                'label' => $master['label'],
                'route' => $master['route'],
                'total' => $model->newQuery()->count(),
                'active' => $model->newQuery()->where('is_active', true)->count(),
            ];
        });

        return view('dashboard', ['cards' => $cards]);
    }
}
