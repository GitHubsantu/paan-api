<?php

namespace App\Support;

class Phone
{
    public const REGEX = '/^[6-9][0-9]{9}$/';

    /** Normalizes +91 / 91 / 0 prefixed Indian numbers to the 10-digit form. */
    public static function normalize(?string $value): string
    {
        $d = preg_replace('/\D+/', '', (string) $value);

        if (strlen($d) === 12 && str_starts_with($d, '91')) {
            return substr($d, 2);
        }
        if (strlen($d) === 11 && str_starts_with($d, '0')) {
            return substr($d, 1);
        }

        return $d;
    }
}
