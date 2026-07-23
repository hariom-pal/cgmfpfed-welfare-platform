<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\UserManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255', 'regex:/^[A-Za-z ]+[A-Za-z\'\- ]*$/'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'mobile' => ['required', 'digits:10', 'unique:users,mobile'],
            // The assignable internal staff roles (confirmed against the legacy `user_type`
            // table): District Union, Samiti, Investigation Committee, Circle, Account. Super
            // Admin (1) is never creatable via this screen (matching legacy), and VLE is a
            // separate, self-service CSC-provisioned identity, not an internal staff role —
            // both are deliberately excluded rather than checked via `exists:user_type,id`,
            // which would depend on that lookup table being seeded.
            'user_type' => ['required', 'integer', Rule::in(UserManagementService::ASSIGNABLE_ROLES)],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'status' => ['required', 'in:0,1'],
            // Legacy: District is shown/required for every role except Circle (5), which shows
            // Circle instead; District Union is always required regardless of role; Samiti only
            // applies to role 3.
            'district_id' => ['required_unless:user_type,5', 'nullable', 'integer', 'exists:districts,id'],
            'circle_id' => ['required_if:user_type,5', 'nullable', 'integer', 'exists:circles,id'],
            'district_union_id' => ['required', 'integer', 'exists:district_unions,id'],
            'samiti_id' => ['required_if:user_type,3', 'nullable', 'integer', 'exists:samitis,id'],
        ];
    }
}
