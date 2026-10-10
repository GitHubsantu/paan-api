<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        DeviceToken::updateOrCreate(
            ['user_id' => $request->user()->id, 'token' => $data['token']],
            ['platform' => $data['platform'], 'last_used_at' => now()],
        );

        return ApiResponse::success(null, 'Device token saved successfully.', 201);
    }

    public function destroy(string $token): Response
    {
        $deviceToken = DeviceToken::whereBelongsTo(request()->user())->where('token', $token)->firstOrFail();
        $this->authorize('delete', $deviceToken);
        $deviceToken->delete();

        return response()->noContent();
    }
}
