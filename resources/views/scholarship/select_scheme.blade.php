@extends('layouts.admin')

@section('title', 'Add Application')
@section('heading', 'Add Scholarship Application')
@section('subtitle', 'Select a scheme to continue')

@php($breadcrumbs = ['Applications' => route('applications.index'), 'Add' => null])

@section('content')
    <x-card title="Select a Scheme to continue to add application" icon="fa-solid fa-list-check">
        <div class="row g-3">
            @forelse($schemes as $scheme)
                <div class="col-md-6">
                    <a class="d-block text-decoration-none" href="{{ route('applications.create.scheme', $scheme) }}">
                        <div class="card text-bg-dark mb-0">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <span class="fw-semibold">{{ $scheme->name }}</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            @empty
                <div class="col-12 text-muted text-center py-4">No active schemes available.</div>
            @endforelse
        </div>
    </x-card>
@endsection
