@extends('layouts.admin')

@section('title', $record->name)
@section('heading', $label)
@section('subtitle', 'View master record details')

@php
    $breadcrumbs = ['Master Management' => null, $label => route('masters.index', $masterKey), $record->name => null];
@endphp

@section('content')
    <x-card :title="$record->name" icon="fa-regular fa-eye">
        <dl class="row mb-0">
            <dt class="col-sm-3">Code</dt><dd class="col-sm-9">{{ $record->code }}</dd>
            <dt class="col-sm-3">Name</dt><dd class="col-sm-9">{{ $record->name }}</dd>
            <dt class="col-sm-3">Description</dt><dd class="col-sm-9">{{ $record->description ?: 'Not provided' }}</dd>
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><x-status-badge :active="$record->is_active" /></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $record->created_at?->format('d M Y H:i') }}</dd>
        </dl>
        <div class="mt-4 d-flex gap-2">
            <a class="btn btn-primary" href="{{ route('masters.edit', [$masterKey, $record->uuid]) }}">Edit</a>
            <a class="btn btn-outline-secondary" href="{{ route('masters.index', $masterKey) }}">Back</a>
        </div>
    </x-card>
@endsection
