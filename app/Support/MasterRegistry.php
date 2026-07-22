<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class MasterRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        /** @var array<string, array<string, mixed>> $masters */
        $masters = config('masters');

        return collect($masters)
            ->map(fn (array $master): array => $this->normalize($master))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $key): array
    {
        $masters = $this->all();

        if (! isset($masters[$key])) {
            throw new InvalidArgumentException("Unknown master [{$key}].");
        }

        return $masters[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $master): array
    {
        $master['fields'] ??= [
            ['name' => 'code', 'label' => 'Code', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 40],
            ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'required' => true, 'unique' => true, 'max' => 255],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'required' => false, 'max' => 2000],
        ];
        $master['search_columns'] ??= collect($master['fields'])
            ->whereIn('type', ['text', 'textarea'])
            ->pluck('name')
            ->all();
        $master['display_columns'] ??= collect($master['fields'])
            ->whereIn('name', ['code', 'legacy_code', 'name'])
            ->pluck('name')
            ->all();
        $master['sort_columns'] ??= array_values(array_unique([
            ...$master['display_columns'],
            'is_active',
            'created_at',
        ]));

        return $master;
    }
}
