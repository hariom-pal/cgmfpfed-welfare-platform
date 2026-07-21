@extends('layouts.admin')

@section('title', 'Scholarship Application')
@section('heading', $application->application_number ?? 'Draft #'.$application->id)
@section('subtitle', $application->status_label)

@php($breadcrumbs = ['Applications' => route('applications.index'), 'Details' => null])

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="Application" icon="fa-regular fa-file-lines" class="mb-3">
                <dl class="row mb-0">
                    <dt class="col-sm-4">Student</dt><dd class="col-sm-8">{{ $application->student_name }} / {{ $application->student_aadhaar }}</dd>
                    <dt class="col-sm-4">Scheme</dt><dd class="col-sm-8">{{ $application->scheme?->name }}</dd>
                    <dt class="col-sm-4">Session</dt><dd class="col-sm-8">{{ $application->academicSession?->name }}</dd>
                    <dt class="col-sm-4">Percentage</dt><dd class="col-sm-8">{{ $application->percentage ?? 'N/A' }}</dd>
                    <dt class="col-sm-4">Amount</dt><dd class="col-sm-8">₹{{ number_format((float) $application->amount, 2) }}</dd>
                    <dt class="col-sm-4">Student Bank</dt><dd class="col-sm-8">{{ $application->student_bank_account_number }} / {{ $application->student_bank_ifsc }}</dd>
                </dl>
            </x-card>
            <x-card title="Audit Trail" icon="fa-solid fa-clock-rotate-left">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Time</th><th>Action</th><th>Status</th><th>Remarks</th></tr></thead>
                        <tbody>
                        @foreach($application->audits->sortByDesc('acted_at') as $audit)
                            <tr>
                                <td>{{ $audit->acted_at?->format('d M Y H:i') }}</td>
                                <td>{{ str_replace('_', ' ', $audit->action) }}</td>
                                <td>{{ $audit->to_status }}</td>
                                <td>{{ $audit->remarks }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-lg-4">
            <x-card title="Actions" icon="fa-solid fa-list-check">
                <div class="d-grid gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('applications.edit', $application) }}"><i class="fa-regular fa-pen-to-square me-1"></i>Edit</a>
                    @if($application->is_draft)
                        <form method="POST" action="{{ route('applications.submit', $application) }}">@csrf<button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-paper-plane me-1"></i>Submit</button></form>
                    @endif
                    <a class="btn btn-outline-primary" href="{{ route('workflow.index', ['q' => $application->application_number]) }}"><i class="fa-solid fa-route me-1"></i>Workflow</a>
                </div>
            </x-card>
        </div>
    </div>
@endsection
