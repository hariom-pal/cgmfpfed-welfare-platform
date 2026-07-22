@extends('layouts.admin')

@section('title', 'Scholarship Reports')
@section('heading', 'Scholarship Reports')
@section('subtitle', 'Application, approval, and payment summaries')

@php($breadcrumbs = ['Reports' => null])

@section('content')
    @if($currentScheme ?? null)
        <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div><span class="text-muted">Current Scheme:</span> <strong>{{ $currentScheme->name }}</strong></div>
            <a href="{{ route('applications.index') }}" class="btn btn-sm btn-outline-primary">Change Scheme</a>
        </div>
    @endif

    <form method="GET" class="row g-2 align-items-end mb-3">
        @if($currentScheme ?? null)
            <input type="hidden" name="scheme" value="{{ $currentScheme->id }}">
        @endif
        <div class="col-md-4 col-lg-3">
            <label class="form-label" for="academic_session_id">Academic Session</label>
            <select class="form-select" id="academic_session_id" name="academic_session_id">
                <option value="">All</option>
                @foreach($academicSessions as $session)
                    <option value="{{ $session->id }}" @selected(($currentAcademicSession?->id ?? '') === $session->id)>{{ $session->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-filter me-1"></i>Apply</button>
            <a class="btn btn-outline-secondary" href="{{ route('reports.index', array_filter(['scheme' => $currentScheme?->id ?? null])) }}">Reset</a>
        </div>
    </form>

    <div class="row g-3 mb-3">
        @foreach([['Applications', $totals['applications']], ['Submitted', $totals['submitted']], ['Sanctioned', '₹'.number_format((float) $totals['amount'], 2)], ['Paid', '₹'.number_format((float) $totals['paid'], 2)]] as [$label, $value])
            <div class="col-md-3"><x-card><div class="text-muted small">{{ $label }}</div><div class="fs-4 fw-semibold">{{ $value }}</div></x-card></div>
        @endforeach
    </div>

    <x-card title="Status Summary" icon="fa-solid fa-chart-column" class="mb-3">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead><tr><th>Status</th><th class="text-end">Applications</th></tr></thead>
                <tbody>
                @foreach($byStatus as $row)
                    <tr><td>{{ $row->status_label }}</td><td class="text-end">{{ $row->aggregate }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-card title="Application Register" icon="fa-regular fa-file-lines">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Application</th><th>Student</th><th>Status</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                @foreach($applications as $application)
                    <tr><td>{{ $application->application_number ?? 'Draft #'.$application->id }}</td><td>{{ $application->student_name }}</td><td>{{ $application->status_label }}</td><td class="text-end">₹{{ number_format((float) $application->amount, 2) }}</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$applications" /></div>
    </x-card>
@endsection
