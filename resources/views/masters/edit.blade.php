@extends('layouts.admin')

@section('title', 'Edit '.$label)
@section('heading', 'Edit '.$label)

@section('content')
    <x-breadcrumb :items="[$label => route('masters.index', $masterKey), 'Edit' => null]" />
    <div class="table-card p-4">
        <form method="POST" action="{{ route('masters.update', [$masterKey, $record->uuid]) }}">
            @include('masters._form', ['method' => 'PUT'])
        </form>
    </div>
@endsection