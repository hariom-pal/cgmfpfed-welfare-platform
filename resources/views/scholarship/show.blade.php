@extends('layouts.admin')

@section('title', 'Scholarship Application')
@section('heading', $application->application_number ?? 'Draft #'.$application->id)
@section('subtitle', $application->status_label)

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            @foreach($sections as $section)
                <x-card :title="$section['title']" :icon="$section['icon']" class="mb-3">
                    <div class="row g-3">
                        @foreach($section['fields'] as $field)
                            <x-show-field :label="$field['label']" :value="$field['value']" :class="$field['class'] ?? 'col-md-6'" />
                        @endforeach
                    </div>
                </x-card>
            @endforeach

            <x-card title="Attach Document / दस्तावेज़ संलग्न करें" icon="fa-solid fa-paperclip" class="mb-3">
                <div class="row g-3">
                    @foreach($documentRows as $row)
                        <div class="col-md-6">
                            <div class="small text-muted">{{ $row['label'] }}</div>
                            @if($row['document'])
                                <a href="{{ $row['showUrl'] }}" target="_blank" rel="noopener">{{ $row['linkLabel'] }}</a>
                                <a class="ms-2" href="{{ $row['downloadUrl'] }}">Download</a>
                            @else
                                <span class="text-muted">Not uploaded</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            @if($collectionRows !== [] || $collectionSummary !== [])
                <x-card title="Collection Details" icon="fa-solid fa-leaf" class="mb-3">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Year</th><th>Collection Quantity in Gaddi</th><th>TP Card Number</th><th>Verified</th></tr></thead>
                            <tbody>
                            @forelse($collectionRows as $row)
                                <tr>
                                    <td>{{ $row['year'] }}</td>
                                    <td>{{ $row['quantity'] }}</td>
                                    <td>{{ $row['tpCard'] }}</td>
                                    <td>{{ $row['verified'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted text-center py-3">No collection details recorded.</td></tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="row g-3 mt-1">
                        @foreach($collectionSummary as $field)
                            <x-show-field :label="$field['label']" :value="$field['value']" :class="$field['class'] ?? 'col-md-6'" />
                        @endforeach
                    </div>
                </x-card>
            @endif

            <x-card title="Document Preview" icon="fa-solid fa-magnifying-glass" class="mb-3">
                <div class="row g-3">
                    @forelse($previewDocuments as $preview)
                        @if($preview['isImage'])
                            <div class="col-md-6">
                                <div class="fw-semibold mb-2">{{ $preview['label'] }}</div>
                                <a href="{{ $preview['showUrl'] }}" target="_blank" rel="noopener">
                                    <img class="img-fluid border rounded" style="max-height: 320px; object-fit: contain; width: 100%;" src="{{ $preview['showUrl'] }}" alt="{{ $preview['document']->displayName() }}">
                                </a>
                            </div>
                        @elseif($preview['isPdf'])
                            <div class="col-12">
                                <div class="fw-semibold mb-2">{{ $preview['label'] }}</div>
                                <iframe class="border rounded w-100" style="min-height: 520px;" src="{{ $preview['showUrl'] }}" title="{{ $preview['document']->displayName() }}"></iframe>
                            </div>
                        @endif
                    @empty
                        <div class="col-12 text-muted text-center py-3">No previewable documents uploaded.</div>
                    @endforelse
                </div>
            </x-card>

            <x-card title="Document History" icon="fa-solid fa-clock-rotate-left" class="mb-3">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Document</th><th>Version</th><th>Uploaded By</th><th>Uploaded On</th><th>Replaced By</th><th>Replaced On</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                        @forelse($application->documents as $document)
                            <tr>
                                <td>{{ $documentLabels[$document->document_type] ?? str_replace('_', ' ', $document->document_type) }} @if($document->is_current)<span class="badge text-bg-success ms-1">Current</span>@endif</td>
                                <td>v{{ $document->version }}</td>
                                <td>{{ $document->uploader?->name ?? $document->uploaded_by ?? 'N/A' }}</td>
                                <td>{{ $document->uploaded_at?->format('d M Y H:i') ?? $document->created_at?->format('d M Y H:i') }}</td>
                                <td>{{ $document->replacer?->name ?? $document->replaced_by ?? 'N/A' }}</td>
                                <td>{{ $document->replaced_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.documents.show', [$application, $document]) }}" target="_blank" rel="noopener"><i class="fa-regular fa-eye"></i></a>
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.documents.download', [$application, $document]) }}"><i class="fa-solid fa-download"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted text-center py-3">No document history recorded.</td></tr>
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
            <x-card title="Status" icon="fa-solid fa-list-check">
                <dl class="row mb-0">
                    <dt class="col-sm-5">Application</dt><dd class="col-sm-7">{{ $statusSummary['applicationNumber'] }}</dd>
                    <dt class="col-sm-5">Status</dt><dd class="col-sm-7">{{ $statusSummary['status'] }}</dd>
                    <dt class="col-sm-5">Amount</dt><dd class="col-sm-7">{{ $statusSummary['amount'] }}</dd>
                    <dt class="col-sm-5">Payment</dt><dd class="col-sm-7">{{ $statusSummary['payment'] }}</dd>
                    <dt class="col-sm-5">Reference</dt><dd class="col-sm-7">{{ $statusSummary['reference'] }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
