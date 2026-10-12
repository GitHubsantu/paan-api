<?php

namespace App\Http\Controllers;

use App\Enums\DownloadMode;
use App\Models\Setting;
use App\Services\AppReleaseService;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadController extends Controller
{
    public function __invoke(AppReleaseService $releases): BinaryFileResponse|RedirectResponse
    {
        $mode = DownloadMode::tryFrom(Setting::where('key', 'download_mode')->value('value') ?: 'coming_soon') ?? DownloadMode::ComingSoon;
        if ($mode === DownloadMode::Apk) {
            $release = $releases->current();

            return $release ? $releases->stream($release) : redirect('/#download');
        }
        if ($mode === DownloadMode::PlayStore && filter_var($url = Setting::where('key', 'play_store_url')->value('value'), FILTER_VALIDATE_URL) && str_starts_with($url, 'https://')) {
            return redirect()->away($url);
        }

        return redirect('/#download');
    }
}
