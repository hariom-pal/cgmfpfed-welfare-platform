<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\ScholarshipApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ScholarshipReportController extends Controller
{
    public function __construct(private readonly ScholarshipRepositoryInterface $applications) {}

    public function index(Request $request): View
    {
        $visible = $this->applications->queryVisibleFor($request->user());

        return view('scholarship.reports.index', [
            'applications' => $this->applications->paginateFor($request->user(), $request->query(), 20),
            'totals' => [
                'applications' => (clone $visible)->count(),
                'submitted' => (clone $visible)->where('is_draft', false)->count(),
                'amount' => (clone $visible)->sum('amount'),
                'paid' => (clone $visible)->where('payment_status', 'success')->sum('amount'),
            ],
            'byStatus' => ScholarshipApplication::query()
                ->select('status_label', DB::raw('count(*) as aggregate'))
                ->groupBy('status_label')
                ->orderByDesc('aggregate')
                ->get(),
        ]);
    }
}
