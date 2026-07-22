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
            @foreach($master['fields'] as $field)
                @php($value = $record->getAttribute($field['name']))
                <dt class="col-sm-3">{{ $field['label'] }}</dt>
                <dd class="col-sm-9">{{ $value instanceof \Carbon\CarbonInterface ? $value->format('d M Y') : ($value ?: 'Not provided') }}</dd>
            @endforeach
            <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><x-status-badge :active="$record->is_active" /></dd>
            <dt class="col-sm-3">Created</dt><dd class="col-sm-9">{{ $record->created_at?->format('d M Y H:i') }}</dd>
        </dl>
        <div class="mt-4 d-flex gap-2">
            <a class="btn btn-primary" href="{{ route('masters.edit', [$masterKey, $record->uuid]) }}">Edit</a>
            <a class="btn btn-outline-secondary" href="{{ route('masters.index', $masterKey) }}">Back</a>
        </div>
    </x-card>
@endsection
