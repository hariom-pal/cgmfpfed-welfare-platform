<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

final class MenuBuilder
{
    public function __construct(
        private readonly RoleService $roles,
        private readonly PermissionService $permissions,
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

        if ($this->permissions->can($user, 'masters.manage')) {
            $items[] = $this->routeOrDisabledItem('Masters', 'masters.index', 'fa-solid fa-table-list', ['masterKey' => 'schemes']);
        }

        if ($this->permissions->can($user, 'applications.view')) {
            $items[] = [
                'label' => 'Scholarship',
                'icon' => 'fa-regular fa-file-lines',
                'active' => ['applications.*'],
                'children' => array_values(array_filter([
                    $this->routeItem('All Applications', 'applications.index', 'fa-regular fa-circle'),
                    $this->roles->isVle($user) ? $this->routeItem('Add Application', 'applications.create', 'fa-regular fa-circle') : null,
                    $this->routeItem('Pending at VLE', 'applications.index', 'fa-regular fa-circle', [], ['category' => 'pending-at-vle']),
                    $this->routeItem('Under Process', 'applications.index', 'fa-regular fa-circle', [], ['category' => 'under-process']),
                    $this->routeItem('Completed', 'applications.index', 'fa-regular fa-circle', [], ['category' => 'completed']),
                    $this->routeItem('Failed', 'applications.index', 'fa-regular fa-circle', [], ['category' => 'failed']),
                    $this->routeItem('Rejected', 'applications.index', 'fa-regular fa-circle', [], ['category' => 'rejected']),
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

        if ($this->roles->isSuperAdmin($user)) {
            $items[] = $this->disabledItem('User Management', 'fa-solid fa-users-gear');
        }

        $otherModules = [];
        if ($this->permissions->has($user, 38)) {
            $otherModules[] = $this->routeItem('Workflow Batches', 'workflow.index', 'fa-regular fa-circle');
        }
        if ($this->roles->isSuperAdmin($user)) {
            $otherModules[] = [
                'label' => 'Payment',
                'icon' => 'fa-solid fa-building-columns',
                'active' => ['payment.*'],
                'children' => [
                    $this->disabledItem('Pending', 'fa-regular fa-circle'),
                    $this->disabledItem('Completed', 'fa-regular fa-circle'),
                    $this->disabledItem('Failed', 'fa-regular fa-circle'),
                    $this->disabledItem('Upload UTR', 'fa-regular fa-circle'),
                ],
            ];
        }
        if ($this->permissions->can($user, 'settings.manage')) {
            $otherModules[] = $this->routeItem('Settings', 'settings.index', 'fa-regular fa-circle');
        }
        if ($otherModules !== []) {
            $items[] = [
                'label' => 'Other Modules',
                'icon' => 'fa-solid fa-layer-group',
                'children' => $otherModules,
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $active
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function routeItem(string $label, string $route, string $icon, array $active = [], array $parameters = []): array
    {
        return [
            'label' => $label,
            'route' => $route,
            'parameters' => $parameters,
            'url' => route($route, $parameters),
            'icon' => $icon,
            'active' => $active === [] ? [$route] : $active,
        ];
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
     * @return array<string, mixed>
     */
    private function disabledItem(string $label, string $icon): array
    {
        return ['label' => $label, 'url' => '#', 'icon' => $icon, 'disabled' => true];
    }
}
