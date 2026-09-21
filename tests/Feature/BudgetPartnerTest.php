<?php

namespace Tests\Feature;

use App\Mail\PartnerInviteMail;
use App\Models\Budget;
use App\Models\BudgetMember;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BudgetPartnerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeBudget(User $owner): Budget
    {
        return Budget::create([
            'user_id' => $owner->id,
            'name' => 'September 2026 Budget',
            'type' => 'salary',
            'total_amount' => 5000,
            'currency_code' => 'USD',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'active',
        ]);
    }

    public function test_owner_can_invite_existing_user_and_email_is_sent()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $res = $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email,
            'role' => 'editor',
        ]);

        $res->assertStatus(201)->assertJsonPath('status', 'pending');
        $this->assertDatabaseHas('budget_members', [
            'budget_id' => $budget->id,
            'email' => strtolower($partner->email),
            'user_id' => $partner->id,
        ]);
        Mail::assertQueued(PartnerInviteMail::class);
    }

    public function test_invite_new_email_creates_pending_without_user_id()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $res = $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => 'newperson@example.com',
            'role' => 'viewer',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('budget_members', [
            'budget_id' => $budget->id,
            'email' => 'newperson@example.com',
            'user_id' => null,
            'role' => 'viewer',
            'status' => 'pending',
        ]);
    }

    public function test_owner_cannot_invite_self()
    {
        $owner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $owner->email,
            'role' => 'editor',
        ])->assertStatus(422);
    }

    public function test_duplicate_invite_is_rejected()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'viewer',
        ])->assertStatus(201);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'editor',
        ])->assertStatus(422);
    }

    public function test_non_owner_cannot_invite()
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($other, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => 'x@example.com', 'role' => 'viewer',
        ])->assertStatus(403);
    }

    public function test_partner_can_accept_with_matching_email()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'editor',
        ])->assertStatus(201);

        $token = BudgetMember::first()->token;

        $this->actingAs($partner, 'sanctum')->postJson("/api/invites/{$token}/accept")
            ->assertStatus(200);

        $this->assertDatabaseHas('budget_members', [
            'budget_id' => $budget->id,
            'user_id' => $partner->id,
            'status' => 'accepted',
        ]);

        // Accepted partner can now view the budget.
        $this->actingAs($partner, 'sanctum')->getJson("/api/budgets/{$budget->id}")
            ->assertStatus(200);
    }

    public function test_accept_with_wrong_email_is_forbidden()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $intruder = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'viewer',
        ]);

        $token = BudgetMember::first()->token;

        $this->actingAs($intruder, 'sanctum')->postJson("/api/invites/{$token}/accept")
            ->assertStatus(403);
    }

    public function test_expired_invite_cannot_be_accepted()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'viewer',
        ]);

        $member = BudgetMember::first();
        $member->update(['expires_at' => now()->subDay()]);

        $this->actingAs($partner, 'sanctum')->postJson("/api/invites/{$member->token}/accept")
            ->assertStatus(410);
    }

    public function test_viewer_read_only_and_editor_can_write()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $viewer = User::factory()->create(['email' => 'viewer@example.com']);
        $editor = User::factory()->create(['email' => 'editor@example.com']);
        $budget = $this->makeBudget($owner);

        $category = Category::create([
            'user_id' => $owner->id, 'name' => 'Food', 'type' => 'expense',
        ]);

        foreach (['viewer@example.com' => 'viewer', 'editor@example.com' => 'editor'] as $email => $role) {
            $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
                'email' => $email, 'role' => $role,
            ])->assertStatus(201);
        }
        BudgetMember::where('email', 'viewer@example.com')->update(['status' => 'accepted', 'user_id' => $viewer->id, 'accepted_at' => now()]);
        BudgetMember::where('email', 'editor@example.com')->update(['status' => 'accepted', 'user_id' => $editor->id, 'accepted_at' => now()]);

        // Viewer: read OK.
        $this->actingAs($viewer, 'sanctum')->getJson("/api/budgets/{$budget->id}")->assertStatus(200);
        // Viewer: write blocked.
        $this->actingAs($viewer, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'amount' => 10,
            'date' => '2026-09-10',
        ])->assertStatus(403);

        // Editor: write OK (owner's category is usable on shared budget).
        $this->actingAs($editor, 'sanctum')->postJson('/api/expenses', [
            'category_id' => $category->id,
            'budget_id' => $budget->id,
            'amount' => 10,
            'date' => '2026-09-10',
        ])->assertStatus(201);

        // Editor cannot delete the budget; owner can manage.
        $this->actingAs($editor, 'sanctum')->deleteJson("/api/budgets/{$budget->id}")->assertStatus(403);
    }

    public function test_owner_can_change_role_and_remove_partner()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $partner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => $partner->email, 'role' => 'viewer',
        ])->assertStatus(201);

        $member = BudgetMember::first();

        $this->actingAs($owner, 'sanctum')->patchJson("/api/budgets/{$budget->id}/partners/{$member->id}", [
            'role' => 'editor',
        ])->assertStatus(200)->assertJsonPath('role', 'editor');

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/budgets/{$budget->id}/partners/{$member->id}")
            ->assertStatus(204);
        $this->assertDatabaseMissing('budget_members', ['id' => $member->id]);
    }

    public function test_register_with_invite_token_auto_accepts()
    {
        Mail::fake();
        $owner = User::factory()->create();
        $budget = $this->makeBudget($owner);

        $this->actingAs($owner, 'sanctum')->postJson("/api/budgets/{$budget->id}/partners", [
            'email' => 'fresh@example.com', 'role' => 'editor',
        ])->assertStatus(201);

        $token = BudgetMember::first()->token;

        $res = $this->postJson('/api/register', [
            'name' => 'Fresh',
            'email' => 'fresh@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'invite_token' => $token,
        ]);

        $res->assertStatus(201)->assertJsonPath('accepted_invites', 1);
        $this->assertDatabaseHas('budget_members', [
            'budget_id' => $budget->id,
            'email' => 'fresh@example.com',
            'status' => 'accepted',
        ]);
    }
}
