<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Services\CurrentUserService;
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
        ScholarshipRepositoryInterface $applications,
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
        $currentSchemeId = (int) ($request->query('scheme') ?: $request->session()->get('current_scheme_id'));
        $currentScheme = $currentSchemeId > 0 ? Scheme::query()->find($currentSchemeId) : null;
        $academicSessionId = (int) $request->query('academic_session_id');
        $currentAcademicSession = $academicSessionId > 0 ? AcademicSession::query()->find($academicSessionId) : null;

        if ($currentScheme) {
            $request->session()->put('current_scheme_id', $currentScheme->id);
        }

        $baseFilters = array_filter([
            'scheme_id' => $currentScheme?->id,
            'academic_session_id' => $currentAcademicSession?->id,
        ]);

        $applicationCount = function (?string $status) use ($user, $applications, $baseFilters): int {
            if (! $user) {
                return 0;
            }

            $filters = $status !== null ? [...$baseFilters, 'status' => $status] : $baseFilters;

            return $applications->filteredQueryFor($user, $filters)->count();
        };

        $applicationLink = fn (?string $status): string => route('applications.index', array_filter([
            'scheme' => $currentScheme?->id,
            'academic_session_id' => $currentAcademicSession?->id,
            'status' => $status,
        ]));

        /** @var Collection<int, array{label: string, value: int|string, icon: string, color: string, route?: string|null, href?: string|null}> $cards */
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
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Applications', 'value' => $applicationCount(null), 'icon' => 'fa-file-lines', 'color' => 'secondary', 'href' => $applicationLink(null)] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Pending Applications', 'value' => $applicationCount('pending'), 'icon' => 'fa-clock', 'color' => 'warning', 'href' => $applicationLink('pending')] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Pending at VLE', 'value' => $applicationCount('pending_vle'), 'icon' => 'fa-user-clock', 'color' => 'info', 'href' => $applicationLink('pending_vle')] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Recommended Applications', 'value' => $applicationCount('recommended'), 'icon' => 'fa-thumbs-up', 'color' => 'primary', 'href' => $applicationLink('recommended')] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Rejected Applications', 'value' => $applicationCount('rejected'), 'icon' => 'fa-circle-xmark', 'color' => 'danger', 'href' => $applicationLink('rejected')] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Completed Applications', 'value' => $applicationCount('completed'), 'icon' => 'fa-circle-check', 'color' => 'success', 'href' => $applicationLink('completed')] : null,
            $user && $permissions->can($user, 'applications.view') ? ['label' => 'Payment Failed Applications', 'value' => $applicationCount('payment_failed'), 'icon' => 'fa-triangle-exclamation', 'color' => 'danger', 'href' => $applicationLink('payment_failed')] : null,
        ])));

        return view('dashboard', [
            'cards' => $cards,
            'masterCards' => $user && $permissions->can($user, 'masters.manage') ? $masterCards : collect(),
            'currentScheme' => $currentScheme,
            'currentAcademicSession' => $currentAcademicSession,
            'academicSessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'activities' => [
                'Signed in as '.$currentUser->roleName().'.',
                'Menus are generated from the centralized role and permission services.',
                'Application counts respect the centralized data scope.',
            ],
        ]);
    }
}
