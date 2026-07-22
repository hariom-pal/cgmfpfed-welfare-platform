<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWorkflowBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ScholarshipWorkflowController extends Controller
{
    public function __construct(
        private readonly ScholarshipRepositoryInterface $applications,
        private readonly ScholarshipServiceInterface $service,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('workflow.view');

        return view('scholarship.workflow.index', [
            'applications' => $this->applications->paginateFor($request->user(), $request->query(), 20),
            'batches' => ScholarshipWorkflowBatch::query()->latest()->limit(10)->get(),
        ]);
    }

    public function action(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        Gate::authorize('workflow.action');
        Gate::authorize('view', $application);
        $application = $this->applications->findVisible($application->id, $request->user());
        $data = $request->validate([
            'action' => ['required', 'in:recommend,return,reject'],
            'remarks' => ['nullable', 'string'],
            'correction_sections' => ['nullable', 'array'],
            'correction_sections.*' => ['string', 'max:80'],
            'editable_documents' => ['nullable', 'array'],
            'editable_documents.*' => ['string', 'max:80'],
        ]);

        $this->service->transition(
            $application,
            $data['action'],
            $data['remarks'] ?? null,
            $request->user(),
            $data['correction_sections'] ?? [],
            $data['editable_documents'] ?? [],
        );

        return back()->with('status', 'Workflow action recorded.');
    }

    public function icBatch(Request $request): RedirectResponse
    {
        Gate::authorize('workflow.action');

        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', 'exists:scholarship_applications,id'],
            'mom_file_path' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $this->service->createIcBatch($data['application_ids'], $request->user(), $data['mom_file_path'], $data['remarks'] ?? null);

        return back()->with('status', 'IC batch submitted.');
    }

    public function paymentBatch(Request $request): RedirectResponse
    {
        Gate::authorize('workflow.action');

        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', 'exists:scholarship_applications,id'],
            'remarks' => ['nullable', 'string'],
        ]);

        $this->service->createPaymentBatch($data['application_ids'], $request->user(), $data['remarks'] ?? null);

        return back()->with('status', 'Payment batch submitted.');
    }

    public function paymentResult(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        Gate::authorize('workflow.action');
        Gate::authorize('view', $application);
        $application = $this->applications->findVisible($application->id, $request->user());
        $data = $request->validate([
            'success' => ['required', 'boolean'],
            'payment_reference_id' => ['nullable', 'string', 'max:255'],
            'payment_failure_reason' => ['nullable', 'string'],
        ]);

        $this->service->recordPaymentResult(
            $application,
            (bool) $data['success'],
            $data['payment_reference_id'] ?? null,
            $data['payment_failure_reason'] ?? null,
            $request->user(),
        );

        return back()->with('status', 'Payment result recorded.');
    }
}
