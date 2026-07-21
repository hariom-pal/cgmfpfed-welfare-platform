@extends('layouts.admin')

@section('title', $label)
@section('heading', $label)

@section('content')
    <x-breadcrumb :items="[$label => null]" />
    <div class="table-card">
        <div class="p-3 border-bottom d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <form method="GET" class="d-flex gap-2">
                <input name="search" class="form-control" placeholder="Search" value="{{ request('search') }}">
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <option value="1" @selected(request('status') === '1')>Active</option>
                    <option value="0" @selected(request('status') === '0')>Inactive</option>
                </select>
                <button class="btn btn-outline-primary" type="submit">Search</button>
            </form>
            <a class="btn btn-primary" href="{{ route('masters.create', $masterKey) }}">Create {{ $label }}</a>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                <tr>
                    @php
                        $columns = ['code' => 'Code', 'name' => 'Name', 'is_active' => 'Status', 'created_at' => 'Created'];
                    @endphp
                    @foreach($columns as $column => $title)
                        @php
                            $nextDirection = request('sort') === $column && request('direction') !== 'desc' ? 'desc' : 'asc';
                        @endphp
                        <th><a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'direction' => $nextDirection]) }}">{{ $title }}</a></th>
                    @endforeach
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($records as $record)
                    <tr>
                        <td class="fw-semibold">{{ $record->code }}</td>
                        <td>{{ $record->name }}</td>
                        <td><x-status-badge :active="$record->is_active" /></td>
                        <td>{{ $record->created_at?->format('d M Y') }}</td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-outline-secondary" href="{{ route('masters.show', [$masterKey, $record->uuid]) }}">View</a>
                                <a class="btn btn-outline-primary" href="{{ route('masters.edit', [$masterKey, $record->uuid]) }}">Edit</a>
                            </div>
                            <form class="d-inline" method="POST" action="{{ route('masters.toggle', [$masterKey, $record->uuid]) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-warning" type="submit">Toggle</button>
                            </form>
                            <form class="d-inline" method="POST" action="{{ route('masters.destroy', [$masterKey, $record->uuid]) }}" onsubmit="return confirm('Delete this record?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-4">No records found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $records->links() }}</div>
    </div>
@endsection