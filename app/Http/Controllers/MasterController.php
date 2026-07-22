<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreMasterRequest;
use App\Http\Requests\UpdateMasterRequest;
use App\Repositories\MasterRepository;
use App\Services\MasterService;
use App\Support\MasterRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class MasterController extends Controller
{
    public function __construct(private readonly MasterRegistry $registry) {}

    public function index(Request $request, string $masterKey): View
    {
        $context = $this->context($masterKey);
        $records = $context['service']->paginate(
            [
                'search' => $request->string('search')->toString(),
                'is_active' => $request->query('status') === null ? null : $request->boolean('status'),
            ],
            $request->string('sort', 'name')->toString(),
            $request->string('direction', 'asc')->toString(),
        );

        return view('masters.index', $context + ['records' => $records]);
    }

    public function create(string $masterKey): View
    {
        return view('masters.create', $this->context($masterKey));
    }

    public function store(StoreMasterRequest $request, string $masterKey): RedirectResponse
    {
        $context = $this->context($masterKey);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $record = $context['service']->create($data);

        if ($request->input('action') === 'continue') {
            return redirect()->route('masters.create', $masterKey)->with('success', "{$context['label']} created. You can add another record.");
        }

        return redirect()->route('masters.index', $masterKey)->with('success', "{$context['label']} created successfully.");
    }

    public function show(string $masterKey, string $uuid): View
    {
        $context = $this->context($masterKey);

        return view('masters.show', $context + ['record' => $context['service']->findByUuid($uuid)]);
    }

    public function edit(string $masterKey, string $uuid): View
    {
        $context = $this->context($masterKey);

        return view('masters.edit', $context + ['record' => $context['service']->findByUuid($uuid)]);
    }

    public function update(UpdateMasterRequest $request, string $masterKey, string $uuid): RedirectResponse
    {
        $context = $this->context($masterKey);
        $record = $context['service']->findByUuid($uuid);
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');

        $updated = $context['service']->update($record, $data);

        if ($request->input('action') === 'continue') {
            return redirect()->route('masters.edit', [$masterKey, $updated->getAttribute('uuid')])->with('success', "{$context['label']} saved. Continue editing when needed.");
        }

        return redirect()->route('masters.index', $masterKey)->with('success', "{$context['label']} updated successfully.");
    }

    public function destroy(string $masterKey, string $uuid): RedirectResponse
    {
        $context = $this->context($masterKey);
        $context['service']->delete($context['service']->findByUuid($uuid));

        return redirect()->route('masters.index', $masterKey)->with('success', "{$context['label']} deleted successfully.");
    }

    public function toggle(string $masterKey, string $uuid): RedirectResponse
    {
        $context = $this->context($masterKey);
        $context['service']->toggle($context['service']->findByUuid($uuid));

        return back()->with('success', "{$context['label']} status updated.");
    }

    /**
     * @return array{masterKey: string, master: array<string, mixed>, masters: array<string, array<string, mixed>>, label: string, service: MasterService}
     */
    private function context(string $masterKey): array
    {
        $master = $this->registry->get($masterKey);
        $repository = new MasterRepository(app($master['model']), $master);

        return [
            'masterKey' => $masterKey,
            'master' => $master,
            'masters' => $this->registry->all(),
            'label' => $master['label'],
            'service' => new MasterService($repository),
        ];
    }
}
