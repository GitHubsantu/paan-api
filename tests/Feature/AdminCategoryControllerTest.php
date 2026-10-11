<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCategoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_create_update_and_soft_delete_a_category(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);

        $created = $this->postJson('/api/v1/admin/categories', ['name' => 'Premium Paans', 'description' => 'Special picks.', 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'premium-paans');
        $categoryId = $created->json('data.id');

        $this->putJson("/api/v1/admin/categories/{$categoryId}", ['name' => 'Premium Paan'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'premium-paan');

        $this->deleteJson("/api/v1/admin/categories/{$categoryId}")->assertNoContent();

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
    }

    public function test_returns_409_when_deleting_a_category_that_has_products(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['admin']);
        $category = Category::factory()->create();
        Product::factory()->for($category)->create();

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")
            ->assertConflict()
            ->assertJsonPath('message', 'This category still has 1 products. Move or delete them first.')
            ->assertJsonPath('code', 'CATEGORY_HAS_PRODUCTS');
    }
}
