@extends('layouts.admin')

@section('title', 'CSV Export Configuration — '.$definition->label())
@section('heading', 'CSV Export Configuration')
@section('subtitle', $definition->label())

@section('content')
    <x-card title="Columns" icon="fa-solid fa-file-csv">
        <x-slot:tools>
            <a class="btn btn-outline-secondary" href="{{ route('settings.csv-export-configuration.index') }}">
                <i class="fa-solid fa-arrow-left me-1"></i>Back to Modules
            </a>
        </x-slot:tools>

        <p class="text-muted small">
            Drag rows with <i class="fa-solid fa-grip-vertical"></i> to reorder columns. Untick a field to exclude it from the CSV. This order and visibility is what every "Download CSV" export for {{ $definition->label() }} will use.
        </p>

        <form method="POST" action="{{ route('settings.csv-export-configuration.update', $module) }}" id="export-template-form">
            @csrf
            @method('PUT')

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                    <tr>
                        <th style="width: 2rem"></th>
                        <th>Field</th>
                        <th>Display Name</th>
                        <th class="text-center" style="width: 6rem">Visible</th>
                    </tr>
                    </thead>
                    <tbody id="fields-body">
                    @foreach($fields as $index => $field)
                        <tr draggable="true" class="export-field-row">
                            <td class="text-muted" style="cursor: grab"><i class="fa-solid fa-grip-vertical"></i></td>
                            <td>
                                <code>{{ $field['field_name'] }}</code>
                                <input type="hidden" name="fields[{{ $index }}][field_name]" value="{{ $field['field_name'] }}">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="fields[{{ $index }}][display_name]" value="{{ $field['display_name'] }}" required maxlength="255">
                            </td>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input" name="fields[{{ $index }}][is_visible]" value="1" @checked($field['is_visible'])>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pt-3">
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save Configuration</button>
            </div>
        </form>
    </x-card>
@endsection

@push('scripts')
    <script>
        (() => {
            const body = document.getElementById('fields-body');
            const form = document.getElementById('export-template-form');
            let dragged = null;

            body.addEventListener('dragstart', (event) => {
                const row = event.target.closest('.export-field-row');
                if (!row) return;
                dragged = row;
                event.dataTransfer.effectAllowed = 'move';
            });

            body.addEventListener('dragover', (event) => {
                event.preventDefault();
                const target = event.target.closest('.export-field-row');
                if (!target || target === dragged) return;

                const rect = target.getBoundingClientRect();
                const before = (event.clientY - rect.top) < rect.height / 2;
                body.insertBefore(dragged, before ? target : target.nextSibling);
            });

            form.addEventListener('submit', () => {
                body.querySelectorAll('.export-field-row').forEach((row, index) => {
                    row.querySelectorAll('input[name]').forEach((input) => {
                        input.name = input.name.replace(/fields\[\d+\]/, `fields[${index}]`);
                    });
                });
            });
        })();
    </script>
@endpush
