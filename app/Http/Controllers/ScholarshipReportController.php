<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Domains\Scholarship\Enums\ApplicationState;
use App\Domains\Scholarship\Enums\PaymentState;
use App\Models\AcademicSession;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScholarshipReportController extends Controller
{
    public function __construct(private readonly ScholarshipRepositoryInterface $applications) {}

    public function index(Request $request): View
    {
        $visible = $this->applications->queryVisibleFor($request->user());
        $filters = $request->query();
        $currentSchemeId = (int) ($request->query('scheme') ?: $request->session()->get('current_scheme_id'));
        $currentScheme = $currentSchemeId > 0 ? Scheme::query()->find($currentSchemeId) : null;
        $academicSessionId = (int) ($request->query('academic_session_id') ?: 0);
        $currentAcademicSession = $academicSessionId > 0 ? AcademicSession::query()->find($academicSessionId) : null;

        if ($currentScheme) {
            $request->session()->put('current_scheme_id', $currentScheme->id);
            $visible->where('scheme_id', $currentScheme->id);
            $filters['scheme_id'] = $currentScheme->id;
        }

        if ($currentAcademicSession) {
            $visible->where('academic_session_id', $currentAcademicSession->id);
            $filters['academic_session_id'] = $currentAcademicSession->id;
        } else {
            unset($filters['academic_session_id']);
        }

        $statusRows = (clone $visible)
            ->get(['status_label'])
            ->countBy('status_label')
            ->sortDesc()
            ->map(fn (int $aggregate, string $status): object => (object) [
                'status_label' => $status,
                'aggregate' => $aggregate,
            ])
            ->values();

        return view('scholarship.reports.index', [
            'applications' => $this->applications->paginateFor($request->user(), $filters, 20),
            'currentScheme' => $currentScheme,
            'currentAcademicSession' => $currentAcademicSession,
            'academicSessions' => AcademicSession::query()->orderByDesc('start_date')->get(),
            'totals' => [
                'applications' => (clone $visible)->count(),
                'submitted' => (clone $visible)->whereIn('application_state', [
                    ApplicationState::InWorkflow->value,
                    ApplicationState::ReturnedForCorrection->value,
                    ApplicationState::Rejected->value,
                    ApplicationState::Completed->value,
                ])->count(),
                'amount' => (clone $visible)->sum('amount'),
                'paid' => (clone $visible)->where('payment_state', PaymentState::BeneficiaryPaymentSuccess->value)->sum('amount'),
            ],
            'byStatus' => $statusRows,
        ]);
    }
}
