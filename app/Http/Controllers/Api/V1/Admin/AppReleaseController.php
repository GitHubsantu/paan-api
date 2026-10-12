<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAppReleaseRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAppReleaseRequest;
use App\Http\Requests\Api\V1\Admin\UpdateDownloadSettingsRequest;
use App\Http\Resources\AppReleaseResource;
use App\Models\AppRelease;
use App\Models\Setting;
use App\Services\AdminAuditLogger;
use App\Services\AppReleaseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AppReleaseController extends Controller
{
    public function __construct(private AppReleaseService $releases, private AdminAuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return ApiResponse::paginated(AppRelease::query()->with('uploadedBy:id,name')->latest()->paginate(20), AppReleaseResource::class, 'App releases retrieved successfully.');
    }

    public function store(StoreAppReleaseRequest $request): JsonResponse
    {
        $release = $this->releases->create($request->safe()->except('apk'), $request->file('apk'), $request->user());
        $this->audit->log($request->user(), null, 'app_release.created', ['release_id' => $release->id]);

        return ApiResponse::success((new AppReleaseResource($release->load('uploadedBy:id,name')))->resolve($request), 'App release created successfully.', 201);
    }

    public function update(UpdateAppReleaseRequest $request, AppRelease $appRelease): JsonResponse
    {
        $release = $this->releases->update($appRelease, $request->validated());

        return ApiResponse::success((new AppReleaseResource($release->load('uploadedBy:id,name')))->resolve($request), 'App release updated successfully.');
    }

    public function makeCurrent(AppRelease $appRelease): JsonResponse
    {
        $release = $this->releases->makeCurrent($appRelease);
        $this->audit->log(request()->user(), null, 'app_release.current', ['release_id' => $release->id]);

        return ApiResponse::success((new AppReleaseResource($release->load('uploadedBy:id,name')))->resolve(request()), 'App release is now current.');
    }

    public function destroy(AppRelease $appRelease): Response
    {
        $id = $appRelease->id;
        $this->releases->delete($appRelease);
        $this->audit->log(request()->user(), null, 'app_release.deleted', ['release_id' => $id]);

        return response()->noContent();
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::success(['download_mode' => Setting::where('key', 'download_mode')->value('value') ?: 'coming_soon', 'play_store_url' => Setting::where('key', 'play_store_url')->value('value')]);
    }

    public function updateSettings(UpdateDownloadSettingsRequest $request): JsonResponse
    {
        foreach ($request->validated() as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        } $this->audit->log($request->user(), null, 'app_release.mode_changed', ['admin_id' => $request->user()->id]);

        return $this->settings();
    }
}
