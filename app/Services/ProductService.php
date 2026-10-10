<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductService
{
    public function paginate(array $f, ?Category $category = null): LengthAwarePaginator
    {
        $q = Product::query()
            ->with([
                'category:id,name,slug',
                'addons' => fn ($a) => $a->where('addons.is_active', true)->orderBy('addons.name'),
            ])
            ->whereHas('category', fn ($c) => $c->where('is_active', true));

        if ($category) {
            $q->where('category_id', $category->id);
        } elseif (! empty($f['category'])) {
            $c = (string) $f['category'];
            ctype_digit($c)
                ? $q->where('category_id', (int) $c)
                : $q->whereHas('category', fn ($x) => $x->where('slug', $c));
        }

        if (! empty($f['search'])) {
            $q->where('name', 'like', '%'.$f['search'].'%');
        }
        if (isset($f['featured'])) {
            $q->where('is_featured', filter_var($f['featured'], FILTER_VALIDATE_BOOLEAN));
        }
        if (isset($f['available'])) {
            $q->where('is_available', filter_var($f['available'], FILTER_VALIDATE_BOOLEAN));
        }

        match ($f['sort'] ?? 'default') {
            'name' => $q->orderBy('name'),
            '-name' => $q->orderByDesc('name'),
            'price' => $q->orderBy('price'),
            '-price' => $q->orderByDesc('price'),
            'newest' => $q->orderByDesc('id'),
            default => $q->orderBy('sort_order')->orderBy('name'),
        };

        return $q->paginate((int) ($f['per_page'] ?? 20))->withQueryString();
    }

    public function load(Product $product): Product
    {
        return $product->load([
            'category:id,name,slug,is_active',
            'addons' => fn ($a) => $a->where('addons.is_active', true)->orderBy('addons.name'),
        ]);
    }
}
