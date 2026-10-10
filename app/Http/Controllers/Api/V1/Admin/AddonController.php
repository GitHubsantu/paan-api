<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAddonRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAddonRequest;
use App\Http\Resources\AddonResource;
use App\Models\Addon;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class AddonController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(AddonResource::collection(Addon::orderBy('name')->get())->resolve(request()), 'Add-ons retrieved successfully.');
    }

    public function store(StoreAddonRequest $request): JsonResponse
    {
        $addon = Addon::create($request->validated());

        return ApiResponse::success((new AddonResource($addon))->resolve($request), 'Add-on created successfully.', 201);
    }

    public function update(UpdateAddonRequest $request, Addon $addon): JsonResponse
    {
        $addon->update($request->validated());

        return ApiResponse::success((new AddonResource($addon->fresh()))->resolve($request), 'Add-on updated successfully.');
    }

    public function destroy(Addon $addon): Response
    {
        $addon->delete();

        return response()->noContent();
    }
}
