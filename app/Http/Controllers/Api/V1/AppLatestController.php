<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\AppReleaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppLatestController extends Controller
{
    public function __invoke(Request $request, AppReleaseService $releases): JsonResponse
    {
        $release = $releases->current();
        if (! $release) {
            throw new ApiException('No current release found.', 404);
        }

        return ApiResponse::success(['version_name' => $release->version_name, 'version_code' => $release->version_code, 'release_notes' => $release->release_notes, 'download_url' => route('download'), 'published_at' => $release->published_at?->toISOString(), 'sha256' => $release->sha256, 'size_bytes' => $release->apk_size]);
    }
}
