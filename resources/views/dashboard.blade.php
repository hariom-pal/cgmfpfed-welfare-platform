@extends('layouts.admin')

@section('title', 'Dashboard')
@section('heading', 'Dashboard')

@section('content')
    <div class="row g-3">
        @foreach($cards as $card)
            <div class="col-sm-6 col-xl-3">
                <a class="text-decoration-none text-reset" href="{{ route('masters.index', $card['route']) }}">
                    <div class="table-card p-3 h-100">
                        <div class="text-muted small">{{ $card['label'] }}</div>
                        <div class="display-6 fw-semibold">{{ $card['total'] }}</div>
                        <div class="small text-success">{{ $card['active'] }} active</div>
                    </div>
                </a>
            </div>
        @endforeach
    </div>
@endsection