<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $addons = collect([
            ['Extra Chocolate', 10],
            ['Dry Fruits', 20],
            ['Extra Sweet', 5],
            ['Extra Supari', 5],
        ])->map(fn ($a) => Addon::create(['name' => $a[0], 'price' => $a[1]]))->keyBy('name');

        $catalog = [
            'Sweet Paan' => [
                ['Meetha Paan', 30, true, ['Extra Sweet', 'Dry Fruits']],
                ['Kesar Paan', 50, true, ['Extra Sweet', 'Dry Fruits']],
            ],
            'Sada Paan' => [
                ['Sada Paan', 20, false, ['Extra Supari']],
            ],
            'Chocolate Paan' => [
                ['Chocolate Paan', 60, true, ['Extra Chocolate', 'Dry Fruits']],
            ],
            'Special Paan' => [
                ['Royal Special Paan', 80, true, ['Dry Fruits', 'Extra Sweet', 'Extra Supari']],
                ['Dry Fruit Paan', 90, false, ['Extra Sweet']],
            ],
        ];

        $order = 0;
        foreach ($catalog as $categoryName => $products) {
            $category = Category::create([
                'name' => $categoryName,
                'slug' => Str::slug($categoryName),
                'sort_order' => $order++,
            ]);

            foreach ($products as $i => [$name, $price, $featured, $addonNames]) {
                $product = Product::create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'description' => "{$name} freshly prepared at the shop.",
                    'price' => $price,
                    'is_featured' => $featured,
                    'sort_order' => $i,
                ]);
                $product->addons()->attach(collect($addonNames)->map(fn ($n) => $addons[$n]->id));
            }
        }
    }
}
