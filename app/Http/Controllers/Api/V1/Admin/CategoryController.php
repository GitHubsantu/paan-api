<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\AdminAuditLogger;
use App\Services\PublicImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(private PublicImageService $images, private AdminAuditLogger $audit) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection(Category::query()->withCount('products')->orderBy('sort_order')->orderBy('name')->get())->resolve(request()), 'Categories retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['remove_image']);
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'categories');
        }
        $category = Category::create($data);

        $this->audit->log($request->user(), null, 'category.created', ['category_id' => $category->id]);

        return ApiResponse::success((new CategoryResource($category->loadCount('products')))->resolve($request), 'Category created successfully.', 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();
        $removeImage = $request->boolean('remove_image');
        unset($data['remove_image']);
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'categories');
        } elseif ($removeImage) {
            $data['image'] = null;
        }
        $previousImage = $category->image;
        $category->update($data);
        if (array_key_exists('image', $data)) {
            $this->images->delete($previousImage);
        }
        $this->audit->log($request->user(), null, 'category.updated', ['category_id' => $category->id, 'fields' => array_keys($data)]);

        return ApiResponse::success((new CategoryResource($category->fresh()->loadCount('products')))->resolve($request), 'Category updated successfully.');
    }

    public function destroy(Category $category): Response
    {
        $productsCount = $category->products()->count();
        if ($productsCount > 0) {
            throw new ApiException("This category still has {$productsCount} products. Move or delete them first.", 409, [], 'CATEGORY_HAS_PRODUCTS');
        }
        $category->delete();
        $this->audit->log(request()->user(), null, 'category.deleted', ['category_id' => $category->id]);

        return response()->noContent();
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;
        while (Category::query()->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
