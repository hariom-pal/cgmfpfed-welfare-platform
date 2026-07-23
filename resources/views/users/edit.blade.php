@extends('layouts.admin')

@section('title', 'Edit User')
@section('heading', 'Edit User — '.$record->name)
@section('subtitle', app(\App\Services\RoleService::class)->name($record))

@php
    $breadcrumbs = ['User Management' => route('users.index'), 'Edit' => null];
@endphp

@section('content')
    <x-card title="Edit User" icon="fa-regular fa-pen-to-square">
        <form method="POST" action="{{ route('users.update', $record) }}">
            @csrf
            @method('PUT')
            @include('users._form', ['mode' => 'edit'])
        </form>
    </x-card>
@endsection
