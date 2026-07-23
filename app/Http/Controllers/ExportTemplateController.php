<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Export\ExportTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ExportTemplateController extends Controller
{
    public function __construct(private readonly ExportTemplateService $templates) {}

    public function index(): View
    {
        return view('settings.export_templates.index', [
            'definitions' => $this->templates->definitions(),
            'breadcrumbs' => ['Settings' => null, 'CSV Export Configuration' => null],
        ]);
    }

    public function edit(string $module): View
    {
        $definition = $this->templates->definitionFor($module);

        return view('settings.export_templates.edit', [
            'module' => $module,
            'definition' => $definition,
            'fields' => $this->templates->fieldsFor($module),
            'breadcrumbs' => [
                'Settings' => null,
                'CSV Export Configuration' => route('settings.csv-export-configuration.index'),
                $definition->label() => null,
            ],
        ]);
    }

    public function update(Request $request, string $module): RedirectResponse
    {
        $definition = $this->templates->definitionFor($module);
        $knownFields = array_keys($definition->availableFields());

        $data = $request->validate([
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.field_name' => ['required', 'string'],
            'fields.*.display_name' => ['required', 'string', 'max:255'],
            'fields.*.is_visible' => ['nullable', 'boolean'],
        ]);

        $fields = array_map(static fn (array $field): array => [
            'field_name' => $field['field_name'],
            'display_name' => $field['display_name'],
            'is_visible' => (bool) ($field['is_visible'] ?? false),
        ], $data['fields']);

        foreach ($fields as $field) {
            abort_unless(in_array($field['field_name'], $knownFields, true), 422);
        }

        $this->templates->save($module, $fields, $request->user());

        return redirect()->route('settings.csv-export-configuration.edit', $module)->with('status', 'CSV export configuration saved.');
    }
}
