<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreProductRequest;
use App\Http\Requests\Api\V1\Admin\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(): JsonResponse
    {
        return ApiResponse::success(ProductResource::collection(Product::with(['category', 'addons'])->latest()->get())->resolve(request()), 'Products retrieved successfully.');
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->safe()->except('addon_ids');
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', config('filesystems.default'));
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
            $data['image'] = $request->file('image')->store('products', config('filesystems.default'));
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
