@extends('layouts.admin')

@section('title', 'Scholarship Applications')
@section('heading', $selectedScheme?->name ?? 'Scholarship Applications')
@section('subtitle', 'Scheme-wise application list')

@section('content')
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><span class="fw-semibold">Current Scheme:</span> {{ $selectedScheme?->name }}</div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.index') }}">Change Scheme</a>
    </div>

    <x-card title="Applications" icon="fa-regular fa-file-lines">
        <x-slot:tools>
            @can('create', \App\Models\ScholarshipApplication::class)
                <a class="btn btn-primary" href="{{ route('applications.create.scheme', $selectedScheme) }}">
                    <i class="fa-solid fa-plus me-1"></i>New Application
                </a>
            @endcan
        </x-slot:tools>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="scheme" value="{{ $filters['scheme_id'] }}">
            <input type="hidden" name="category" value="{{ $selectedCategory }}">
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application, name, or Aadhaar">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="academic_session_id">Session</label>
                <select class="form-select" id="academic_session_id" name="academic_session_id">
                    <option value="">All</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['academic_session_id'] ?? '') == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
                <a class="btn btn-outline-secondary" href="{{ route('applications.index', ['scheme' => $filters['scheme_id']]) }}">Reset</a>
            </div>
        </form>

        <ul class="nav nav-tabs mb-3">
            @foreach($categories as $key => $label)
                <li class="nav-item">
                    <a @class(['nav-link', 'active' => $selectedCategory === $key]) href="{{ route('applications.index', ['scheme' => $filters['scheme_id'], 'category' => $key]) }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Application</th>
                    <th>Student</th>
                    <th>Scheme</th>
                    <th>Status</th>
                    <th class="text-end">Amount</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($applications as $application)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $application->application_number ?? 'Draft #'.$application->id }}</div>
                            <div class="small text-muted">{{ $application->academicSession?->name }}</div>
                        </td>
                        <td>
                            <div>{{ $application->student_name }}</div>
                            <div class="small text-muted">{{ $application->student_aadhaar }}</div>
                        </td>
                        <td>{{ $application->scheme?->name }}</td>
                        <td><span class="badge text-bg-{{ $application->is_draft ? 'secondary' : 'primary' }}">{{ $application->status_label }}</span></td>
                        <td class="text-end">₹{{ number_format((float) $application->amount, 2) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.show', ['application' => $application, 'scheme' => $filters['scheme_id'], 'category' => $selectedCategory]) }}"><i class="fa-regular fa-eye"></i></a>
                            @can('update', $application)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.edit', ['application' => $application, 'scheme' => $filters['scheme_id'], 'category' => $selectedCategory]) }}"><i class="fa-regular fa-pen-to-square"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">No applications found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$applications" /></div>
    </x-card>
@endsection
