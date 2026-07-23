@extends('layouts.admin')

@section('title', 'User Management')
@section('heading', 'User Management')
@section('subtitle', 'Manage District Union, Samiti, Investigation Committee and Circle accounts')

@php
    $breadcrumbs = ['User Management' => null];
@endphp

@section('content')
    <x-card title="Users" icon="fa-solid fa-users-gear">
        <x-slot:tools>
            @can('create', \App\Models\User::class)
                <a class="btn btn-primary" href="{{ route('users.create') }}">
                    <i class="fa-solid fa-plus me-1"></i>Create User
                </a>
            @endcan
            <a class="btn btn-outline-secondary" href="{{ route('users.export', $filters) }}">
                <i class="fa-solid fa-file-csv me-1"></i>Download CSV
            </a>
        </x-slot:tools>

        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label" for="name">Name</label>
                <input id="name" name="name" class="form-control" value="{{ $filters['name'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" name="email" class="form-control" value="{{ $filters['email'] ?? '' }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="user_type">Role</label>
                <select id="user_type" name="user_type" class="form-select">
                    <option value="">All</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(($filters['user_type'] ?? '') == $role->id)>{{ $role->type }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="1" @selected(($filters['status'] ?? '') === '1')>Active</option>
                    <option value="0" @selected(($filters['status'] ?? '') === '0')>Inactive</option>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-primary" type="submit"><i class="fa-solid fa-magnifying-glass me-1"></i>Search</button>
                <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>District Union</th>
                    <th>Samiti</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @forelse($records as $record)
                    <tr>
                        <td class="fw-semibold">{{ $record->name }}</td>
                        <td>{{ $record->email }}</td>
                        <td>{{ $record->mobile }}</td>
                        <td>{{ $record->role?->type }}</td>
                        <td>{{ $record->districtUnionMaster?->name ?? $record->circleMaster?->name }}</td>
                        <td>{{ $record->samitiMaster?->name }}</td>
                        <td><x-status-badge :active="$record->status === '1'" /></td>
                        <td class="text-end">
                            @can('update', $record)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $record) }}"><i class="fa-regular fa-pen-to-square"></i></a>
                                <form method="POST" action="{{ route('users.toggle', $record) }}" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        {{ $record->status === '1' ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No users found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pt-3"><x-pagination :records="$records" /></div>
    </x-card>
@endsection
