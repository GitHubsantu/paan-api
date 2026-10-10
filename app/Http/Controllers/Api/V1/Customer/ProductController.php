<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->products->paginate($request->validated()),
            ProductResource::class,
            'Products retrieved successfully.'
        );
    }

    public function show(Product $product): JsonResponse
    {
        $product = $this->products->load($product);

        if (! $product->category?->is_active) {
            throw new ApiException('Resource not found.', 404);
        }

        return ApiResponse::success(
            (new ProductResource($product))->resolve(request()),
            'Product retrieved successfully.'
        );
    }
}
