@php
    $selectedRole = old('user_type', $record->user_type ?? '');
    $selectedDistrictId = old('district_id', $record?->districtUnionMaster?->district_id ?? '');
@endphp

<div class="row g-3">
    @if($mode === 'create')
        <div class="col-md-4">
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="mobile">Mobile</label>
            <input id="mobile" name="mobile" class="form-control @error('mobile') is-invalid @enderror" value="{{ old('mobile') }}" maxlength="10" required>
            @error('mobile')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="user_type">Role</label>
            <select id="user_type" name="user_type" class="form-select @error('user_type') is-invalid @enderror" required>
                <option value="">Select a role</option>
                @foreach($roles as $id => $label)
                    <option value="{{ $id }}" @selected((string) $selectedRole === (string) $id)>{{ $label }}</option>
                @endforeach
            </select>
            @error('user_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @else
        <div class="col-md-4">
            <label class="form-label">Name</label>
            <input class="form-control" value="{{ $record->name }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Email</label>
            <input class="form-control" value="{{ $record->email }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Mobile</label>
            <input class="form-control" value="{{ $record->mobile }}" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Role</label>
            <input class="form-control" value="{{ app(\App\Services\RoleService::class)->name($record) }}" disabled>
        </div>
    @endif

    <div class="col-md-4">
        <label class="form-label" for="status">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="1" @selected((string) old('status', $record->status ?? '1') === '1')>Active</option>
            <option value="0" @selected((string) old('status', $record->status ?? '1') === '0')>Inactive</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4" id="district-field">
        <label class="form-label" for="district_id">District</label>
        <select id="district_id" name="district_id" class="form-select @error('district_id') is-invalid @enderror">
            <option value="">Select a District</option>
            @foreach($districts as $district)
                <option value="{{ $district->id }}" @selected((string) $selectedDistrictId === (string) $district->id)>{{ $district->name }}</option>
            @endforeach
        </select>
        @error('district_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4" id="circle-field" style="display:none">
        <label class="form-label" for="circle_id">Circle</label>
        <select id="circle_id" name="circle_id" class="form-select @error('circle_id') is-invalid @enderror">
            <option value="">Select a Circle</option>
            @foreach($circles as $circle)
                <option value="{{ $circle->id }}" @selected((string) old('circle_id', $record->circle_master_id ?? '') === (string) $circle->id)>{{ $circle->name }}</option>
            @endforeach
        </select>
        @error('circle_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="district_union_id">District Union</label>
        <select id="district_union_id" name="district_union_id" class="form-select @error('district_union_id') is-invalid @enderror" required>
            <option value="">Select a District Union</option>
            @foreach($districtUnions as $districtUnion)
                <option
                    value="{{ $districtUnion->id }}"
                    data-district-id="{{ $districtUnion->district_id }}"
                    data-circle-id="{{ $districtUnion->circle_id }}"
                    @selected((string) old('district_union_id', $record->district_union_master_id ?? '') === (string) $districtUnion->id)
                >{{ $districtUnion->name }}</option>
            @endforeach
        </select>
        @error('district_union_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4" id="samiti-field" style="display:none">
        <label class="form-label" for="samiti_id">Samiti</label>
        <select id="samiti_id" name="samiti_id" class="form-select @error('samiti_id') is-invalid @enderror">
            <option value="">Select a Samiti</option>
            @foreach($samitis as $samiti)
                <option value="{{ $samiti->id }}" data-district-union-id="{{ $samiti->district_union_id }}" @selected((string) old('samiti_id', $record->samiti_master_id ?? '') === (string) $samiti->id)>{{ $samiti->name }}</option>
            @endforeach
        </select>
        @error('samiti_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label" for="password">{{ $mode === 'create' ? 'Password' : 'Reset Password' }}</label>
        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" {{ $mode === 'create' ? 'required' : '' }}>
        <div class="form-text">Min 8 characters, upper &amp; lower case, a number and a symbol.{{ $mode === 'edit' ? ' Leave blank to keep the current password.' : '' }}</div>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="password_confirmation">Confirm Password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" {{ $mode === 'create' ? 'required' : '' }}>
    </div>
</div>

<div class="pt-4 d-flex gap-2">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1"></i>Save</button>
    <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Cancel</a>
</div>

@push('scripts')
    <script>
        (() => {
            const roleSelect = document.getElementById('user_type');
            const districtField = document.getElementById('district-field');
            const circleField = document.getElementById('circle-field');
            const samitiField = document.getElementById('samiti-field');
            const districtSelect = document.getElementById('district_id');
            const circleSelect = document.getElementById('circle_id');
            const districtUnionSelect = document.getElementById('district_union_id');
            const samitiSelect = document.getElementById('samiti_id');
            const ROLE_SAMITI = 3;
            const ROLE_CIRCLE = 5;

            const filterOptions = (select, attribute, value) => {
                Array.from(select.options).forEach((option) => {
                    if (!option.value) { return; }
                    const match = !value || option.dataset[attribute] === String(value);
                    option.hidden = !match;
                });
            };

            const filterDistrictUnions = () => {
                if (circleField.style.display !== 'none') {
                    filterOptions(districtUnionSelect, 'circleId', circleSelect.value);
                } else {
                    filterOptions(districtUnionSelect, 'districtId', districtSelect.value);
                }
            };

            // Legacy add_user/edit_user: District drives the District Union list for every
            // role except Circle (5), which uses Circle instead; Samiti only applies to role 3.
            const applyRoleVisibility = (role) => {
                const isSamiti = String(role) === String(ROLE_SAMITI);
                const isCircle = String(role) === String(ROLE_CIRCLE);

                districtField.style.display = isCircle ? 'none' : '';
                districtSelect.required = !isCircle;

                circleField.style.display = isCircle ? '' : 'none';
                circleSelect.required = isCircle;

                samitiField.style.display = isSamiti ? '' : 'none';
                samitiSelect.required = isSamiti;

                filterDistrictUnions();
            };

            if (roleSelect) {
                roleSelect.addEventListener('change', () => applyRoleVisibility(roleSelect.value));
                applyRoleVisibility(roleSelect.value);
            } else {
                applyRoleVisibility('{{ $record->user_type ?? '' }}');
            }

            districtSelect?.addEventListener('change', filterDistrictUnions);
            circleSelect?.addEventListener('change', filterDistrictUnions);

            districtUnionSelect?.addEventListener('change', () => filterOptions(samitiSelect, 'districtUnionId', districtUnionSelect.value));
            if (samitiField.style.display !== 'none') {
                filterOptions(samitiSelect, 'districtUnionId', districtUnionSelect.value);
            }
        })();
    </script>
@endpush
