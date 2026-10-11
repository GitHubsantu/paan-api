<?php

namespace App\Support;

class Phone
{
    public const REGEX = '/^[6-9][0-9]{9}$/';

    /** The V1 API accepts only a 10-digit Indian local mobile number. */
    public static function normalize(?string $value): string
    {
        return (string) $value;
    }
}
