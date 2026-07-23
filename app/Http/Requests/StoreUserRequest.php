<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
            // The only 4 assignable internal staff roles (confirmed against the legacy `user_type`
            // table): District Union, Samiti, Investigation Committee, Circle. Super Admin (1) is
            // never creatable via this screen (matching legacy), and VLE is a separate, self-service
            // CSC-provisioned identity, not an internal staff role — both are deliberately excluded
            // rather than checked via `exists:user_type,id`, which would depend on that lookup
            // table being seeded.
            'user_type' => ['required', 'integer', Rule::in([2, 3, 4, 5])],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'status' => ['required', 'in:0,1'],
            'circle_id' => ['required_if:user_type,5', 'nullable', 'integer', 'exists:circles,id'],
            'district_union_id' => ['required', 'integer', 'exists:district_unions,id'],
            'samiti_id' => ['required_if:user_type,3', 'nullable', 'integer', 'exists:samitis,id'],
        ];
    }
}
