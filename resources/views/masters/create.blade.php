@extends('layouts.admin')

@section('title', 'Create '.$label)
@section('heading', 'Create '.$label)

@section('content')
    <x-breadcrumb :items="[$label => route('masters.index', $masterKey), 'Create' => null]" />
    <div class="table-card p-4">
        <form method="POST" action="{{ route('masters.store', $masterKey) }}">
            @include('masters._form')
        </form>
    </div>
@endsection