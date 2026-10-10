<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateSettingsRequest;
use App\Services\ShopSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SettingController extends Controller
{
    public function __construct(private ShopSettingsService $settings) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success($this->settings->all(), 'Settings retrieved successfully.');
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        return ApiResponse::success($this->settings->update($request->validated()), 'Settings updated successfully.');
    }
}
