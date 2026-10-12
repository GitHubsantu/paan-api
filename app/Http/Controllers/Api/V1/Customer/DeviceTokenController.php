<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        if (($data['app'] === 'admin') !== $user->isAdmin()) {
            return ApiResponse::error('The selected app does not match the authenticated user role.', 422, ['app' => ['The selected app does not match the authenticated user role.']]);
        }

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            ['user_id' => $user->id, 'platform' => $data['platform'], 'app' => $data['app'], 'last_used_at' => now()],
        );

        return ApiResponse::success(null, 'Device token saved successfully.', 201);
    }

    public function destroy(string $token): JsonResponse
    {
        $deviceToken = DeviceToken::whereBelongsTo(request()->user())->where('token', $token)->first();
        if ($deviceToken) {
            $this->authorize('delete', $deviceToken);
            $deviceToken->delete();
        }

        return ApiResponse::success(null, 'Device token removed successfully.');
    }
}
