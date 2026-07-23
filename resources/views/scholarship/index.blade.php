@extends('layouts.admin')

@php($statusLabels = [
    'pending' => 'Pending',
    'pending_vle' => 'Pending at VLE',
    'rejected' => 'Rejected',
    'completed' => 'Completed',
    'payment_failed' => 'Payment Failed',
])
@php($currentStatus = $filters['status'] ?? null)
@php($currentStatusLabel = $statusLabels[$currentStatus] ?? null)

@section('title', 'Scholarship Applications')
@section('heading', ($selectedScheme?->name ?? 'Scholarship Applications').($currentStatusLabel ? ' — '.$currentStatusLabel : ''))
@section('subtitle', 'Scheme-wise application list')

@section('content')
    <div class="alert alert-info d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><span class="fw-semibold">Current Scheme:</span> {{ $selectedScheme?->name }}</div>
        <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.index') }}">Change Scheme</a>
    </div>

    @php($currentSessionSelected = $sessions->firstWhere('id', $filters['academic_session_id'] ?? null))
    <div class="alert alert-secondary d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div><span class="fw-semibold">Academic Session:</span> {{ $currentSessionSelected?->name ?? 'All Sessions' }}</div>
        <div class="small text-muted">Use the Academic Session filter below to view a different session.</div>
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
            @if($currentStatus)
                <input type="hidden" name="status" value="{{ $currentStatus }}">
            @endif
            <div class="col-md-3">
                <label class="form-label" for="academic_session_id">Academic Session</label>
                <select class="form-select" id="academic_session_id" name="academic_session_id">
                    <option value="">All</option>
                    @foreach($sessions as $session)
                        <option value="{{ $session->id }}" @selected(($filters['academic_session_id'] ?? '') == $session->id)>{{ $session->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="district_union_id">District Union</label>
                <select class="form-select" id="district_union_id" name="district_union_id">
                    <option value="">All</option>
                    @foreach($districtUnions as $union)
                        <option value="{{ $union->id }}" @selected(($filters['district_union_id'] ?? '') == $union->id)>{{ $union->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="samiti_id">Samiti</label>
                <select class="form-select" id="samiti_id" name="samiti_id" data-selected="{{ $filters['samiti_id'] ?? '' }}">
                    <option value="">All</option>
                    @foreach($samitis as $samiti)
                        <option value="{{ $samiti->id }}" @selected(($filters['samiti_id'] ?? '') == $samiti->id)>{{ $samiti->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="phad_id">Phad</label>
                <select class="form-select" id="phad_id" name="phad_id" data-selected="{{ $filters['phad_id'] ?? '' }}">
                    <option value="">All</option>
                    @foreach($phads as $phad)
                        <option value="{{ $phad->id }}" @selected(($filters['phad_id'] ?? '') == $phad->id)>{{ $phad->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="application_number">Application Number</label>
                <input class="form-control" id="application_number" name="application_number" value="{{ $filters['application_number'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="aadhaar_number">Aadhaar Number</label>
                <input class="form-control" id="aadhaar_number" name="aadhaar_number" value="{{ $filters['aadhaar_number'] ?? '' }}" maxlength="12">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="student_name">Student Name</label>
                <input class="form-control" id="student_name" name="student_name" value="{{ $filters['student_name'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="last_action_role">Last Action Role</label>
                <select class="form-select" id="last_action_role" name="last_action_role">
                    <option value="">All</option>
                    @foreach($lastActionRoles as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['last_action_role'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="last_action_from_date">Last Action From</label>
                <input class="form-control" type="date" id="last_action_from_date" name="last_action_from_date" value="{{ $filters['last_action_from_date'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="last_action_to_date">Last Action To</label>
                <input class="form-control" type="date" id="last_action_to_date" name="last_action_to_date" value="{{ $filters['last_action_to_date'] ?? '' }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
                <a class="btn btn-outline-secondary" href="{{ route('applications.index', ['scheme' => $filters['scheme_id']]) }}">Reset</a>
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
                    <th>Last Action</th>
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
                        <td>{{ $application->latestWorkflowTransition?->acted_at?->format('d M Y H:i') ?? 'N/A' }}</td>
                        <td class="text-end">₹{{ number_format((float) $application->amount, 2) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('applications.show', ['application' => $application, 'scheme' => $filters['scheme_id']]) }}"><i class="fa-regular fa-eye"></i></a>
                            @can('update', $application)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('applications.edit', ['application' => $application, 'scheme' => $filters['scheme_id']]) }}"><i class="fa-regular fa-pen-to-square"></i></a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">No applications found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$applications" /></div>
    </x-card>
@endsection

@push('scripts')
    <script>
        (() => {
            const districtUnion = document.getElementById('district_union_id');
            const samiti = document.getElementById('samiti_id');
            const phad = document.getElementById('phad_id');

            const fill = (select, items, selected = '') => {
                select.innerHTML = '<option value="">All</option>';
                items.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = item.name;
                    option.selected = String(item.id) === String(selected);
                    select.appendChild(option);
                });
            };

            const load = async (target, endpoint, params, selected = '') => {
                const query = new URLSearchParams(params);
                const response = await fetch(`/api/scholarship/lookups/${endpoint}?${query.toString()}`, {
                    headers: {'Accept': 'application/json'},
                });
                fill(target, await response.json(), selected);
            };

            districtUnion?.addEventListener('change', async () => {
                await load(samiti, 'samitis', {district_union_id: districtUnion.value});
                fill(phad, []);
            });

            samiti?.addEventListener('change', () => {
                load(phad, 'phads', {samiti_id: samiti.value});
            });
        })();
    </script>
@endpush
