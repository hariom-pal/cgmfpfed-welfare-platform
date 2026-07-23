<?php

declare(strict_types=1);

namespace App\Contracts\Services;

interface ExportDefinitionInterface
{
    /**
     * Stable machine key for this module, e.g. "scholarship_applications".
     */
    public function module(): string;

    /**
     * Human-readable module name shown in the CSV Export Configuration admin screen.
     */
    public function label(): string;

    /**
     * Every field this module can export, in its natural default order.
     *
     * @return array<string, string> field_name => default display label
     */
    public function availableFields(): array;

    /**
     * Resolve a single exported row (already eager-loaded by the caller's query) into
     * field_name => value pairs. Only keys also present in availableFields() are used.
     *
     * @return array<string, string|int|float|null>
     */
    public function resolveRow(mixed $row): array;
}
