<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Scholarship\Contracts\ScholarshipRepositoryInterface;
use App\Models\ScholarshipApplication;
use App\Models\ScholarshipApplicationDocument;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ScholarshipDocumentController extends Controller
{
    public function __construct(
        private readonly ScholarshipRepositoryInterface $applications,
        private readonly DocumentService $documents,
    ) {}

    public function show(Request $request, ScholarshipApplication $application, ScholarshipApplicationDocument $document): Response
    {
        return $this->serve($request, $application, $document, false);
    }

    public function download(Request $request, ScholarshipApplication $application, ScholarshipApplicationDocument $document): Response
    {
        return $this->serve($request, $application, $document, true);
    }

    private function serve(Request $request, ScholarshipApplication $application, ScholarshipApplicationDocument $document, bool $forceDownload): Response
    {
        $visibleApplication = $this->applications->findVisible($application->id, $request->user());
        Gate::authorize('viewDocument', $visibleApplication);

        abort_unless((int) $document->scholarship_application_id === (int) $visibleApplication->id, 404);
        abort_if($document->file_path === null || $document->file_path === '', 404);

        return $this->documents->serve($document, $forceDownload);
    }
}
