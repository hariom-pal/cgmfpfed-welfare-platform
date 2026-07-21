@extends('layouts.admin')

@section('title', 'Create '.$label)
@section('heading', 'Create '.$label)
@section('subtitle', 'Add a new '.$label.' master record')

@php
    $breadcrumbs = ['Master Management' => null, $label => route('masters.index', $masterKey), 'Create' => null];
@endphp

@section('content')
    <x-card :title="'Create '.$label" icon="fa-solid fa-plus">
        <form method="POST" action="{{ route('masters.store', $masterKey) }}">
            @include('masters._form')
        </form>
    </x-card>
@endsection
