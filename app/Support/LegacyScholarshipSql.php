<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class LegacyScholarshipSql
{
    /**
     * @return list<string>
     */
    public static function tableNames(string $sql): array
    {
        preg_match_all('/CREATE TABLE `([^`]+)`/i', $sql, $matches);

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    public static function createStatements(string $sql): array
    {
        preg_match_all('/CREATE TABLE `[^`]+` \\(.*?\\) ENGINE=.*?;/is', $sql, $matches);

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    public static function alterStatements(string $sql): array
    {
        preg_match_all('/^ALTER TABLE `[^`]+`.*?;/ms', $sql, $matches);

        return $matches[0];
    }

    /**
     * @return list<string>
     */
    public static function insertStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        $capturing = false;

        foreach (preg_split('/\\R/', $sql) ?: [] as $line) {
            if (! $capturing && str_starts_with($line, 'INSERT INTO `')) {
                $capturing = true;
                $buffer = $line.PHP_EOL;

                if (str_ends_with(rtrim($line), ';')) {
                    $statements[] = trim($buffer);
                    $capturing = false;
                    $buffer = '';
                }

                continue;
            }

            if ($capturing) {
                $buffer .= $line.PHP_EOL;

                if (str_ends_with(rtrim($line), ';')) {
                    $statements[] = trim($buffer);
                    $capturing = false;
                    $buffer = '';
                }
            }
        }

        return $statements;
    }

    public static function read(): string
    {
        $path = (string) config('legacy_database.scholarship_sql_path');
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException("Unable to read legacy Scholarship SQL dump at {$path}.");
        }

        return $sql;
    }

    public static function prefixedStatement(string $statement): string
    {
        $prefix = (string) config('legacy_database.table_prefix', 'legacy_');

        $prefixed = preg_replace_callback(
            '/(CREATE TABLE|INSERT INTO|ALTER TABLE) `([^`]+)`/i',
            fn (array $matches): string => $matches[1].' `'.$prefix.$matches[2].'`',
            $statement,
            1,
        ) ?? $statement;

        if (str_starts_with(strtoupper(ltrim($statement)), 'CREATE TABLE')) {
            foreach (['aadharcard', 'tpcard', 'admission_copy', 'passbook', 'admission_receipt', 'filepath'] as $column) {
                $prefixed = preg_replace(
                    '/`'.$column.'` varchar\\(255\\) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci/i',
                    '`'.$column.'` varbinary(255)',
                    $prefixed,
                ) ?? $prefixed;
            }
        }

        return $prefixed;
    }

    public static function sourceTableName(string $statement): ?string
    {
        if (preg_match('/(?:CREATE TABLE|INSERT INTO) `([^`]+)`/i', $statement, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    public static function recordCountInInsert(string $statement): int
    {
        if (preg_match('/\\bVALUES\\b/i', $statement, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return 0;
        }

        $valuesSql = substr($statement, (int) $matches[0][1] + strlen($matches[0][0]));
        $count = 0;
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
            }

            if (! $inString && $char === ')') {
                $depth--;

                if ($depth === 0) {
                    $count++;
                }
            }
        }

        return $count;
    }
}
