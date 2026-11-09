<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_landing_page_renders_featured_products_and_category_names(): void
    {
        Cache::forget('landing.v1');
        $category = Category::factory()->create(['name' => 'Classic Paan']);
        Product::factory()->for($category)->create([
            'name' => 'Royal Paan',
            'is_featured' => true,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('Classic Paan')
            ->assertSeeText('Royal Paan');
    }
}
