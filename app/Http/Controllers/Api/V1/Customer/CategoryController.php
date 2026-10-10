<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(): JsonResponse
    {
        $categories = Category::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return ApiResponse::success(
            CategoryResource::collection($categories)->resolve(request()),
            'Categories retrieved successfully.'
        );
    }

    public function products(ProductIndexRequest $request, Category $category): JsonResponse
    {
        if (! $category->is_active) {
            throw new ApiException('Resource not found.', 404);
        }

        return ApiResponse::paginated(
            $this->products->paginate($request->validated(), $category),
            ProductResource::class,
            'Products retrieved successfully.'
        );
    }
}
