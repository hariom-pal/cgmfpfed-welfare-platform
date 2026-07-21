<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Support\MasterRegistry;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(MasterRegistry $registry): View
    {
        $masterCards = collect($registry->all())->map(function (array $master): array {
            $model = app($master['model']);

            return [
                'label' => $master['label'],
                'route' => $master['route'],
                'total' => $model->newQuery()->count(),
                'active' => $model->newQuery()->where('is_active', true)->count(),
            ];
        });

        /** @var Collection<int, array{label: string, value: int|string, icon: string, color: string, route: string|null}> $cards */
        $cards = collect([
            [
                'label' => 'Academic Sessions',
                'value' => AcademicSession::query()->count(),
                'icon' => 'fa-calendar-days',
                'color' => 'primary',
                'route' => null,
            ],
            [
                'label' => 'Schemes',
                'value' => $masterCards->firstWhere('route', 'schemes')['total'] ?? 0,
                'icon' => 'fa-landmark',
                'color' => 'success',
                'route' => 'schemes',
            ],
            [
                'label' => 'Courses',
                'value' => $masterCards->firstWhere('route', 'courses')['total'] ?? 0,
                'icon' => 'fa-graduation-cap',
                'color' => 'info',
                'route' => 'courses',
            ],
            [
                'label' => 'Categories',
                'value' => $masterCards->firstWhere('route', 'categories')['total'] ?? 0,
                'icon' => 'fa-layer-group',
                'color' => 'warning',
                'route' => 'categories',
            ],
            [
                'label' => 'Districts',
                'value' => $masterCards->firstWhere('route', 'districts')['total'] ?? 0,
                'icon' => 'fa-map-location-dot',
                'color' => 'danger',
                'route' => 'districts',
            ],
            [
                'label' => 'District Unions',
                'value' => $masterCards->firstWhere('route', 'district-unions')['total'] ?? 0,
                'icon' => 'fa-people-roof',
                'color' => 'secondary',
                'route' => 'district-unions',
            ],
            [
                'label' => 'Samitis',
                'value' => $masterCards->firstWhere('route', 'samitis')['total'] ?? 0,
                'icon' => 'fa-sitemap',
                'color' => 'primary',
                'route' => 'samitis',
            ],
            ['label' => 'Applications', 'value' => '0', 'icon' => 'fa-file-lines', 'color' => 'secondary', 'route' => null],
            ['label' => 'Pending Applications', 'value' => '0', 'icon' => 'fa-clock', 'color' => 'warning', 'route' => null],
            ['label' => 'Approved Applications', 'value' => '0', 'icon' => 'fa-circle-check', 'color' => 'success', 'route' => null],
            ['label' => 'Rejected Applications', 'value' => '0', 'icon' => 'fa-circle-xmark', 'color' => 'danger', 'route' => null],
        ]);

        return view('dashboard', [
            'cards' => $cards,
            'masterCards' => $masterCards,
            'activities' => [
                'Master data reviewed for business demonstration.',
                'Workflow status catalogue seeded.',
                'Document checklist linked with scholarship schemes.',
            ],
        ]);
    }
}
