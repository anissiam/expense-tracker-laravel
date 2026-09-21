<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Category;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_category()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/categories', [
            'name' => 'Groceries',
            'type' => 'expense',
            'icon' => 'cart',
            'color' => '#ffffff'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('name', 'Groceries');
                 
        $this->assertDatabaseHas('categories', [
            'user_id' => $user->id,
            'name' => 'Groceries'
        ]);
    }

    public function test_user_can_fetch_categories()
    {
        $user = User::factory()->create();
        
        Category::create([
            'user_id' => $user->id,
            'name' => 'Transport',
            'type' => 'expense'
        ]);
        
        Category::create([
            'user_id' => null, // Default system category
            'name' => 'Housing',
            'type' => 'expense',
            'is_default' => true
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/categories');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json());
    }
}
