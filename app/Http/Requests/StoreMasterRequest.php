<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MasterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMasterRequest extends FormRequest
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
        $master = app(MasterRegistry::class)->get((string) $this->route('masterKey'));
        $table = $master['table'];

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique($table, 'code')->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($table, 'name')->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
