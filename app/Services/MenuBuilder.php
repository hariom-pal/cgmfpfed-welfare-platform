<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\MasterRegistry;

final class MenuBuilder
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly PermissionService $permissions,
        private readonly MasterRegistry $masters,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function buildFor(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        $items = [
            $this->routeItem('Dashboard', 'dashboard', 'fa-solid fa-gauge-high'),
        ];

        if ($this->permissions->can($user, 'users.view')) {
            $items[] = $this->routeItem('User Management', 'users.index', 'fa-solid fa-users-gear', ['users.*']);
        }

        if ($this->permissions->can($user, 'masters.manage')) {
            $items[] = [
                'label' => 'Masters',
                'url' => '#',
                'icon' => 'fa-solid fa-table-list',
                'active' => ['masters.*'],
                'children' => $this->masterChildren(),
            ];
        }

        if ($this->permissions->can($user, 'applications.view')) {
            $statusItems = collect([
                'Pending' => 'pending',
                'Pending at VLE' => 'pending_vle',
                'Rejected' => 'rejected',
                'Completed' => 'completed',
                'Payment Failed' => 'payment_failed',
            ])->map(fn (string $status, string $label): array => $this->routeItem(
                $label,
                'applications.index',
                'fa-regular fa-circle',
                [],
                ['status' => $status],
                ['status' => $status],
            ))->values()->all();

            $items[] = [
                'label' => 'Scholarship Applications',
                'icon' => 'fa-regular fa-file-lines',
                'active' => ['applications.*'],
                'children' => array_values(array_filter([
                    $this->routeItem('All Applications', 'applications.index', 'fa-regular fa-circle', [], [], ['status' => '']),
                    ...$statusItems,
                    $this->roles->isVle($user) ? $this->routeItem('Add Application', 'applications.create', 'fa-regular fa-circle', ['applications.create', 'applications.create.scheme']) : null,
                ])),
            ];
        }

        $items[] = [
            'label' => 'Beema',
            'icon' => 'fa-solid fa-shield-heart',
            'children' => [
                $this->externalItem('Insurance Dashboard', 'https://beema.local.in/dashboard', 'fa-regular fa-circle'),
            ],
        ];

        if ($this->permissions->can($user, 'reports.view')) {
            $items[] = $this->routeOrDisabledItem('Reports', 'reports.index', 'fa-solid fa-chart-column');
        }

        if ($this->permissions->has($user, 38)) {
            $items[] = $this->routeItem('Workflow Batches', 'workflow.index', 'fa-solid fa-layer-group');
        }

        if ($this->permissions->can($user, 'masters.manage')) {
            $items[] = [
                'label' => 'Settings',
                'icon' => 'fa-solid fa-gear',
                'active' => ['settings.csv-export-configuration.*'],
                'children' => [
                    $this->routeItem('CSV Export Configuration', 'settings.csv-export-configuration.index', 'fa-regular fa-circle', ['settings.csv-export-configuration.*']),
                ],
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $active
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $activeQuery
     * @return array<string, mixed>
     */
    private function routeItem(string $label, string $route, string $icon, array $active = [], array $parameters = [], array $activeQuery = []): array
    {
        $item = [
            'label' => $label,
            'route' => $route,
            'parameters' => $parameters,
            'url' => route($route, $parameters),
            'icon' => $icon,
            'active' => $active === [] ? [$route] : $active,
        ];

        if ($activeQuery !== []) {
            $item['active_query'] = $activeQuery;
        }

        return $item;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function routeOrDisabledItem(string $label, string $route, string $icon, array $parameters = []): array
    {
        return route($route, $parameters) ? $this->routeItem($label, $route, $icon, [], $parameters) : $this->disabledItem($label, $icon);
    }

    /**
     * @return array<string, mixed>
     */
    private function externalItem(string $label, string $url, string $icon): array
    {
        return ['label' => $label, 'url' => $url, 'icon' => $icon, 'external' => true];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function masterChildren(): array
    {
        return collect($this->masters->all())
            ->map(fn (array $master): array => $this->routeItem($master['label'], 'masters.index', 'fa-regular fa-circle', ['masters.*'], ['masterKey' => $master['route']]))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledItem(string $label, string $icon): array
    {
        return ['label' => $label, 'url' => '#', 'icon' => $icon, 'disabled' => true];
    }
}
