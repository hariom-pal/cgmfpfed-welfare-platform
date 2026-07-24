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

        <form id="ic-batch-form" method="POST" action="{{ route('workflow.ic-batches.store') }}"></form>
        <form id="payment-batch-form" method="POST" action="{{ route('workflow.payment-batches.store') }}"></form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th></th><th>Application</th><th>Student</th><th>Stage</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>
                            @can('act', [$application, 'recommend'])
                                @if($application->status_enum === \App\Domains\Scholarship\Enums\ScholarshipApplicationStatus::RecommendedBySamiti)
                                    <input type="checkbox" class="form-check-input" name="application_ids[]" value="{{ $application->id }}" form="ic-batch-form">
                                @endif
                            @endcan
                            @can('act', [$application, 'forward'])
                                @if($application->status_enum === \App\Domains\Scholarship\Enums\ScholarshipApplicationStatus::FinalApplicationForPayment)
                                    <input type="checkbox" class="form-check-input" name="application_ids[]" value="{{ $application->id }}" form="payment-batch-form">
                                @endif
                            @endcan
                        </td>
                        <td>{{ $application->application_number ?? 'Draft #'.$application->id }}</td>
                        <td>{{ $application->student_name }}</td>
                        <td>{{ str_replace('_', ' ', $application->current_stage) }}</td>
                        <td><span class="badge text-bg-primary">{{ $application->status_label }}</span></td>
                        <td class="text-end">
                            @can('act', [$application, 'recommend'])
                                <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="recommend"><button class="btn btn-sm btn-outline-success" title="Recommend"><i class="fa-solid fa-check"></i></button></form>
                            @endcan
                            @can('act', [$application, 'return'])
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
                            @endcan
                            @can('act', [$application, 'reject'])
                                <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="reject"><button class="btn btn-sm btn-outline-danger" title="Permanently Reject"><i class="fa-solid fa-xmark"></i></button></form>
                            @endcan
                            @can('act', [$application, 'forward'])
                                <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="forward"><button class="btn btn-sm btn-outline-primary" title="Forward for Payment (Payment Ready)"><i class="fa-solid fa-paper-plane"></i></button></form>
                            @endcan
                            @can('act', [$application, 'remove'])
                                <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="remove"><button class="btn btn-sm btn-outline-secondary" title="Remove from Available for Payment"><i class="fa-solid fa-rotate-left"></i></button></form>
                            @endcan
                            @can('act', [$application, 'retry'])
                                <form class="d-inline" method="POST" action="{{ route('workflow.action', $application) }}">@csrf<input type="hidden" name="action" value="retry"><button class="btn btn-sm btn-outline-primary" title="Retry Payment"><i class="fa-solid fa-rotate-right"></i></button></form>
                            @endcan
                            @can('recordPaymentResult', $application)
                                <details class="d-inline-block text-start align-middle">
                                    <summary class="btn btn-sm btn-outline-primary" title="Record Payment Result"><i class="fa-solid fa-money-check-dollar"></i></summary>
                                    <form class="border rounded bg-white p-2 mt-2 shadow-sm" style="min-width: 260px;" method="POST" action="{{ route('workflow.payment-result', $application) }}">
                                        @csrf
                                        <div class="small fw-semibold mb-1">Payment reference / UTR</div>
                                        <input class="form-control form-control-sm mb-2" type="text" name="payment_reference_id" placeholder="Reference / UTR">
                                        <div class="small fw-semibold mb-1">Failure reason (if failed)</div>
                                        <input class="form-control form-control-sm mb-2" type="text" name="payment_failure_reason" placeholder="Failure reason">
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-success flex-fill" type="submit" name="success" value="1">Completed</button>
                                            <button class="btn btn-sm btn-danger flex-fill" type="submit" name="success" value="0">Failed</button>
                                        </div>
                                    </form>
                                </details>
                            @endcan
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.show', $application) }}"><i class="fa-regular fa-eye"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No workflow records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$applications" /></div>
    </x-card>

    @can('workflow.ic-batch')
        <x-card title="IC Batch Verification (MoM)" icon="fa-solid fa-layer-group" class="mb-3">
            <p class="text-muted small">Select checked applications above (Recommended by Samiti) and set an award amount per application if it needs to differ from the standard, automatically calculated amount. Only the scheme's fixed amounts are selectable.</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">MoM Document Path</label>
                    <input class="form-control" type="text" name="mom_file_path" form="ic-batch-form" placeholder="uploads/mom/....pdf" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Remarks</label>
                    <input class="form-control" type="text" name="remarks" form="ic-batch-form">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit" form="ic-batch-form"><i class="fa-solid fa-layer-group me-1"></i>Submit IC Batch</button>
                </div>
            </div>
            <div class="mt-3">
                <div class="small fw-semibold mb-1">Amount overrides (optional, scheme-fixed values only)</div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th></th><th>Application</th><th>Standard Amount</th><th>Modified Amount</th></tr></thead>
                        <tbody>
                        @foreach($applications->where('status_enum', \App\Domains\Scholarship\Enums\ScholarshipApplicationStatus::RecommendedBySamiti) as $application)
                            <tr>
                                <td></td>
                                <td>{{ $application->application_number }}</td>
                                <td>₹{{ number_format((float) $application->amount, 2) }}</td>
                                <td>
                                    <select class="form-select form-select-sm" name="amounts[{{ $application->id }}]" form="ic-batch-form">
                                        <option value="">Use standard amount</option>
                                        @foreach($amountOptionsByScheme[$application->scheme_id] ?? [] as $option)
                                            <option value="{{ $option }}" @selected((int) $application->amount === $option)>₹{{ number_format($option, 2) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </x-card>
    @endcan

    @can('workflow.payment-batch')
        <x-card title="Payment Batch (Account)" icon="fa-solid fa-file-invoice-dollar" class="mb-3">
            <p class="text-muted small">Select checked applications above (Final Application for Payment) and submit to generate the AXIS bank payment file.</p>
            <div class="row g-2 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Remarks</label>
                    <input class="form-control" type="text" name="remarks" form="payment-batch-form">
                </div>
                <div class="col-md-4">
                    <button class="btn btn-primary" type="submit" form="payment-batch-form"><i class="fa-solid fa-file-invoice-dollar me-1"></i>Submit Payment Batch</button>
                </div>
            </div>
        </x-card>
    @endcan

    <x-card title="Recent Batches" icon="fa-solid fa-layer-group">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Batch</th><th>Type</th><th>Status</th><th>Total</th><th class="text-end">Amount</th><th class="text-end">AXIS File</th></tr></thead>
                <tbody>
                @forelse($batches as $batch)
                    <tr>
                        <td>{{ $batch->batch_number }}</td>
                        <td>{{ $batch->type }}</td>
                        <td>{{ $batch->status }}</td>
                        <td>{{ $batch->total_applications }}</td>
                        <td class="text-end">₹{{ number_format((float) $batch->total_amount, 2) }}</td>
                        <td class="text-end">{{ $batch->axis_file_path ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No batches created.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
