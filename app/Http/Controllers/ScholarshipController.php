<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Models\AcademicSession;
use App\Models\DistrictUnion;
use App\Models\Phad;
use App\Models\Samiti;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipWalletTransaction;
use App\Services\RoleService;
use App\Services\ScholarshipSessionService;
use App\Services\ScholarshipViewModelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ScholarshipController extends Controller
{
    /**
     * @var list<string>
     */
    private const STATUS_MENU_FILTERS = ['pending', 'pending_vle', 'rejected', 'completed', 'last_completed'];

    public function __construct(
        private readonly ScholarshipRepositoryInterface $applications,
        private readonly ScholarshipServiceInterface $service,
        private readonly ScholarshipViewModelService $viewModels,
        private readonly RoleService $roles,
        private readonly ScholarshipSessionService $sessions,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ScholarshipApplication::class);

        $filters = $this->applicationFilters($request);

        if (! isset($filters['scheme_id'])) {
            return view('scholarship.select_scheme', $this->viewModels->schemeSelection('list', $filters));
        }

        $request->session()->put('current_scheme_id', $filters['scheme_id']);

        return view('scholarship.index', [
            'applications' => $this->applications->paginateFor($request->user(), $filters, 20),
            'schemes' => Scheme::query()->where('is_active', true)->orderBy('name')->get(),
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'districtUnions' => DistrictUnion::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'samitis' => $this->samitiOptions($filters),
            'phads' => $this->phadOptions($filters),
            'lastActionRoles' => $this->lastActionRoles(),
            'filters' => $filters,
            'selectedScheme' => Scheme::query()->find($filters['scheme_id']),
            'breadcrumbs' => ['Operations' => null, 'Applications' => null],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ScholarshipApplication::class);

        return view('scholarship.select_scheme', $this->viewModels->schemeSelection('create'));
    }

    public function createForScheme(Scheme $scheme): View
    {
        Gate::authorize('create', ScholarshipApplication::class);
        abort_unless($scheme->is_active, 404);
        request()->session()->put('current_scheme_id', $scheme->id);

        return view('scholarship.form', $this->formData(null, $scheme));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ScholarshipApplication::class);

        $application = $this->service->createDraft($this->payload($request), $request->user());

        if ($request->input('intent') === 'submit') {
            if ($this->isVle($request)) {
                $this->service->prepareWalletSubmission($application, $request->user());

                return redirect()->route('applications.wallet.redirect', $application);
            }

            $application = $this->service->submit($application, $request->user());
        }

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application saved.');
    }

    public function show(Request $request, ScholarshipApplication $application): View
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('view', $application);
        $request->session()->put('current_scheme_id', $application->scheme_id);

        $application->load([
            'academicSession',
            'scholarshipSession',
            'scheme',
            'district',
            'districtUnion',
            'samiti',
            'phad',
            'block',
            'gramPanchayat',
            'village',
            'city',
            'ward',
            'audits',
            'documents.uploader',
            'documents.replacer',
            'currentDocuments',
            'tendupattaCollections',
            'workflowTransitions.actor',
            'latestWorkflowTransition',
            'paymentAttempts',
        ]);

        return view('scholarship.show', $this->viewModels->applicationDetails($application));
    }

    public function edit(Request $request, ScholarshipApplication $application): View
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('update', $application);
        $request->session()->put('current_scheme_id', $application->scheme_id);

        return view('scholarship.form', $this->formData($application));
    }

    public function update(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('update', $application);
        $application = $application->is_draft
            ? $this->service->updateDraft($application, $this->payload($request), $request->user())
            : $this->service->resubmit($application, $this->payload($request), $request->user());

        if ($request->input('intent') === 'submit' && $application->is_draft) {
            if ($this->isVle($request)) {
                $this->service->prepareWalletSubmission($application, $request->user());

                return redirect()->route('applications.wallet.redirect', $application);
            }

            $application = $this->service->submit($application, $request->user());
        }

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application updated.');
    }

    public function submit(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('submit', $application);
        if ($this->isVle($request)) {
            $this->service->prepareWalletSubmission($application, $request->user());

            return redirect()->route('applications.wallet.redirect', $application);
        }

        $application = $this->service->submit($application, $request->user());

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application submitted.');
    }

    public function walletRedirect(Request $request, ScholarshipApplication $application): View
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('submit', $application);
        $transaction = $application->walletTransactions()
            ->where('transaction_type', 'application_fee')
            ->where('status', 'pending')
            ->latest()
            ->firstOrFail();

        if (! $transaction instanceof ScholarshipWalletTransaction) {
            abort(404);
        }

        $rawMetadata = $transaction->getRawOriginal('metadata');
        $decodedMetadata = is_string($rawMetadata) ? json_decode($rawMetadata, true) : null;
        $metadata = is_array($decodedMetadata) ? $decodedMetadata : [];

        return view('scholarship.wallet.redirect', [
            'application' => $application,
            'transaction' => $transaction,
            'gatewayUrl' => Arr::get($metadata, 'gateway_url'),
            'message' => base64_encode(json_encode(Arr::get($metadata, 'request', []), JSON_THROW_ON_ERROR)),
        ]);
    }

    public function walletCallback(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('submit', $application);
        $response = $this->parseWalletResponse($request);

        try {
            $application = $this->service->completeWalletSubmission($application, $response, $request->user());
        } catch (ValidationException $exception) {
            return redirect()->route('applications.show', $application)->withErrors($exception->errors());
        }

        return redirect()->route('applications.show', $application)->with('status', 'Wallet payment completed and application submitted.');
    }

    private function formData(?ScholarshipApplication $application = null, ?Scheme $selectedScheme = null): array
    {
        return [
            'application' => $application,
            'selectedScheme' => $selectedScheme,
            'currentAcademicSession' => $application?->academicSession ?? $this->sessions->deriveForDate($application?->created_at ?? now()),
            'currentScholarshipSession' => $application?->scholarshipSession ?? $this->sessions->deriveForDate($application?->created_at ?? now()),
            'schemes' => Scheme::query()->where('is_active', true)->orderBy('name')->get(),
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'districts' => DB::table('source_data_archives')
                ->where('source_table', 'districts')
                ->get()
                ->map(fn (object $row): array => [
                    'id' => Arr::get((array) json_decode((string) $row->payload, true), 'district_code'),
                    'name' => Arr::get((array) json_decode((string) $row->payload, true), 'district_name'),
                ])
                ->sortBy('name')
                ->values(),
            'districtUnions' => DB::table('district_unions')->orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $payload = $request->validate([
            'scheme_id' => ['required', 'integer', 'exists:schemes,id'],
            'student_aadhaar' => ['required', 'digits:12'],
            'student_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'pincode' => ['nullable', 'digits:6'],
            'block_code' => ['nullable', 'string', 'max:30'],
            'area' => ['nullable', 'in:Rural,Urban'],
            'gram_panchayat_code' => ['nullable', 'string', 'max:30'],
            'village_code' => ['nullable', 'string', 'max:30'],
            'city_code' => ['nullable', 'string', 'max:30'],
            'ward_code' => ['nullable', 'string', 'max:30'],
            'ward_number' => ['nullable', 'string', 'max:30'],
            'class' => ['nullable', 'string', 'max:20'],
            'school_college_name' => ['nullable', 'string', 'max:255'],
            'board_university' => ['nullable', 'string', 'max:255'],
            'roll_number' => ['nullable', 'string', 'max:255'],
            'marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'maximum_marks' => ['nullable', 'numeric', 'min:1'],
            'course_name' => ['nullable', 'string', 'max:255'],
            'course_duration' => ['nullable', 'integer', 'min:1', 'max:10'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'first_year_session' => ['nullable', 'string', 'max:20'],
            'current_year_of_study' => ['nullable', 'integer', 'min:1', 'max:10'],
            'sangrahak_card_number' => ['nullable', 'string', 'max:255'],
            'head_of_family_aadhaar' => ['nullable', 'digits:12'],
            'head_of_family_name' => ['nullable', 'string', 'max:255'],
            'head_of_family_father_or_husband_name' => ['nullable', 'string', 'max:255'],
            'head_of_family_gender' => ['nullable', 'string', 'max:20'],
            'head_of_family_date_of_birth' => ['nullable', 'date'],
            'student_bank_account_number' => ['nullable', 'string', 'max:50'],
            'student_bank_ifsc' => ['nullable', 'string', 'max:20'],
            'student_bank_name' => ['nullable', 'string', 'max:255'],
            'student_bank_branch' => ['nullable', 'string', 'max:255'],
            'student_bank_account_holder_name' => ['nullable', 'string', 'max:255'],
            'district_id' => ['nullable', 'integer'],
            'district_union_id' => ['nullable', 'integer'],
            'samiti_id' => ['nullable', 'integer'],
            'phad_id' => ['nullable', 'integer'],
            'tendupatta_collections' => ['nullable', 'array'],
            'tendupatta_collections.*.collection_year' => ['nullable', 'string', 'max:9'],
            'tendupatta_collections.*.quantity_gaddi' => ['nullable', 'numeric', 'min:0'],
            'documents' => ['nullable', 'array'],
            'document_uploads' => ['nullable', 'array'],
            'document_uploads.*' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        foreach ($request->file('document_uploads', []) as $documentType => $file) {
            $disk = (string) config('scholarship_documents.disk', 'public');
            $storedPath = $file->store('scholarship-documents', $disk);

            $payload['documents'][$documentType] = [
                'file_path' => $storedPath,
                'storage_disk' => $disk,
                'original_file_name' => $file->getClientOriginalName(),
                'stored_file_name' => basename($storedPath),
                'file_extension' => $file->getClientOriginalExtension(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'source' => 'MANUAL',
            ];
        }

        if (! isset($payload['documents'])) {
            $payload['documents'] = [];
        }

        return $payload;
    }

    private function isVle(Request $request): bool
    {
        return $this->roles->isVle($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function parseWalletResponse(Request $request): array
    {
        if ($request->filled('mock_success')) {
            return [
                'txn_status' => 'Success',
                'txn_status_message' => 'Success',
                'merchant_txn' => (string) $request->input('merchant_txn'),
                'csc_txn' => 'MOCK-'.now()->format('YmdHis'),
            ];
        }

        $message = (string) $request->input('bridgeResponseMessage', $request->input('message', ''));
        $decoded = base64_decode($message, true);
        $raw = $decoded !== false ? $decoded : $message;
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }

        $response = [];
        foreach (explode('|', $raw) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key !== '') {
                $response[$key] = $value;
            }
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationFilters(Request $request): array
    {
        $filters = $request->query();
        if (! isset($filters['scheme_id']) && $request->filled('scheme')) {
            $filters['scheme_id'] = $request->query('scheme');
        }

        if (! isset($filters['application_number']) && $request->filled('application')) {
            $filters['application_number'] = $request->query('application');
        }

        if (! isset($filters['aadhaar_number']) && $request->filled('student_aadhaar')) {
            $filters['aadhaar_number'] = $request->query('student_aadhaar');
        }

        foreach (['scheme_id', 'academic_session_id', 'district_union_id', 'samiti_id', 'phad_id'] as $integerFilter) {
            if (isset($filters[$integerFilter]) && filled($filters[$integerFilter])) {
                $filters[$integerFilter] = (int) $filters[$integerFilter];
            } else {
                unset($filters[$integerFilter]);
            }
        }

        foreach (['application_number', 'aadhaar_number', 'student_name', 'last_action_from_date', 'last_action_to_date', 'last_action_role'] as $textFilter) {
            if (isset($filters[$textFilter]) && filled($filters[$textFilter])) {
                $filters[$textFilter] = trim((string) $filters[$textFilter]);
            } else {
                unset($filters[$textFilter]);
            }
        }

        if (isset($filters['status']) && in_array($filters['status'], self::STATUS_MENU_FILTERS, true)) {
            if ($filters['status'] === 'last_completed' && ! isset($filters['academic_session_id'])) {
                $currentSession = $this->sessions->deriveForDate(now());
                if ($currentSession !== null) {
                    $filters['academic_session_id'] = $currentSession->id;
                }
            }
        } else {
            unset($filters['status']);
        }

        return $filters;
    }

    /**
     * @return Collection<int, mixed>
     */
    private function samitiOptions(array $filters): mixed
    {
        if (! isset($filters['district_union_id'])) {
            return collect();
        }

        return Samiti::query()
            ->where('is_active', true)
            ->where('district_union_id', $filters['district_union_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'district_union_id']);
    }

    private function phadOptions(array $filters): mixed
    {
        if (! isset($filters['samiti_id'])) {
            return collect();
        }

        return Phad::query()
            ->where('is_active', true)
            ->where('samiti_id', $filters['samiti_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'samiti_id']);
    }

    /**
     * @return array<string, string>
     */
    private function lastActionRoles(): array
    {
        return [
            'VLE' => 'VLE',
            'Samiti' => 'Samiti',
            'Investigation Commitee' => 'IC',
            'District Union' => 'District Union',
            'Super Admin' => 'HQ',
            'Accounts' => 'Accounts',
        ];
    }
}
