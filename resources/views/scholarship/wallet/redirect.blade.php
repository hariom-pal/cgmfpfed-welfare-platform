@extends('layouts.admin')

@section('title', 'CSC Wallet Payment')
@section('heading', 'CSC Wallet Payment')
@section('subtitle', 'Application fee payment')

@php($breadcrumbs = ['Applications' => route('applications.index'), 'Wallet' => null])

@section('content')
    <x-card title="Redirecting to CSC Wallet" icon="fa-solid fa-wallet">
        <p class="text-muted mb-3">Wait while the payment request is sent to CSC Wallet.</p>
        <dl class="row">
            <dt class="col-sm-3">Application</dt>
            <dd class="col-sm-9">{{ $application->application_number ?? 'Draft #'.$application->id }}</dd>
            <dt class="col-sm-3">Reference</dt>
            <dd class="col-sm-9">{{ $transaction->reference }}</dd>
            <dt class="col-sm-3">Amount</dt>
            <dd class="col-sm-9">₹{{ number_format((float) $transaction->amount, 2) }}</dd>
        </dl>
        <form method="POST" action="{{ $gatewayUrl }}" id="wallet-form">
            <input type="hidden" name="message" value="{{ $message }}">
            <button class="btn btn-primary" type="submit"><i class="fa-solid fa-wallet me-1"></i>Continue to CSC Wallet</button>
            @if(app()->environment('testing', 'local'))
                <a class="btn btn-outline-success" href="{{ route('applications.wallet.callback', ['application' => $application, 'mock_success' => 1, 'merchant_txn' => $transaction->reference]) }}">Mock Success</a>
            @endif
        </form>
    </x-card>

    <script>
        window.setTimeout(() => document.getElementById('wallet-form').submit(), 500);
    </script>
@endsection
