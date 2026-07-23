<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
        $target = $this->route('user');
        $userType = $target instanceof User ? (int) $target->user_type : null;

        return [
            'status' => ['required', 'in:0,1'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'circle_id' => [$userType === 5 ? 'required' : 'nullable', 'integer', 'exists:circles,id'],
            'district_union_id' => ['required', 'integer', 'exists:district_unions,id'],
            'samiti_id' => [$userType === 3 ? 'required' : 'nullable', 'integer', 'exists:samitis,id'],
        ];
    }
}
