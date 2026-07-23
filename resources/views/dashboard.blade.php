@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')
@section('subtitle', 'Operational overview for scholarship welfare administration')

@php
    $breadcrumbs = ['Dashboard' => null];
@endphp

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
            <a class="btn btn-outline-secondary" href="{{ route('dashboard', array_filter(['scheme' => $currentScheme?->id ?? null])) }}">Reset</a>
        </div>
    </form>

    <div class="row g-3 mb-4">
        @foreach($cards as $card)
            <div class="col-sm-6 col-xl-3">
                @php
                    $href = $card['href'] ?? (($card['route'] ?? null) ? route('masters.index', $card['route']) : null);
                @endphp
                <a class="text-decoration-none text-reset" href="{{ $href ?? '#' }}" @if(!$href) aria-disabled="true" @endif>
                    <div class="app-card stat-card p-3 h-100">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="display-6 fw-semibold">{{ $card['value'] }}</div>
                        <div class="small text-{{ $card['color'] }}">{{ $href ? 'View list' : 'Coming soon' }}</div>
                        <i class="icon fa-solid {{ $card['icon'] }} text-{{ $card['color'] }}"></i>
                    </div>
                </a>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-card title="Master Data Health" icon="fa-solid fa-chart-simple">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>Master</th>
                            <th>Total</th>
                            <th>Active</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($masterCards->take(8) as $master)
                            <tr>
                                <td>{{ $master['label'] }}</td>
                                <td>{{ $master['total'] }}</td>
                                <td>{{ $master['active'] }}</td>
                                <td class="text-end"><a href="{{ route('masters.index', $master['route']) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>
        <div class="col-lg-5">
            <x-card title="Recent Activities" icon="fa-regular fa-clock">
                <ul class="list-group list-group-flush">
                    @foreach($activities as $activity)
                        <li class="list-group-item px-0 d-flex gap-2">
                            <i class="fa-solid fa-circle-check text-success mt-1"></i>
                            <span>{{ $activity }}</span>
                        </li>
                    @endforeach
                </ul>
                <div class="mt-3 p-3 bg-light rounded">
                    <div class="text-muted small">Recent Login</div>
                    <div class="fw-semibold">admin via Local Login</div>
                </div>
            </x-card>
        </div>
    </div>
@endsection
