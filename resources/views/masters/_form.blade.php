@csrf
@if($method ?? null)
    @method($method)
@endif
<div class="row g-3">
    <div class="col-md-4">
        <label for="code" class="form-label">Code</label>
        <input id="code" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $record->code ?? '') }}" maxlength="40" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-8">
        <label for="name" class="form-label">Name</label>
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
    <button class="btn btn-primary" type="submit">Save</button>
    <a class="btn btn-outline-secondary" href="{{ route('masters.index', $masterKey) }}">Cancel</a>
</div>