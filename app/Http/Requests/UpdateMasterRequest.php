<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\MasterRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
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

        return $this->rulesFromMaster($master, $table, $record->getKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesFromMaster(array $master, string $table, mixed $recordKey): array
    {
        $rules = ['is_active' => ['sometimes', 'boolean']];

        foreach ($master['fields'] as $field) {
            $name = (string) $field['name'];
            if (! Schema::hasColumn($table, $name)) {
                continue;
            }

            $fieldRules = [(bool) ($field['required'] ?? false) ? 'required' : 'nullable'];
            $fieldRules[] = match ($field['type'] ?? 'text') {
                'date' => 'date',
                default => 'string',
            };

            if (isset($field['max']) && ($field['type'] ?? 'text') !== 'date') {
                $fieldRules[] = 'max:'.(int) $field['max'];
            }

            if ((bool) ($field['unique'] ?? false)) {
                $uniqueRule = Rule::unique($table, $name)->ignore($recordKey);
                if (Schema::hasColumn($table, 'deleted_at')) {
                    $uniqueRule->whereNull('deleted_at');
                }
                $fieldRules[] = $uniqueRule;
            }

            $rules[$name] = $fieldRules;
        }

        return $rules;
    }
}
