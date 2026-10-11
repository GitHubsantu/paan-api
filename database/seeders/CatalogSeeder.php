<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['Sweet Paan (Meetha)', [['Shahi Meetha Paan', 40, 'Chandi Vark', 3, true, true, 'Gulkand, glazed cherries, tutty-fruitty, green cardamom, silver leaf.', ['Magahi Betel Leaf', 'Gulkand', 'Glazed Cherries', 'Silver Leaf', 'Green Cardamom']]]],
            ['Chocolate Paan', [['Belgian Dark Chocolate Paan', 80, 'Gourmet', 4, true, true, 'Rich dark chocolate ganache, roasted almonds, gold dust.', ['Magahi Betel Leaf', 'Dark Chocolate Ganache', 'Roasted Almonds', 'Edible Gold Dust']]]],
            ['Sada & Saunf', [['Sada Saunf Paan', 25, null, 2, true, false, 'Classic fresh betel leaf with fennel and light sweet filling.', []]]],
            ['Special Royal Gilouri', [['Royal Silver Vark Thali Selection', 250, "Chef's Masterpiece", 5, true, true, 'Hand-folded with fresh Magahi leaves & Shahi Gulkand for celebratory dining.', ['Magahi Betel Leaf', 'Shahi Gulkand', 'Silver Vark', 'Green Cardamom']], ['Banarasi Special Gilouri', 60, 'Heritage Classic', 4, true, false, 'Authentic Banarasi betel leaf, fragrant saunf, cloves & dates.', ['Banarasi Betel Leaf', 'Lucknowi Saunf', 'Cloves', 'Dates', 'Silver Vark']]]],
            ['Ice / Fire Paan', [['Fire Paan', 90, 'Showstopper', 3, false, false, 'Flambe-style showpiece paan, made fresh at the counter.', []]]],
            ['Mouth Freshener', []],
        ];

        foreach ($catalog as $sortOrder => [$categoryName, $products]) {
            $slug = Str::slug($categoryName);
            $category = Category::withTrashed()->firstOrNew(['slug' => $slug]);
            $category->fill(['name' => $categoryName, 'description' => null, 'sort_order' => $sortOrder + 1, 'is_active' => true]);
            $category->deleted_at = null;
            $category->save();

            foreach ($products as $productData) {
                [$name, $price, $tag, $prepMinutes, $available, $featured, $description, $ingredients] = $productData;
                $product = Product::withTrashed()->firstOrNew(['slug' => Str::slug($name)]);
                $product->fill(['category_id' => $category->id, 'name' => $name, 'description' => $description, 'price' => $price, 'tag' => $tag, 'prep_minutes' => $prepMinutes, 'ingredients' => $ingredients, 'is_available' => $available, 'is_featured' => $featured, 'sort_order' => 0]);
                $product->deleted_at = null;
                $product->save();
            }
        }
    }
}
