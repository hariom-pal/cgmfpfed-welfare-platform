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
            $this->externalItem('Insurance Dashboard', 'https://beema.local.in/dashboard', 'fa-solid fa-shield-heart'),
        ];

        if ($this->roles->isVle($user)) {
            $items[] = $this->routeItem('Add Application', 'applications.create', 'fa-solid fa-file-circle-plus', ['applications.create*']);
            $items[] = $this->disabledItem('Incomplete Application', 'fa-regular fa-file-lines');
        }

        if ($this->permissions->can($user, 'applications.view')) {
            $items[] = [
                'label' => 'Application',
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

        if ($this->permissions->has($user, 38)) {
            $items[] = $this->routeOrDisabledItem('Batches', 'workflow.index', 'fa-solid fa-layer-group');
        }

        if ($this->roles->isSuperAdmin($user)) {
            $items[] = [
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
            $items[] = $this->routeOrDisabledItem('Samiti Wise Count', 'reports.index', 'fa-solid fa-chart-column');
        }

        if ($this->permissions->can($user, 'masters.manage')) {
            $items[] = $this->routeOrDisabledItem('Master Management', 'masters.index', 'fa-solid fa-table-list', ['masterKey' => 'schemes']);
        }

        if ($this->permissions->can($user, 'reports.view') && ! $this->roles->isSuperAdmin($user)) {
            $items[] = $this->routeOrDisabledItem('Reports', 'reports.index', 'fa-solid fa-chart-column');
        }

        if ($this->permissions->can($user, 'settings.manage')) {
            $items[] = $this->routeOrDisabledItem('Settings', 'settings.index', 'fa-solid fa-gear');
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
