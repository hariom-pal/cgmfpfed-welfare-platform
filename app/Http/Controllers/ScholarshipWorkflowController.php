<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Models\AcademicSession;
use App\Models\Scheme;
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
            'academicSessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'filters' => $request->query(),
            'amountOptionsByScheme' => Scheme::query()->pluck('id')
                ->mapWithKeys(fn (int $schemeId): array => [$schemeId => $this->service->amountOptionsForScheme($schemeId)]),
        ]);
    }

    public function action(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        Gate::authorize('workflow.action');
        Gate::authorize('view', $application);
        $application = $this->applications->findVisible($application->id, $request->user());
        $data = $request->validate([
            'action' => ['required', 'in:recommend,return,reject,forward,remove,retry'],
            'remarks' => ['nullable', 'string'],
            'correction_sections' => ['nullable', 'array'],
            'correction_sections.*' => ['string', 'max:80'],
            'editable_documents' => ['nullable', 'array'],
            'editable_documents.*' => ['string', 'max:80'],
        ]);

        Gate::authorize('act', [$application, $data['action']]);

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
        Gate::authorize('workflow.ic-batch');

        $data = $request->validate([
            'application_ids' => ['required', 'array', 'min:1'],
            'application_ids.*' => ['integer', 'exists:scholarship_applications,id'],
            'mom_file_path' => ['required', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'amounts' => ['nullable', 'array'],
            'amounts.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $amountOverrides = collect($data['amounts'] ?? [])
            ->filter(fn (mixed $amount): bool => $amount !== null && $amount !== '')
            ->mapWithKeys(fn (mixed $amount, int|string $applicationId): array => [(int) $applicationId => (int) $amount])
            ->all();

        $this->service->createIcBatch($data['application_ids'], $request->user(), $data['mom_file_path'], $data['remarks'] ?? null, $amountOverrides);

        return back()->with('status', 'IC batch submitted.');
    }

    public function paymentBatch(Request $request): RedirectResponse
    {
        Gate::authorize('workflow.action');
        Gate::authorize('workflow.payment-batch');

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
        Gate::authorize('recordPaymentResult', $application);
        $data = $request->validate([
            'success' => ['required', 'boolean'],
            'payment_reference_id' => ['nullable', 'string', 'max:255'],
            'payment_failure_reason' => ['nullable', 'string'],
            'payment_failure_code' => ['nullable', 'string', 'max:100'],
            'payment_response_message' => ['nullable', 'string'],
            'bank_response' => ['nullable'],
        ]);

        $bankResponse = array_filter([
            'failure_code' => $data['payment_failure_code'] ?? null,
            'response_message' => $data['payment_response_message'] ?? null,
            'raw_response' => $data['bank_response'] ?? null,
        ], fn (mixed $value): bool => filled($value));

        $this->service->recordPaymentResult(
            $application,
            (bool) $data['success'],
            $data['payment_reference_id'] ?? null,
            $data['payment_failure_reason'] ?? null,
            $request->user(),
            $bankResponse,
        );

        return back()->with('status', 'Payment result recorded.');
    }
}
