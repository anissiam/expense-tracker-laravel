<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Budget;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_budget()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/budgets', [
            'month' => 10,
            'year' => 2026,
            'mode' => 'salary',
            'income' => 5000,
            'currency' => 'USD'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('type', 'salary')
                 ->assertJsonPath('start_date', '2026-10-01');
                 
        $this->assertDatabaseHas('budgets', [
            'user_id' => $user->id,
            'start_date' => '2026-10-01',
        ]);
    }

    public function test_user_cannot_create_duplicate_budget_for_same_month()
    {
        $user = User::factory()->create();
        
        Budget::create([
            'user_id' => $user->id,
            'name' => 'October 2026 Budget',
            'type' => 'salary',
            'total_amount' => 5000,
            'currency_code' => 'USD',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/budgets', [
            'month' => 10,
            'year' => 2026,
            'mode' => 'planned',
            'income' => 4000,
            'currency' => 'USD'
        ]);

        $response->assertStatus(400); // Because we throw an exception in the service
    }
}
