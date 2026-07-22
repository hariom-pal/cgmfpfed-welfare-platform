<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
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

        if ($currentScheme) {
            $request->session()->put('current_scheme_id', $currentScheme->id);
            $visible->where('scheme_id', $currentScheme->id);
            $filters['scheme_id'] = $currentScheme->id;
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
            'totals' => [
                'applications' => (clone $visible)->count(),
                'submitted' => (clone $visible)->whereIn('application_state', ['in_workflow', 'returned_for_correction', 'rejected', 'completed'])->count(),
                'amount' => (clone $visible)->sum('amount'),
                'paid' => (clone $visible)->where('payment_state', 'beneficiary_payment_success')->sum('amount'),
            ],
            'byStatus' => $statusRows,
        ]);
    }
}
