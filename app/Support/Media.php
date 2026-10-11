<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Media
{
    public static function url(?string $path): ?string
    {
        if (! $path) {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }

    public static function thumbnailUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension === '' ? $path : substr($path, 0, -(strlen($extension) + 1));
        $thumbnail = $base.'-thumb.webp';

        return self::url($thumbnail);
    }
}
