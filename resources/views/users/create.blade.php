@extends('layouts.admin')

@section('title', 'Create User')
@section('heading', 'Create User')
@section('subtitle', 'Add a new District Union, Samiti, Investigation Committee or Circle account')

@php
    $breadcrumbs = ['User Management' => route('users.index'), 'Create' => null];
@endphp

@section('content')
    <x-card title="Create User" icon="fa-solid fa-user-plus">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            @include('users._form', ['mode' => 'create'])
        </form>
    </x-card>
@endsection
