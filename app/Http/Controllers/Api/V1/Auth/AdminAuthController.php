<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminAuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function login(LoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->attemptAdmin(
            $request->validated('phone'),
            $request->validated('password'),
        );

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Admin logged in successfully.');
    }
}
