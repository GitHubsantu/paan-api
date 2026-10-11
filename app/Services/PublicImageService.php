<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicImageService
{
    public function store(UploadedFile $file, string $directory): string
    {
        $disk = Storage::disk(config('filesystems.default'));
        $id = Str::lower(Str::random(20));
        $originalPath = "{$directory}/originals/{$id}.{$file->extension()}";
        $largePath = "{$directory}/{$id}.webp";
        $thumbnailPath = "{$directory}/{$id}-thumb.webp";

        $disk->put($originalPath, file_get_contents($file->getRealPath()));
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            return $originalPath;
        }

        $this->storeWebp($disk, $source, $largePath, 1080);
        $this->storeWebp($disk, $source, $thumbnailPath, 300);
        imagedestroy($source);

        return $largePath;
    }

    private function storeWebp($disk, \GdImage $source, string $path, int $maxWidth): void
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min($width, $maxWidth);
        $targetHeight = max(1, (int) round(($height / $width) * $targetWidth));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagewebp($target, null, 85);
        $disk->put($path, ob_get_clean());
        imagedestroy($target);
    }
}
