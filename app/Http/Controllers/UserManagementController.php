<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Circle;
use App\Models\District;
use App\Models\DistrictUnion;
use App\Models\Samiti;
use App\Models\User;
use App\Services\Export\CsvExportService;
use App\Services\UserManagementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class UserManagementController extends Controller
{
    public function __construct(private readonly UserManagementService $users) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $filters = $request->only(['name', 'email', 'user_type', 'status']);

        return view('users.index', [
            'records' => $this->users->paginate($filters),
            'filters' => $filters,
            'roles' => $this->assignableRoles(),
            'breadcrumbs' => ['User Management' => null],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', $this->formData());
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $this->users->create($request->validated(), $request->user());

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', $this->formData($user));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->update($user, $request->validated(), $request->user());

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function toggle(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        $this->users->toggleStatus($user);

        return back()->with('success', 'User status updated.');
    }

    public function export(Request $request, CsvExportService $csvExport): StreamedResponse
    {
        Gate::authorize('viewAny', User::class);

        $query = $this->users->query($request->only(['name', 'email', 'user_type', 'status']))->orderBy('name');

        return $csvExport->stream('users', $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?User $user = null): array
    {
        return [
            'roles' => $this->assignableRoles(),
            'districts' => District::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'circles' => Circle::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'districtUnions' => DistrictUnion::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'district_id', 'circle_id']),
            'samitis' => Samiti::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'district_union_id']),
            'record' => $user,
            'breadcrumbs' => ['User Management' => route('users.index'), $user ? 'Edit' : 'Create' => null],
        ];
    }

    /**
     * The assignable roles' display labels, sourced from the same config that already drives
     * RoleService::name() and every other role-label lookup in the app — avoids depending on
     * the `user_type` lookup table being seeded (it isn't under RefreshDatabase in tests).
     *
     * @return array<int, string>
     */
    private function assignableRoles(): array
    {
        $labels = config('legacy_authorization.roles', []);

        return collect(UserManagementService::ASSIGNABLE_ROLES)
            ->mapWithKeys(fn (int $id): array => [$id => (string) ($labels[$id] ?? $id)])
            ->all();
    }
}
