@extends('layouts.admin')

@section('title', 'CSV Export Configuration')
@section('heading', 'CSV Export Configuration')
@section('subtitle', 'Choose which columns each module exports, their order, and their display names')

@section('content')
    <x-card title="Modules" icon="fa-solid fa-file-csv">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Module</th>
                    <th>Available Fields</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                @foreach($definitions as $definition)
                    <tr>
                        <td class="fw-semibold">{{ $definition->label() }}</td>
                        <td>{{ count($definition->availableFields()) }}</td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('export-templates.edit', $definition->module()) }}">
                                <i class="fa-solid fa-sliders me-1"></i>Configure
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
@endsection
