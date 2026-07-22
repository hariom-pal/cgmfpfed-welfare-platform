<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\Scheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScholarshipReportController extends Controller
{
    public function __construct(private readonly ScholarshipRepositoryInterface $applications) {}

    public function index(Request $request): View
    {
        $visible = $this->applications->queryVisibleFor($request->user());
        $statusVisible = $this->applications->queryVisibleFor($request->user());
        $filters = $request->query();
        $currentSchemeId = (int) ($request->query('scheme') ?: $request->session()->get('current_scheme_id'));
        $currentScheme = $currentSchemeId > 0 ? Scheme::query()->find($currentSchemeId) : null;

        if ($currentScheme) {
            $request->session()->put('current_scheme_id', $currentScheme->id);
            $visible->where('scheme_id', $currentScheme->id);
            $statusVisible->where('scheme_id', $currentScheme->id);
            $filters['scheme_id'] = $currentScheme->id;
        }

        return view('scholarship.reports.index', [
            'applications' => $this->applications->paginateFor($request->user(), $filters, 20),
            'currentScheme' => $currentScheme,
            'totals' => [
                'applications' => (clone $visible)->count(),
                'submitted' => (clone $visible)->where('is_draft', false)->count(),
                'amount' => (clone $visible)->sum('amount'),
                'paid' => (clone $visible)->where('payment_status', 'success')->sum('amount'),
            ],
            'byStatus' => $statusVisible
                ->select('status_label', DB::raw('count(*) as aggregate'))
                ->groupBy('status_label')
                ->orderByDesc('aggregate')
                ->get(),
        ]);
    }
}
