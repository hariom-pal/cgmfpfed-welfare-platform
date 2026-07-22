@extends('layouts.admin')

@section('title', $title)
@section('heading', $heading)
@section('subtitle', $subtitle)

@section('content')
    <x-card :title="$cardTitle" icon="fa-solid fa-list-check">
        <div class="row g-3">
            @forelse($schemes as $scheme)
                <div class="col-md-6">
                    <a class="d-block text-decoration-none" href="{{ $scheme['url'] }}">
                        <div class="card text-bg-dark mb-0">
                            <div class="card-body">
                                <div class="d-flex align-items-center justify-content-between gap-3">
                                    <span class="fw-semibold">{{ $scheme['name'] }}</span>
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
