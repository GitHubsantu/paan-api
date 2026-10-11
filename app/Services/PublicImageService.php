<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicImageService
{
    public function store(UploadedFile $file, string $directory): string
    {
        $disk = Storage::disk('public');
        $id = Str::lower(Str::random(20));
        $originalPath = "{$directory}/originals/{$id}.{$file->extension()}";
        $largePath = "{$directory}/{$id}.webp";
        $thumbnailPath = "{$directory}/{$id}-thumb.webp";

        $disk->put($originalPath, file_get_contents($file->getRealPath()));
        $source = imagecreatefromstring(file_get_contents($file->getRealPath()));

        if ($source === false) {
            return $originalPath;
        }

        $source = $this->orient($source, $file);

        $this->storeWebp($disk, $source, $largePath, 1080);
        $this->storeWebp($disk, $source, $thumbnailPath, 300);
        imagedestroy($source);

        return $largePath;
    }

    public function delete(?string $path): void
    {
        if (! $path || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $base = $extension === '' ? $path : substr($path, 0, -(strlen($extension) + 1));
        $directory = dirname($path);
        $filename = basename($base);
        $original = "{$directory}/originals/{$filename}";
        $disk = Storage::disk('public');

        $disk->delete([$path, "{$base}-thumb.webp", "{$original}.jpg", "{$original}.jpeg", "{$original}.png", "{$original}.webp"]);
    }

    private function orient(\GdImage $source, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $source;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? null;
        $rotated = match ($orientation) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => false,
        };

        if ($rotated instanceof \GdImage) {
            imagedestroy($source);

            return $rotated;
        }

        return $source;
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
