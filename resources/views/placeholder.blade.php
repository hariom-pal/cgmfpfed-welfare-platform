@extends('layouts.admin')

@section('title', $title)
@section('heading', $title)

@section('content')
    <div class="table-card p-4">
        <p class="mb-0 text-muted">{{ $title }} workspace is ready for the next module.</p>
    </div>
@endsection