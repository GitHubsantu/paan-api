<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Services\ShopSettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ShopController extends Controller
{
    public function __construct(private ShopSettingsService $shop) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->shop->publicPayload(), 'Shop retrieved successfully.');
    }
}
