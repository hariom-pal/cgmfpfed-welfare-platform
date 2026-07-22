@extends('layouts.admin')

@section('title', 'Scholarship Workflow')
@section('heading', 'Scholarship Workflow')
@section('subtitle', 'Samiti, IC, District Union, HQ, Finance, and payment actions')

@php($breadcrumbs = ['Operations' => null, 'Workflow' => null])

@section('content')
    <x-card title="Workflow Queue" icon="fa-solid fa-route" class="mb-3">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4 col-lg-3">
                <label class="form-label" for="academic_session_id">Academic Session</label>
                <select class="form-select" id="academic_session_id" name="academic_session_id">
                    <option value="">All</option>
                    @foreach($academicSessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['academic_session_id'] ?? '') == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply</button>
                <a class="btn btn-outline-secondary" href="{{ route('workflow.index') }}">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Application</th><th>Student</th><th>Stage</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>{{ $application->application_number ?? 'Draft #'.$application->id }}</td>
                        <td>{{ $application->student_name }}</td>
                        <td>{{ str_replace('_', ' ', $application->current_stage) }}</td>
                        <td><span class="badge text-bg-primary">{{ $application->status_label }}</span></td>
                        <td class="text-end">
                            <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="recommend"><button class="btn btn-sm btn-outline-success" title="Recommend"><i class="fa-solid fa-check"></i></button></form>
                            <details class="d-inline-block text-start align-middle">
                                <summary class="btn btn-sm btn-outline-warning" title="Return"><i class="fa-solid fa-arrow-rotate-left"></i></summary>
                                <form class="border rounded bg-white p-2 mt-2 shadow-sm" style="min-width: 280px;" method="POST" action="{{ route('workflow.action', $application) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="return">
                                    <div class="small fw-semibold mb-1">Correction sections</div>
                                    @foreach(['student_details' => 'Student Details', 'education_details' => 'Education Details', 'bank_details' => 'Student Bank Details', 'supporting_documents' => 'Supporting Documents'] as $section => $label)
                                        <label class="form-check small">
                                            <input class="form-check-input" type="checkbox" name="correction_sections[]" value="{{ $section }}" @checked($section === 'supporting_documents')>
                                            <span class="form-check-label">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                    <div class="small fw-semibold mt-2 mb-1">Editable documents</div>
                                    @forelse($application->currentDocuments as $document)
                                        <label class="form-check small">
                                            <input class="form-check-input" type="checkbox" name="editable_documents[]" value="{{ $document->document_type }}">
                                            <span class="form-check-label">{{ str_replace('_', ' ', $document->document_type) }}</span>
                                        </label>
                                    @empty
                                        <div class="small text-muted">No uploaded documents.</div>
                                    @endforelse
                                    <textarea class="form-control form-control-sm my-2" name="remarks" rows="2" placeholder="Remarks"></textarea>
                                    <button class="btn btn-sm btn-warning w-100" type="submit">Return</button>
                                </form>
                            </details>
                            <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-outline-danger" title="Reject"><i class="fa-solid fa-xmark"></i></button></form>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.show', $application) }}"><i class="fa-regular fa-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No workflow records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$applications" /></div>
    </x-card>

    <x-card title="Recent Batches" icon="fa-solid fa-layer-group">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Batch</th><th>Type</th><th>Status</th><th>Total</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr><td>{{ $batch->batch_number }}</td><td>{{ $batch->type }}</td><td>{{ $batch->status }}</td><td>{{ $batch->total_applications }}</td><td class="text-end">₹{{ number_format((float) $batch->total_amount, 2) }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-3">No batches created.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
