@extends('layouts.admin')

@section('title', 'Edit '.$label)
@section('heading', 'Edit '.$label)
@section('subtitle', 'Update '.$record->name)

@php
    $breadcrumbs = ['Master Management' => null, $label => route('masters.index', $masterKey), 'Edit' => null];
@endphp

@section('content')
    <x-card :title="'Edit '.$label" icon="fa-regular fa-pen-to-square">
        <form method="POST" action="{{ route('masters.update', [$masterKey, $record->uuid]) }}">
            @include('masters._form', ['method' => 'PUT'])
        </form>
    </x-card>
@endsection
