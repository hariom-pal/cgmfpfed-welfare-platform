@csrf
@if($method ?? null)
    @method($method)
@endif
<x-form-section :title="$label.' Details'" />
<div class="row g-3">
    <div class="col-md-4">
        <label for="code" class="form-label required">Code</label>
        <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code ?? '') }}" maxlength="40" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="name" class="form-label required">Name</label>
        <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $record->name ?? '') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-12">
        <label for="description" class="form-label">Description</label>
        <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="4">{{ old('description', $record->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
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
