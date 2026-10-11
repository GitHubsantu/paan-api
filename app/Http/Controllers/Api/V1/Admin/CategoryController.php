<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\PublicImageService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(private PublicImageService $images) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(CategoryResource::collection(Category::orderBy('sort_order')->orderBy('name')->get())->resolve(request()), 'Categories retrieved successfully.');
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $this->uniqueSlug($data['name']);
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'categories');
        }
        $category = Category::create($data);

        return ApiResponse::success((new CategoryResource($category))->resolve($request), 'Category created successfully.', 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['name']) && $data['name'] !== $category->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $category->id);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $this->images->store($request->file('image'), 'categories');
        }
        $category->update($data);

        return ApiResponse::success((new CategoryResource($category->fresh()))->resolve($request), 'Category updated successfully.');
    }

    public function destroy(Category $category): Response
    {
        if ($category->products()->exists()) {
            throw new ApiException('A category with products cannot be deleted.', 409);
        }
        $category->delete();

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
