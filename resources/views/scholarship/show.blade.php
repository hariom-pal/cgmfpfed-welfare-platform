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
                    <dt class="col-sm-4">Location</dt><dd class="col-sm-8">District {{ $application->district_id }}, Union {{ $application->district_union_id }}, Samiti {{ $application->samiti_id }}, Phad {{ $application->phad_id }}</dd>
                    <dt class="col-sm-4">Area</dt><dd class="col-sm-8">{{ $application->area }} {{ $application->area === 'Rural' ? 'GP '.$application->gram_panchayat_code.', Village '.$application->village_code : 'City '.$application->city_code.', Ward '.$application->ward_code }}</dd>
                    <dt class="col-sm-4">Address</dt><dd class="col-sm-8">{{ $application->address }} {{ $application->pincode ? '- '.$application->pincode : '' }}</dd>
                    <dt class="col-sm-4">Head of Family</dt><dd class="col-sm-8">{{ $application->head_of_family_name }} / {{ $application->head_of_family_aadhaar }}</dd>
                    <dt class="col-sm-4">Education</dt><dd class="col-sm-8">{{ $application->school_college_name }} {{ $application->class ? '(Class '.$application->class.')' : '' }}</dd>
                    @if(in_array((int) $application->scheme_id, [3, 4], true))
                        <dt class="col-sm-4">Course</dt><dd class="col-sm-8">{{ $application->course_name }} / {{ $application->institution_name }} / {{ $application->board_university }}</dd>
                    @endif
                    <dt class="col-sm-4">Percentage</dt><dd class="col-sm-8">{{ $application->percentage ?? 'N/A' }}</dd>
                    <dt class="col-sm-4">Amount</dt><dd class="col-sm-8">₹{{ number_format((float) $application->amount, 2) }}</dd>
                    <dt class="col-sm-4">Student Bank</dt><dd class="col-sm-8">{{ $application->student_bank_account_number }} / {{ $application->student_bank_ifsc }}</dd>
                </dl>
            </x-card>
            <x-card title="Tendupatta Collections" icon="fa-solid fa-leaf" class="mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Year</th><th>Quantity</th><th>Source</th><th>Verified</th></tr></thead>
                        <tbody>
                        @forelse($application->tendupattaCollections as $collection)
                            <tr>
                                <td>{{ $collection->collection_year }}</td>
                                <td>{{ $collection->quantity_gaddi }}</td>
                                <td>{{ $collection->data_source }}</td>
                                <td>{{ $collection->is_verified ? 'Yes' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted text-center py-3">No collection details recorded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>
            <x-card title="Documents" icon="fa-solid fa-paperclip" class="mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Document</th><th>File</th><th>Verified</th></tr></thead>
                        <tbody>
                        @forelse($application->documents as $document)
                            <tr>
                                <td>{{ str_replace('_', ' ', $document->document_type) }}</td>
                                <td>{{ $document->file_path }}</td>
                                <td>{{ $document->is_verified ? 'Yes' : 'No' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted text-center py-3">No documents uploaded.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
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
