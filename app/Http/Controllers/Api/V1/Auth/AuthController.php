<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\OtpLoginRequest;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Requests\Api\V1\Auth\SendOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $data = $this->auth->sendOtp($request->validated('phone'), $request->validated('purpose'));

        return ApiResponse::success($data, 'If the number is eligible, an OTP has been sent.');
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->register(
            $request->validated('name'),
            $request->validated('phone'),
            $request->validated('otp'),
        );

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Registered successfully.', 201);
    }

    public function login(OtpLoginRequest $request): JsonResponse
    {
        [$user, $token] = $this->auth->loginWithOtp(
            $request->validated('phone'),
            $request->validated('otp'),
        );

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ], 'Logged in successfully.');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout($request->user());

        return ApiResponse::success(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()), 'Profile retrieved successfully.');
    }
}
