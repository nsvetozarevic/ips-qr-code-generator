<?php

namespace App\Support;

class SerbianBankAccount
{
    public static function normalize(string $account): string
    {
        $digits = (string) preg_replace('/\D/', '', $account);
        $bank = substr($digits, 0, 3);
        $control = substr($digits, -2);
        $middle = str_pad(substr($digits, 3, -2), 13, '0', STR_PAD_LEFT);

        return $bank.$middle.$control;
    }

    public static function isValidChecksum(string $account18): bool
    {
        if (strlen($account18) !== 18 || ! ctype_digit($account18)) {
            return false;
        }

        $base = substr($account18, 0, 16).'00';
        $remainder = (int) bcmod($base, '97');
        $expected = str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);

        return substr($account18, -2) === $expected;
    }
}
