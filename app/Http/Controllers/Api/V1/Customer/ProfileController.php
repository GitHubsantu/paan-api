<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\CustomerProfileResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success((new CustomerProfileResource($request->user()))->resolve($request), 'Profile retrieved successfully.');
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $request->user()->update($request->validated());

        return ApiResponse::success((new CustomerProfileResource($request->user()->fresh()))->resolve($request), 'Profile updated successfully.');
    }
}
