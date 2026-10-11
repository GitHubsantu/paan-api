<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ProductIndexRequest;
use App\Http\Requests\Api\V1\Admin\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductAvailabilityRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\PublicImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(private PublicImageService $images) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $products = Product::query()
            ->with(['category', 'addons'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($products, ProductResource::class, 'Products retrieved successfully.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->safe()->except('addon_ids');
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'products');
        }
        $product = Product::create($data);
        $product->addons()->sync($request->validated('addon_ids', []));

        return ApiResponse::success((new ProductResource($product->load(['category', 'addons'])))->resolve($request), 'Product created successfully.', 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->safe()->except('addon_ids');
        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $product->id);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'products');
        }
        $product->update($data);
        if ($request->has('addon_ids')) {
            $product->addons()->sync($request->validated('addon_ids'));
        }

        return ApiResponse::success((new ProductResource($product->fresh()->load(['category', 'addons'])))->resolve($request), 'Product updated successfully.');
    }

    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }

    public function updateAvailability(UpdateProductAvailabilityRequest $request, Product $product): JsonResponse
    {
        $product->update(['is_available' => $request->boolean('is_available')]);

        return ApiResponse::success((new ProductResource($product->fresh()->load(['category', 'addons'])))->resolve($request), 'Product availability updated successfully.');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (Product::query()->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
