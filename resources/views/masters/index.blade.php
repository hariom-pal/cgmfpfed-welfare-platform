@extends('layouts.admin')

@section('title', $label)
@section('heading', $label)
@section('subtitle', 'Manage '.$label.' records')

@php
    $breadcrumbs = ['Master Management' => null, $label => null];
@endphp

@section('content')
    <x-card :title="$label.' Records'" icon="fa-solid fa-table-list" class="mb-3">
        <x-slot:tools>
            <a class="btn btn-primary" href="{{ route('masters.create', $masterKey) }}">
                <i class="fa-solid fa-plus me-1"></i>Create {{ $label }}
            </a>
            <button class="btn btn-outline-secondary" type="button" disabled>
                <i class="fa-solid fa-file-export me-1"></i>Export
            </button>
        </x-slot:tools>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-6 col-lg-5">
                <label class="form-label" for="search">Search</label>
                <input id="search" name="search" class="form-control" placeholder="Search by code, name, or description" value="{{ request('search') }}">
            </div>
            <div class="col-md-3 col-lg-2">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="1" @selected(request('status') === '1')>Active</option>
                    <option value="0" @selected(request('status') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 col-lg-3 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit">
                    <i class="fa-solid fa-magnifying-glass me-1"></i>Search
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('masters.index', $masterKey) }}">Reset</a>
            </div>
        </form>

        @if($records->count() > 0)
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        @php
                            $fieldLabels = collect($master['fields'])->mapWithKeys(fn ($field) => [$field['name'] => $field['label']]);
                            $columns = collect($master['display_columns'])->mapWithKeys(fn ($column) => [$column => $fieldLabels[$column] ?? str($column)->headline()->toString()])->all();
                            $columns['is_active'] = 'Status';
                            $columns['created_at'] = 'Created';
                        @endphp
                        @foreach($columns as $column => $title)
                            @php
                                $nextDirection = request('sort') === $column && request('direction') !== 'desc' ? 'desc' : 'asc';
                                $icon = request('sort') === $column && request('direction') === 'desc' ? 'fa-sort-down' : 'fa-sort-up';
                            @endphp
                            <th>
                                <a class="text-decoration-none text-reset" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection]) }}">
                                    {{ $title }} <i class="fa-solid {{ request('sort') === $column ? $icon : 'fa-sort' }} ms-1 text-muted"></i>
                                </a>
                            </th>
                        @endforeach
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($records as $record)
                        <tr>
                            @foreach($columns as $column => $title)
                                <td @class(['fw-semibold' => $loop->first])>
                                    @if($column === 'is_active')
                                        <x-status-badge :active="$record->is_active" />
                                    @elseif($column === 'created_at')
                                        {{ $record->created_at?->format('d M Y') }}
                                    @else
                                        @php($value = $record->getAttribute($column))
                                        {{ $value instanceof \Carbon\CarbonInterface ? $value->format('d M Y') : $value }}
                                    @endif
                                </td>
                            @endforeach
                            <td class="text-end">
                                <x-action-buttons :master-key="$masterKey" :record="$record" />
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pt-3">
                <x-pagination :records="$records" />
            </div>
        @else
            <x-empty-state :action="route('masters.create', $masterKey)" :action-label="'Create '.$label" />
        @endif
    </x-card>

    <x-modal />
@endsection
