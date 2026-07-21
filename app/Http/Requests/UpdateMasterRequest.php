<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MasterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMasterRequest extends FormRequest
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
        $record = app($master['model'])->newQuery()->where('uuid', $this->route('uuid'))->firstOrFail();

        return [
            'code' => [
                'required',
                'string',
                'max:40',
                Rule::unique($table, 'code')->ignore($record->getKey())->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($table, 'name')->ignore($record->getKey())->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
