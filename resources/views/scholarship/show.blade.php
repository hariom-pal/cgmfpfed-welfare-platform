@extends('layouts.admin')

@section('title', 'Scholarship Application')
@section('heading', $application->application_number ?? 'Draft #'.$application->id)
@section('subtitle', $application->status_label)

@php($breadcrumbs = ['Applications' => route('applications.index'), 'Details' => null])

@php
    $legacy = $legacyDetail ?? [];
    $verification = $latestVerification ?? [];
    $metadata = $application->metadata ?? [];
    $schemeId = (int) $application->scheme_id;
    $isProfessionalScheme = in_array($schemeId, [3, 4], true);
    $isAdvancedYear = $isProfessionalScheme && (int) $application->current_year_of_study > 1;
    $documentMap = $application->currentDocuments->keyBy('document_type');
    $statusLabels = [
        'Pending', 'Resubmitted by VLE', 'Not Recommended by Samiti', 'Not Recommended by IC',
        'Recommended by Samiti', 'Recommended by IC', 'Appealed by Beneficiary', 'Rejected By CCF',
        'Recommended by CCF', 'Not Recommended By DU', 'Not Recommended By DU', 'Approved By DU',
        'Approved By DU', 'Rejected By HQ', 'Rejected By HQ', 'Recommended For Payment',
        'Recommended For Payment', 'Payment Failed', 'Payment Failed', 'Payment Completed',
        'Payment Completed', 'Permanent Rejected By Samiti', 'Permanent Rejected By IC',
        'Permanent Rejected By CCF', 'Permanent Rejected By DU', 'Permanent Rejected By HQ',
        'Permanent Rejected By Accounts',
    ];
    $value = static fn (string $legacyKey, mixed $fallback = null) => filled($legacy[$legacyKey] ?? null) ? $legacy[$legacyKey] : $fallback;
@endphp

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-card title="View Scholarship Application" icon="fa-regular fa-file-lines" class="mb-3">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="fw-semibold">Information Regarding Primary Society / प्राथमिक सोसायटी के संबंध में जानकारी</div>
                    </div>
                    <x-show-field label="Scheme / योजना" :value="$value('scheme_name', $application->scheme?->name)" />
                    @if($isProfessionalScheme)
                        <x-show-field label="Select Education Year / शिक्षा वर्ष चुनें" :value="$application->current_year_of_study" />
                    @endif
                    <x-show-field label="District Union / जिला संघ" :value="$value('union_name')" />
                    <x-show-field label="Samiti Name / समिति का नाम" :value="$value('samiti_name')" />
                    <x-show-field label="PHAD Name / फड़ का नाम" :value="$value('phad_name')" />
                    <x-show-field label="District / ज़िला" :value="$value('district_name')" />
                    <x-show-field label="Block / ब्लॉक" :value="$value('block_name')" />
                    @if($application->area === 'Rural')
                        <x-show-field label="Gram Panchayat / ग्राम पंचायत" :value="$value('gp_name')" />
                        <x-show-field label="Village / गाँव" :value="$value('village_name')" />
                    @else
                        <x-show-field label="City / शहर" :value="$value('city_name')" />
                        <x-show-field label="Ward / वार्ड" :value="$value('ward_name')" />
                        <x-show-field label="Ward Number / वार्ड संख्या" :value="$value('ward_number', $application->ward_number)" />
                    @endif
                </div>
            </x-card>

            <x-card title="Head of Family Detail / परिवार मुखिया का विवरण" icon="fa-solid fa-people-roof" class="mb-3">
                <div class="row g-3">
                    <x-show-field label="Sangrahak Card Number / संग्राहक कार्ड नंबर" :value="$application->sangrahak_card_number" />
                    <x-show-field label="Head of sangrahak family name / परिवार के मुखिया का नाम" :value="$application->head_of_family_name" />
                    <x-show-field label="Aadhaar Number Of Head / मुखिया का आधार नंबर" :value="$application->head_of_family_aadhaar" />
                </div>
            </x-card>

            @if(in_array($schemeId, [1, 2], true))
                <x-card title="Head of Family Bank Detail / परिवार के प्रमुख बैंक का विवरण" icon="fa-solid fa-building-columns" class="mb-3">
                    <div class="row g-3">
                        <x-show-field label="Account Number / खाता संख्या" :value="$value('haccountnumber', data_get($metadata, 'legacy_head_of_family_bank.account_number'))" />
                        <x-show-field label="Account Holder Name / खाता धारक का नाम" :value="$value('haccountname', data_get($metadata, 'legacy_head_of_family_bank.account_holder'))" />
                        <x-show-field label="IFSC Code / आईएफएससी कोड" :value="$value('hifsc', data_get($metadata, 'legacy_head_of_family_bank.ifsc'))" />
                        <x-show-field label="Bank Name / बैंक का नाम" :value="$value('hbankname', data_get($metadata, 'legacy_head_of_family_bank.bank_name'))" />
                        <x-show-field label="Branch Name of Bank / बैंक की शाखा का नाम" :value="$value('hbranch', data_get($metadata, 'legacy_head_of_family_bank.branch'))" />
                    </div>
                </x-card>
            @endif

            <x-card title="Information of Student / छात्र की जानकारी" icon="fa-solid fa-user-graduate" class="mb-3">
                <div class="row g-3">
                    <x-show-field label="Name of Student / छात्र का नाम" :value="$application->student_name" />
                    <x-show-field label="Gender / लिंग" :value="$application->gender" />
                    <x-show-field label="Student Date of Birth / छात्र की जन्मतिथि" :value="$application->date_of_birth?->format('Y-m-d')" />
                    <x-show-field label="Aadhaar Number Of Student / छात्र का आधार नंबर" :value="$application->student_aadhaar" />
                    <x-show-field label="Address / पता" :value="$application->address" class="col-12" />
                    <x-show-field label="Pin Code / पिन कोड" :value="$application->pincode" />
                    <x-show-field label="Contact Number / संपर्क नंबर" :value="$application->mobile" />
                </div>
            </x-card>

            <x-card title="{{ $isAdvancedYear ? 'Detail of Professional Course / प्रोफेशनल कोर्स का विवरण' : 'Student Educational Detail / छात्र शैक्षिक विवरण' }}" icon="fa-solid fa-school" class="mb-3">
                <div class="row g-3">
                    @if($isAdvancedYear)
                        <x-show-field label="{{ $schemeId === 4 ? 'Non Professional Course Name' : 'Professional Course Name' }} / प्रोफेशनल कोर्स का नाम" :value="$application->course_name" />
                        <x-show-field label="Session of 1st year in Professional course(Year)" :value="$application->first_year_session" />
                        <x-show-field label="University Name / विश्वविद्यालय का नाम" :value="$application->board_university" />
                        <x-show-field label="Institute Name / संस्थान का नाम" :value="$application->institution_name" />
                        <x-show-field label="Session for which student is applying" :value="$application->scholarship_session" />
                    @else
                        <x-show-field label="School Name / स्कूल के नाम{{ $isProfessionalScheme ? ' of Class 12th' : '' }}" :value="$application->school_college_name" />
                        <x-show-field label="Passing Year / उत्तीर्ण वर्ष{{ $isProfessionalScheme ? ' of Class 12th' : '' }}" :value="$application->admission_year" />
                        <x-show-field label="Passing Class / उत्तीर्ण कक्षा{{ $isProfessionalScheme ? ' of Class 12th' : '' }}" :value="$application->class" />
                        @if($isProfessionalScheme)
                            <x-show-field label="{{ $schemeId === 4 ? 'Non Professional Course Name' : 'Professional Course Name' }} / प्रोफेशनल कोर्स का नाम" :value="$application->course_name" />
                            <x-show-field label="Course Duration (in Years) / कोर्स अवधि" :value="$application->course_duration" />
                            <x-show-field label="Institute Name / संस्थान का नाम" :value="$application->institution_name" />
                            <x-show-field label="University Name / विश्वविद्यालय का नाम" :value="$application->board_university" />
                        @endif
                    @endif
                    <x-show-field label="Marks Obtained / प्राप्त अंक" :value="$application->marks_obtained" />
                    <x-show-field label="Total Marks / कुल अंक" :value="$application->maximum_marks" />
                    <x-show-field label="Marks in Percentage / प्रतिशत में अंक" :value="$application->percentage" />
                </div>
            </x-card>

            @if($isProfessionalScheme)
                <x-card title="Student Bank Details / छात्र बैंक विवरण" icon="fa-solid fa-building-columns" class="mb-3">
                    <div class="row g-3">
                        <x-show-field label="Account Number / खाता संख्या" :value="$application->student_bank_account_number" />
                        <x-show-field label="Account Holder Name / खाता धारक का नाम" :value="$application->student_bank_account_holder_name" />
                        <x-show-field label="IFSC Code / आईएफएससी कोड" :value="$application->student_bank_ifsc" />
                        <x-show-field label="Bank Name / बैंक का नाम" :value="$application->student_bank_name" />
                        <x-show-field label="Branch Name of Bank / बैंक की शाखा का नाम" :value="$application->student_bank_branch" />
                    </div>
                </x-card>
            @endif

            <x-card title="Attach Document / दस्तावेज़ संलग्न करें" icon="fa-solid fa-paperclip" class="mb-3">
                <div class="row g-3">
                    @foreach($documentLabels as $type => $label)
                        @php($document = $documentMap->get($type))
                        <div class="col-md-6">
                            <div class="small text-muted">{{ $label }}</div>
                            @if($document)
                                <a href="{{ route('applications.documents.show', [$application, $document]) }}" target="_blank" rel="noopener">View {{ str($label)->before('/')->trim() }}</a>
                                <a class="ms-2" href="{{ route('applications.documents.download', [$application, $document]) }}">Download</a>
                            @else
                                <span class="text-muted">Not uploaded</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            @if($application->tendupattaCollections->isNotEmpty() || $verification !== [])
                <x-card title="Collection Details" icon="fa-solid fa-leaf" class="mb-3">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Year</th><th>Collection Quantity in Gaddi</th><th>TP Card Number</th><th>Verified</th></tr></thead>
                            <tbody>
                                @forelse($application->tendupattaCollections as $index => $collection)
                                    <tr>
                                        <td>{{ $collection->collection_year }}</td>
                                        <td>{{ $collection->quantity_gaddi }}</td>
                                        <td>{{ $verification['tp_card'.($index + 1)] ?? 'N/A' }}</td>
                                        <td>{{ $collection->is_verified ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-muted text-center py-3">No collection details recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="row g-3 mt-1">
                        <x-show-field label="Status" :value="$statusLabels[(int) $application->status] ?? $application->status_label" />
                        <x-show-field label="Feedback" :value="$verification['reason'] ?? $application->tendupattaCollections->first()?->remarks" />
                        @if(filled($verification['phadbookfile'] ?? null))
                            <x-show-field label="Phad Book" :value="'Stored in legacy S3/local uploads: '.$verification['phadbookfile']" />
                        @endif
                    </div>
                </x-card>
            @endif

            <x-card title="Document Preview" icon="fa-solid fa-magnifying-glass" class="mb-3">
                <div class="row g-3">
                    @forelse($application->currentDocuments as $document)
                        @if($document->isImage())
                            <div class="col-md-6">
                                <div class="fw-semibold mb-2">{{ $documentLabels[$document->document_type] ?? str_replace('_', ' ', $document->document_type) }}</div>
                                <a href="{{ route('applications.documents.show', [$application, $document]) }}" target="_blank" rel="noopener">
                                    <img class="img-fluid border rounded" style="max-height: 320px; object-fit: contain; width: 100%;" src="{{ route('applications.documents.show', [$application, $document]) }}" alt="{{ $document->displayName() }}">
                                </a>
                            </div>
                        @elseif($document->isPdf())
                            <div class="col-12">
                                <div class="fw-semibold mb-2">{{ $documentLabels[$document->document_type] ?? str_replace('_', ' ', $document->document_type) }}</div>
                                <iframe class="border rounded w-100" style="min-height: 520px;" src="{{ route('applications.documents.show', [$application, $document]) }}" title="{{ $document->displayName() }}"></iframe>
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
                    <dt class="col-sm-5">Application</dt><dd class="col-sm-7">{{ $application->application_number }}</dd>
                    <dt class="col-sm-5">Status</dt><dd class="col-sm-7">{{ $statusLabels[(int) $application->status] ?? $application->status_label }}</dd>
                    <dt class="col-sm-5">Amount</dt><dd class="col-sm-7">₹{{ number_format((float) $application->amount, 2) }}</dd>
                    <dt class="col-sm-5">Payment</dt><dd class="col-sm-7">{{ $application->payment_status ?? 'N/A' }}</dd>
                    <dt class="col-sm-5">Reference</dt><dd class="col-sm-7">{{ $application->payment_reference_id ?? 'N/A' }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
@endsection
