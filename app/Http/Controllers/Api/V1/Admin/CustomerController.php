<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\CustomerIndexRequest;
use App\Http\Resources\AdminCustomerResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CustomerController extends Controller
{
    public function index(CustomerIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $customers = User::query()->where('role', 'customer')->withCount('orders')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->latest()->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($customers, AdminCustomerResource::class, 'Customers retrieved successfully.');
    }
}
