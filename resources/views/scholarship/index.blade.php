@extends('layouts.admin')

@section('title', 'Scholarship Applications')
@section('heading', 'Scholarship Applications')
@section('subtitle', 'Drafts, submissions, returns, and payment status')

@php($breadcrumbs = ['Operations' => null, 'Applications' => null])

@section('content')
    <x-card title="Applications" icon="fa-regular fa-file-lines">
        <x-slot:tools>
            <a class="btn btn-primary" href="{{ route('applications.create') }}">
                <i class="fa-solid fa-plus me-1"></i>New Application
            </a>
        </x-slot:tools>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Application, name, or Aadhaar">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="scheme_id">Scheme</label>
                <select class="form-select" id="scheme_id" name="scheme_id">
                    <option value="">All</option>
                    @foreach($schemes as $scheme)
                        <option value="{{ $scheme->id }}" @selected(($filters['scheme_id'] ?? '') == $scheme->id)>{{ $scheme->name }}</option>
                    @endforeach
                </select>
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
                <a class="btn btn-outline-secondary" href="{{ route('applications.index') }}">Reset</a>
            </div>
        </form>

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
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.show', $application) }}"><i class="fa-regular fa-eye"></i></a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.edit', $application) }}"><i class="fa-regular fa-pen-to-square"></i></a>
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
