<?php

declare(strict_types=1);

namespace App\Support;

final class LegacyPassword
{
    public static function verify(string $plainTextPassword, string $storedHash): bool
    {
        $sha512Binary = hash('sha512', $plainTextPassword, true);

        if (password_verify($sha512Binary, $storedHash)) {
            return true;
        }

        return password_verify($plainTextPassword, $storedHash);
    }
}
