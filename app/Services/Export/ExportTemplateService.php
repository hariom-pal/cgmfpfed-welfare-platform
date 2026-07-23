<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Contracts\Services\ExportDefinitionInterface;
use App\Models\ExportTemplate;
use App\Models\ExportTemplateField;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reads and writes the database-driven column configuration behind CSV exports.
 * Each module has exactly one default template today (Phase 1); the schema
 * already supports multiple named templates per module for a future phase.
 */
final class ExportTemplateService
{
    public function __construct(private readonly ExportDefinitionRegistry $registry) {}

    /**
     * @return list<ExportDefinitionInterface>
     */
    public function definitions(): array
    {
        return array_values($this->registry->all());
    }

    public function definitionFor(string $module): ExportDefinitionInterface
    {
        return $this->registry->get($module);
    }

    public function templateFor(string $module, ?User $user = null): ExportTemplate
    {
        $definition = $this->definitionFor($module);

        $template = ExportTemplate::query()->firstOrCreate(
            ['module' => $module, 'is_default' => true],
            [
                'name' => $definition->label().' — Default',
                'created_by' => $user?->id,
                'updated_by' => $user?->id,
            ],
        );

        if ($template->fields()->count() === 0) {
            $this->seedFields($template, $definition);
        }

        return $template;
    }

    /**
     * Merged, ordered view of a module's fields: previously-saved rows first (in the
     * admin's chosen order), then any field the code now defines that the template
     * hasn't recorded yet — appended as hidden. Enabling a new column is then a pure
     * admin-UI action; no code change or migration is needed.
     *
     * @return list<array{field_name: string, display_name: string, is_visible: bool, column_order: int}>
     */
    public function fieldsFor(string $module): array
    {
        $template = $this->templateFor($module);
        $known = $template->fields()->get();

        $rows = $known->map(fn (ExportTemplateField $field): array => [
            'field_name' => $field->field_name,
            'display_name' => $field->display_name,
            'is_visible' => $field->is_visible,
            'column_order' => $field->column_order,
        ])->all();

        $seen = $known->pluck('field_name')->all();
        $nextOrder = ((int) $known->max('column_order')) + 1;

        foreach ($this->definitionFor($module)->availableFields() as $fieldName => $label) {
            if (in_array($fieldName, $seen, true)) {
                continue;
            }

            $rows[] = [
                'field_name' => $fieldName,
                'display_name' => $label,
                'is_visible' => false,
                'column_order' => $nextOrder++,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, string> field_name => display_name, visible fields only, in column order
     */
    public function activeColumns(string $module): array
    {
        return collect($this->fieldsFor($module))
            ->filter(fn (array $field): bool => $field['is_visible'])
            ->sortBy('column_order')
            ->mapWithKeys(fn (array $field): array => [$field['field_name'] => $field['display_name']])
            ->all();
    }

    /**
     * @param  list<array{field_name: string, display_name: string, is_visible: bool}>  $fields  in the admin's chosen display order
     */
    public function save(string $module, array $fields, ?User $user): void
    {
        $template = $this->templateFor($module, $user);

        DB::transaction(function () use ($template, $fields, $user): void {
            foreach ($fields as $order => $field) {
                ExportTemplateField::query()->updateOrCreate(
                    ['template_id' => $template->id, 'field_name' => $field['field_name']],
                    [
                        'display_name' => $field['display_name'],
                        'is_visible' => $field['is_visible'],
                        'column_order' => $order,
                    ],
                );
            }

            $template->update(['updated_by' => $user?->id]);
        });
    }

    private function seedFields(ExportTemplate $template, ExportDefinitionInterface $definition): void
    {
        $order = 0;
        $rows = [];
        foreach ($definition->availableFields() as $fieldName => $label) {
            $rows[] = [
                'template_id' => $template->id,
                'field_name' => $fieldName,
                'display_name' => $label,
                'column_order' => $order++,
                'is_visible' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            DB::table('export_template_fields')->insert($rows);
        }
    }
}
