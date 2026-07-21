<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Contracts\ScholarshipServiceInterface;
use App\Models\AcademicSession;
use App\Models\Scheme;
use App\Models\ScholarshipApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScholarshipController extends Controller
{
    public function __construct(
        private readonly ScholarshipRepositoryInterface $applications,
        private readonly ScholarshipServiceInterface $service,
    ) {}

    public function index(Request $request): View
    {
        return view('scholarship.index', [
            'applications' => $this->applications->paginateFor($request->user(), $request->query(), 20),
            'schemes' => Scheme::query()->where('is_active', true)->orderBy('name')->get(),
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'filters' => $request->query(),
        ]);
    }

    public function create(): View
    {
        return view('scholarship.form', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        $application = $this->service->createDraft($this->payload($request), $request->user());

        if ($request->input('intent') === 'submit') {
            $application = $this->service->submit($application, $request->user());
        }

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application saved.');
    }

    public function show(Request $request, ScholarshipApplication $application): View
    {
        $application = $this->applications->findVisible($application->id, $request->user());

        return view('scholarship.show', [
            'application' => $application->load(['academicSession', 'scheme', 'audits', 'documents', 'tendupattaCollections']),
        ]);
    }

    public function edit(Request $request, ScholarshipApplication $application): View
    {
        $application = $this->applications->findVisible($application->id, $request->user());

        return view('scholarship.form', $this->formData($application));
    }

    public function update(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        $application = $application->is_draft
            ? $this->service->updateDraft($application, $this->payload($request), $request->user())
            : $this->service->resubmit($application, $this->payload($request), $request->user());

        if ($request->input('intent') === 'submit' && $application->is_draft) {
            $application = $this->service->submit($application, $request->user());
        }

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application updated.');
    }

    public function submit(Request $request, ScholarshipApplication $application): RedirectResponse
    {
        $application = $this->applications->findVisible($application->id, $request->user());
        $application = $this->service->submit($application, $request->user());

        return redirect()->route('applications.show', $application)->with('status', 'Scholarship application submitted.');
    }

    private function formData(?ScholarshipApplication $application = null): array
    {
        return [
            'application' => $application,
            'schemes' => Scheme::query()->where('is_active', true)->orderBy('name')->get(),
            'sessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        return $request->validate([
            'academic_session_id' => ['required', 'integer', 'exists:academic_sessions,id'],
            'scheme_id' => ['required', 'integer', 'exists:schemes,id'],
            'student_aadhaar' => ['required', 'digits:12'],
            'student_name' => ['required', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:20'],
            'date_of_birth' => ['nullable', 'date'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'class' => ['nullable', 'string', 'max:20'],
            'school_college_name' => ['nullable', 'string', 'max:255'],
            'board_university' => ['nullable', 'string', 'max:255'],
            'roll_number' => ['nullable', 'string', 'max:255'],
            'marks_obtained' => ['nullable', 'numeric', 'min:0'],
            'maximum_marks' => ['nullable', 'numeric', 'min:1'],
            'course_name' => ['nullable', 'string', 'max:255'],
            'institution_name' => ['nullable', 'string', 'max:255'],
            'admission_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
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
        ]);
    }
}
