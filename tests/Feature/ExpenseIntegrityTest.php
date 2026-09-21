<?php

namespace Tests\Feature;

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithBudget(float $income = 2000): array
    {
        $user = User::factory()->create();
        $budget = Budget::create([
            'user_id' => $user->id,
            'name' => 'September 2026 Budget',
            'type' => 'salary',
            'total_amount' => $income,
            'currency_code' => 'USD',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'active',
        ]);
        $food = Category::create(['user_id' => $user->id, 'name' => 'Food', 'type' => 'expense']);
        $groceries = Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense', 'parent_id' => $food->id]);

        return [$user, $budget, $food, $groceries];
    }

    public function test_direct_expense_deducts_budget_atomically(): void
    {
        [$user, $budget, $food, $groceries] = $this->makeUserWithBudget();
        BudgetAllocation::create(['budget_id' => $budget->id, 'category_id' => $groceries->id, 'allocated_amount' => 500, 'spent_amount' => 0]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id,
            'subcategory_id' => $groceries->id,
            'budget_id' => $budget->id,
            'amount' => 25,
            'date' => '2026-09-16',
            'description' => 'Groceries',
            'payment_method' => 'cash',
            'source' => 'manual',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('expenses', ['user_id' => $user->id, 'amount' => 25, 'source' => 'manual']);

        // Allocated plan untouched, spent derived.
        $this->assertDatabaseHas('budget_allocations', [
            'budget_id' => $budget->id,
            'category_id' => $groceries->id,
            'allocated_amount' => 500,
            'spent_amount' => 25,
        ]);

        $summary = $this->actingAs($user, 'sanctum')->getJson("/api/dashboard/summary?budget_id={$budget->id}");
        $summary->assertOk()
            ->assertJsonPath('summary.total_allocated', 500)
            ->assertJsonPath('summary.total_spent', 25)
            ->assertJsonPath('summary.remaining', 475)
            ->assertJsonPath('summary.is_overspent', false);
    }

    public function test_rejects_non_positive_amounts(): void
    {
        [$user, $budget, $food] = $this->makeUserWithBudget();

        foreach ([0, -5] as $bad) {
            $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
                'category_id' => $food->id,
                'budget_id' => $budget->id,
                'amount' => $bad,
                'date' => '2026-09-16',
            ])->assertStatus(422);
        }

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_rejects_foreign_category_and_mismatched_subcategory(): void
    {
        [$user, $budget, $food, $groceries] = $this->makeUserWithBudget();
        $stranger = User::factory()->create();
        $foreign = Category::create(['user_id' => $stranger->id, 'name' => 'Theirs', 'type' => 'expense']);
        $transport = Category::create(['user_id' => $user->id, 'name' => 'Transport', 'type' => 'expense']);

        // Foreign category
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $foreign->id,
            'budget_id' => $budget->id,
            'amount' => 10,
            'date' => '2026-09-16',
        ])->assertStatus(422);

        // Subcategory from a different parent
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $transport->id,
            'subcategory_id' => $groceries->id,
            'budget_id' => $budget->id,
            'amount' => 10,
            'date' => '2026-09-16',
        ])->assertStatus(422);

        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_failed_expense_rolls_back_allocation_totals(): void
    {
        [$user, $budget, $food, $groceries] = $this->makeUserWithBudget();
        BudgetAllocation::create(['budget_id' => $budget->id, 'category_id' => $groceries->id, 'allocated_amount' => 150, 'spent_amount' => 0]);

        // Valid first expense
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id,
            'subcategory_id' => $groceries->id,
            'budget_id' => $budget->id,
            'amount' => 50,
            'date' => '2026-09-16',
        ])->assertCreated();

        // Failing second expense (bad subcategory) must not move totals.
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id,
            'subcategory_id' => 999999,
            'budget_id' => $budget->id,
            'amount' => 50,
            'date' => '2026-09-16',
        ])->assertStatus(422);

        $this->assertDatabaseHas('budget_allocations', [
            'budget_id' => $budget->id,
            'category_id' => $groceries->id,
            'spent_amount' => 50,
        ]);
        $this->assertEquals(1, Expense::count());
    }

    public function test_overspending_math_flags_negative_remaining(): void
    {
        [$user, $budget, $food, $groceries] = $this->makeUserWithBudget();
        BudgetAllocation::create(['budget_id' => $budget->id, 'category_id' => $groceries->id, 'allocated_amount' => 150, 'spent_amount' => 0]);

        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id,
            'subcategory_id' => $groceries->id,
            'budget_id' => $budget->id,
            'amount' => 180,
            'date' => '2026-09-16',
            'description' => 'Big shop',
        ])->assertCreated();

        $summary = $this->actingAs($user, 'sanctum')->getJson("/api/dashboard/summary?budget_id={$budget->id}");
        $summary->assertOk()
            ->assertJsonPath('summary.total_spent', 180)
            ->assertJsonPath('summary.remaining', -30)
            ->assertJsonPath('summary.is_overspent', true);
    }

    public function test_sequential_expenses_keep_consistent_totals(): void
    {
        [$user, $budget, $food] = $this->makeUserWithBudget();
        BudgetAllocation::create(['budget_id' => $budget->id, 'category_id' => $food->id, 'allocated_amount' => 500, 'spent_amount' => 0]);

        // Two rapid sequential submissions (double-click): both persist, totals stay exact.
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id, 'budget_id' => $budget->id, 'amount' => 25, 'date' => '2026-09-16',
        ])->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id, 'budget_id' => $budget->id, 'amount' => 25, 'date' => '2026-09-16',
        ])->assertCreated();

        $summary = $this->actingAs($user, 'sanctum')->getJson("/api/dashboard/summary?budget_id={$budget->id}");
        $summary->assertOk()->assertJsonPath('summary.total_spent', 50);
    }

    public function test_voice_source_expense_is_attributed(): void
    {
        [$user, $budget, $food, $groceries] = $this->makeUserWithBudget();

        $this->actingAs($user, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $food->id,
            'subcategory_id' => $groceries->id,
            'budget_id' => $budget->id,
            'amount' => 15,
            'date' => '2026-09-15',
            'description' => 'Coffee',
            'source' => 'voice',
        ])->assertCreated();

        $this->assertDatabaseHas('expenses', ['source' => 'voice', 'description' => 'Coffee']);
    }
}
