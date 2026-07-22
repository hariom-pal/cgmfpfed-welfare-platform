<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Services\CurrentUserService;
use App\Services\DataScopeService;
use App\Services\PermissionService;
use App\Support\MasterRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        MasterRegistry $registry,
        CurrentUserService $currentUser,
        PermissionService $permissions,
        DataScopeService $scope,
    ): View {
        $user = $currentUser->user();
        $masterCards = collect($registry->all())->map(function (array $master): array {
            $model = app($master['model']);

            return [
                'label' => $master['label'],
                'route' => $master['route'],
                'total' => $model->newQuery()->count(),
                'active' => $model->newQuery()->where('is_active', true)->count(),
            ];
        });
        $visibleApplications = $user
            ? $scope->applyScholarshipVisibility(ScholarshipApplication::query(), $user)
            : ScholarshipApplication::query()->whereKey(-1);
        $currentSchemeId = (int) ($request->query('scheme') ?: $request->session()->get('current_scheme_id'));
        $currentScheme = $currentSchemeId > 0 ? Scheme::query()->find($currentSchemeId) : null;

        if ($currentScheme) {
            $request->session()->put('current_scheme_id', $currentScheme->id);
            $visibleApplications->where('scheme_id', $currentScheme->id);
        }

        /** @var Collection<int, array{label: string, value: int|string, icon: string, color: string, route: string|null}> $cards */
        $cards = collect(array_values(array_filter([
            [
                'label' => 'Academic Sessions',
                'value' => AcademicSession::query()->count(),
                'icon' => 'fa-calendar-days',
                'color' => 'primary',
                'route' => null,
            ],
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'Schemes',
                'value' => $masterCards->firstWhere('route', 'schemes')['total'] ?? 0,
                'icon' => 'fa-landmark',
                'color' => 'success',
                'route' => 'schemes',
            ] : null,
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'Courses',
                'value' => $masterCards->firstWhere('route', 'courses')['total'] ?? 0,
                'icon' => 'fa-graduation-cap',
                'color' => 'info',
                'route' => 'courses',
            ] : null,
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'Categories',
                'value' => $masterCards->firstWhere('route', 'categories')['total'] ?? 0,
                'icon' => 'fa-layer-group',
                'color' => 'warning',
                'route' => 'categories',
            ] : null,
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'Districts',
                'value' => $masterCards->firstWhere('route', 'districts')['total'] ?? 0,
                'icon' => 'fa-map-location-dot',
                'color' => 'danger',
                'route' => 'districts',
            ] : null,
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'District Unions',
                'value' => $masterCards->firstWhere('route', 'district-unions')['total'] ?? 0,
                'icon' => 'fa-people-roof',
                'color' => 'secondary',
                'route' => 'district-unions',
            ] : null,
            $user && $permissions->can($user, 'masters.manage') ? [
                'label' => 'Samitis',
                'value' => $masterCards->firstWhere('route', 'samitis')['total'] ?? 0,
                'icon' => 'fa-sitemap',
                'color' => 'primary',
                'route' => 'samitis',
            ] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Applications', 'value' => (clone $visibleApplications)->count(), 'icon' => 'fa-file-lines', 'color' => 'secondary', 'route' => null] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Pending Applications', 'value' => (clone $visibleApplications)->where('application_state', 'in_workflow')->count(), 'icon' => 'fa-clock', 'color' => 'warning', 'route' => null] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Approved Applications', 'value' => (clone $visibleApplications)->where('application_state', 'completed')->count(), 'icon' => 'fa-circle-check', 'color' => 'success', 'route' => null] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Rejected Applications', 'value' => (clone $visibleApplications)->where('approval_state', 'rejected')->count(), 'icon' => 'fa-circle-xmark', 'color' => 'danger', 'route' => null] : null,
        ])));

        return view('dashboard', [
            'cards' => $cards,
            'masterCards' => $user && $permissions->can($user, 'masters.manage') ? $masterCards : collect(),
            'currentScheme' => $currentScheme,
            'activities' => [
                'Signed in as '.$currentUser->roleName().'.',
                'Menus are generated from the centralized role and permission services.',
                'Application counts respect the centralized data scope.',
            ],
        ]);
    }
}
