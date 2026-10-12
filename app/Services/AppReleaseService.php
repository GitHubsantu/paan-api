<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppReleaseService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, UploadedFile $apk, User $admin): AppRelease
    {
        $this->validateApk($apk);
        $filename = Str::ulid().'.apk';
        Storage::disk('releases')->putFileAs('', $apk, $filename);
        $path = Storage::disk('releases')->path($filename);

        return AppRelease::create([...$data, 'platform' => 'android', 'apk_path' => $filename, 'apk_original_name' => $apk->getClientOriginalName(), 'apk_size' => filesize($path), 'sha256' => hash_file('sha256', $path), 'uploaded_by' => $admin->id, 'published_at' => ! empty($data['is_published']) ? now() : null]);
    }

    /** @param array<string, mixed> $data */
    public function update(AppRelease $release, array $data): AppRelease
    {
        if (array_key_exists('is_published', $data)) {
            $data['published_at'] = $data['is_published'] ? ($release->published_at ?? now()) : null;
        }
        $release->update($data);

        return $release->fresh();
    }

    public function makeCurrent(AppRelease $release): AppRelease
    {
        if (! $release->is_published || ! $release->apk_path || ! Storage::disk('releases')->exists($release->apk_path)) {
            throw new ApiException('Only published releases with an APK can be current.', 422);
        }
        DB::transaction(function () use ($release): void {
            AppRelease::where('platform', $release->platform)->update(['is_current' => false]);
            $release->update(['is_current' => true]);
        });

        return $release->fresh();
    }

    public function delete(AppRelease $release): void
    {
        if ($release->is_current) {
            throw new ApiException('The current release cannot be deleted.', 409);
        }
        if ($release->apk_path) {
            Storage::disk('releases')->delete($release->apk_path);
        }
        $release->delete();
    }

    public function current(): ?AppRelease
    {
        return AppRelease::query()->published()->current()->where('platform', 'android')->first();
    }

    public function stream(AppRelease $release): BinaryFileResponse
    {
        if (! $release->is_published || ! $release->apk_path || ! Storage::disk('releases')->exists($release->apk_path)) {
            throw new ApiException('Release not found.', 404);
        }
        $version = preg_replace('/[^A-Za-z0-9._-]/', '', $release->version_name) ?: 'release';
        $release->increment('download_count');

        return response()->download(Storage::disk('releases')->path($release->apk_path), "paan-app-{$version}.apk", ['Content-Type' => 'application/vnd.android.package-archive', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'no-store']);
    }

    public function validateApk(UploadedFile $apk): void
    {
        if (! $apk->isValid() || strtolower($apk->getClientOriginalExtension()) !== 'apk' || $apk->getSize() > config('landing.max_apk_mb') * 1024 * 1024) {
            throw new ApiException('The upload must be a valid APK within the configured size limit.', 422);
        }
        $magic = file_get_contents($apk->getRealPath(), false, null, 0, 2);
        $zip = new \ZipArchive;
        $opened = $zip->open($apk->getRealPath()) === true;
        $valid = $magic === 'PK' && $opened && $zip->locateName('AndroidManifest.xml') !== false;
        if ($opened) {
            $zip->close();
        }
        if (! $valid) {
            throw new ApiException('The uploaded file is not a valid Android APK.', 422);
        }
    }
}
