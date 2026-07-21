@extends('layouts.admin')

@section('title', $title)
@section('heading', $title)
@section('subtitle', 'Module workspace prepared for Phase 3 implementation')

@php
    $breadcrumbs = [$title => null];
@endphp

@section('content')
    <x-card :title="$title.' Module'" icon="fa-solid fa-compass">
        <div class="empty-state">
            <div class="empty-state-icon mb-3"><i class="fa-solid fa-hourglass-half"></i></div>
            <h2 class="h5">{{ $title }} is scheduled for the next release phase.</h2>
            <p class="text-muted mb-3">Navigation, layout, and access flow are ready for business demonstration.</p>
            <a class="btn btn-primary" href="{{ route('dashboard') }}">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>
    </x-card>
@endsection
