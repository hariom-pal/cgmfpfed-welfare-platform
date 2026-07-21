<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

final class MasterRegistry
{
    /**
     * @return array<string, array{label: string, model: class-string, table: string, route: string}>
     */
    public function all(): array
    {
        /** @var array<string, array{label: string, model: class-string, table: string, route: string}> $masters */
        $masters = config('masters');

        return $masters;
    }

    /**
     * @return array{label: string, model: class-string, table: string, route: string}
     */
    public function get(string $key): array
    {
        $masters = $this->all();

        if (! isset($masters[$key])) {
            throw new InvalidArgumentException("Unknown master [{$key}].");
        }

        return $masters[$key];
    }
}
