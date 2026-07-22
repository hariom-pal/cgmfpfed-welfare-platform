@csrf
@if($method ?? null)
    @method($method)
@endif
<x-form-section :title="$label.' Details'" />
<div class="row g-3">
    @foreach($master['fields'] as $field)
        @php
            $name = $field['name'];
            $type = $field['type'] ?? 'text';
            $required = (bool) ($field['required'] ?? false);
            $recordModel = $record ?? null;
            $value = old($name, $recordModel?->getAttribute($name) ?? '');
            $width = $type === 'textarea' ? 'col-12' : ($name === 'name' ? 'col-md-8' : 'col-md-4');
        @endphp
        <div class="{{ $width }}">
            <label for="{{ $name }}" @class(['form-label', 'required' => $required])>{{ $field['label'] }}</label>
            @if($type === 'textarea')
                <textarea id="{{ $name }}" name="{{ $name }}" class="form-control @error($name) is-invalid @enderror" rows="4" @required($required)>{{ $value }}</textarea>
            @else
                <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" class="form-control @error($name) is-invalid @enderror" value="{{ $value instanceof \Carbon\CarbonInterface ? $value->format('Y-m-d') : $value }}" @isset($field['max']) maxlength="{{ $field['max'] }}" @endisset @required($required)>
            @endif
            @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endforeach
    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input id="is_active" name="is_active" value="1" class="form-check-input" type="checkbox" @checked(old('is_active', $record->is_active ?? true))>
            <label for="is_active" class="form-check-label">Active</label>
        </div>
    </div>
</div>
<div class="d-flex gap-2 mt-4">
    <button class="btn btn-primary" name="action" value="save" type="submit">
        <i class="fa-solid fa-floppy-disk me-1"></i>Save
    </button>
    <button class="btn btn-success" name="action" value="continue" type="submit">
        <i class="fa-solid fa-floppy-disk me-1"></i>Save & Continue
    </button>
    <button class="btn btn-outline-warning" type="reset">
        <i class="fa-solid fa-rotate-left me-1"></i>Reset
    </button>
    <a class="btn btn-outline-secondary" href="{{ route('masters.index', $masterKey) }}">Cancel</a>
</div>
