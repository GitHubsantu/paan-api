<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private AdminDashboardService $dashboard) {}

    public function show(): JsonResponse
    {
        return ApiResponse::success($this->dashboard->summary(), 'Dashboard retrieved successfully.');
    }
}
