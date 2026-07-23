<?php

declare(strict_types=1);

namespace App\Services\Export;

use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Module-agnostic CSV streaming exporter. Column selection/order/labels come from
 * ExportTemplateService (database-driven, see Module 8); row data comes from
 * whatever already-filtered, already-eager-loaded query the caller built (see the
 * Repository layer) — this class never builds its own query, so there is no
 * duplicate filtering logic between the on-screen list and its export.
 */
final class CsvExportService
{
    private const CHUNK_SIZE = 500;

    public function __construct(private readonly ExportTemplateService $templates) {}

    /**
     * @param  Builder<*>  $query
     */
    public function stream(string $module, Builder $query, ?string $filename = null): StreamedResponse
    {
        $definition = $this->templates->definitionFor($module);
        $columns = $this->templates->activeColumns($module);

        if ($columns === []) {
            $columns = $definition->availableFields();
        }

        return response()->streamDownload(function () use ($query, $definition, $columns): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, array_values($columns));

            $query->lazy(self::CHUNK_SIZE)->each(function (mixed $row) use ($handle, $definition, $columns): void {
                $data = $definition->resolveRow($row);

                fputcsv($handle, array_map(
                    static fn (string $field): string => (string) ($data[$field] ?? ''),
                    array_keys($columns),
                ));
            });

            fclose($handle);
        }, $filename ?? $module.'-'.now()->format('Ymd_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
