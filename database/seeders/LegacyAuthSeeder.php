<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class LegacyAuthSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'priviledge',
        'role_priviledge',
        'user_type',
        'users',
    ];

    public function run(): void
    {
        $sql = file_get_contents(database_path('legacy/scholarship_auth.sql'));

        if ($sql === false) {
            throw new RuntimeException('Unable to read legacy auth seed data.');
        }

        try {
            Schema::disableForeignKeyConstraints();

            DB::transaction(function () use ($sql): void {
                foreach ($this->tables as $table) {
                    DB::table($table)->delete();

                    foreach (array_chunk($this->recordsForTable($sql, $table), 500) as $records) {
                        DB::table($table)->insert($records);
                    }
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recordsForTable(string $sql, string $table): array
    {
        $pattern = '/INSERT INTO `'.preg_quote($table, '/').'` \\((.*?)\\) VALUES\\s*(.*?);/s';

        if (! preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $records = [];

        foreach ($matches as $match) {
            preg_match_all('/`([^`]+)`/', $match[1], $columnMatches);
            $columns = $columnMatches[1];

            foreach ($this->splitTuples($match[2]) as $tuple) {
                $values = str_getcsv($tuple, ',', "'", '\\');
                $records[] = array_combine($columns, array_map($this->normalizeValue(...), $values));
            }
        }

        return $records;
    }

    /**
     * @return list<string>
     */
    private function splitTuples(string $valuesSql): array
    {
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $length = strlen($valuesSql);

        for ($index = 0; $index < $length; $index++) {
            $char = $valuesSql[$index];

            if ($char === "'" && ($index === 0 || $valuesSql[$index - 1] !== '\\')) {
                $inString = ! $inString;
            }

            if (! $inString && $char === '(') {
                $depth++;

                if ($depth === 1) {
                    $buffer = '';

                    continue;
                }
            }

            if (! $inString && $char === ')') {
                $depth--;

                if ($depth === 0) {
                    $tuples[] = $buffer;

                    continue;
                }
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    private function normalizeValue(string $value): mixed
    {
        $trimmed = trim($value);

        if (strtoupper($trimmed) === 'NULL') {
            return null;
        }

        return $trimmed;
    }
}
