<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Contracts\Services\ExportDefinitionInterface;

/**
 * Maps a module key to its exportable-field definition, driven entirely by
 * config('exports.modules'). Adding a future module (Beema, Users, Reports,
 * Payments) only requires one config entry plus its ExportDefinitionInterface
 * implementation — no changes to this class or to CsvExportService.
 */
final class ExportDefinitionRegistry
{
    /**
     * @return array<string, ExportDefinitionInterface>
     */
    public function all(): array
    {
        return collect(config('exports.modules', []))
            ->mapWithKeys(fn (string $class, string $module): array => [$module => app($class)])
            ->all();
    }

    public function get(string $module): ExportDefinitionInterface
    {
        $class = config("exports.modules.{$module}");
        abort_unless(is_string($class), 404);

        return app($class);
    }
}
