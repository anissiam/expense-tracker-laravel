<?php

namespace Tests\Feature;

use App\Services\VoiceParserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceParserTest extends TestCase
{
    use RefreshDatabase;
    private function categories(): array
    {
        return [
            ['id' => 1, 'name' => 'Food', 'parent_id' => null],
            ['id' => 2, 'name' => 'Groceries', 'parent_id' => 1],
            ['id' => 3, 'name' => 'Meat', 'parent_id' => 1],
            ['id' => 4, 'name' => 'Coffee', 'parent_id' => 1],
            ['id' => 5, 'name' => 'Transport', 'parent_id' => null],
            ['id' => 6, 'name' => 'Fuel', 'parent_id' => 5],
        ];
    }

    private function parser(): VoiceParserService
    {
        return new VoiceParserService;
    }

    public function test_basic_expense_with_subcategory(): void
    {
        $r = $this->parser()->parse('Bought groceries for 25 dollars.', $this->categories(), '2026-09-16');

        $this->assertSame('READY_FOR_REVIEW', $r['status']);
        $this->assertEquals(25, $r['amount']);
        $this->assertSame('Food', $r['category_name']);
        $this->assertSame('Groceries', $r['subcategory_name']);
    }

    public function test_expense_with_meat_subcategory(): void
    {
        $r = $this->parser()->parse('I bought meat for 40 dollars.', $this->categories(), '2026-09-16');

        $this->assertSame('READY_FOR_REVIEW', $r['status']);
        $this->assertEquals(40, $r['amount']);
        $this->assertSame('Food', $r['category_name']);
        $this->assertSame('Meat', $r['subcategory_name']);
    }

    public function test_expense_with_relative_date(): void
    {
        $r = $this->parser()->parse('Yesterday I spent 15 dollars on coffee.', $this->categories(), '2026-09-16');

        $this->assertSame('READY_FOR_REVIEW', $r['status']);
        $this->assertEquals(15, $r['amount']);
        $this->assertSame('Food', $r['category_name']);
        $this->assertSame('2026-09-15', $r['date']);
        $this->assertStringContainsStringIgnoringCase('coffee', (string) $r['description']);
    }

    public function test_missing_amount_asks_for_clarification(): void
    {
        $r = $this->parser()->parse('I bought groceries today.', $this->categories(), '2026-09-16');

        $this->assertSame('NEEDS_CLARIFICATION', $r['status']);
        $this->assertSame('amount', $r['missingField']);
        $this->assertSame('How much did you spend?', $r['prompt']);
    }

    public function test_missing_category_asks_for_clarification(): void
    {
        $r = $this->parser()->parse('I spent 20 dollars today.', $this->categories(), '2026-09-16');

        $this->assertSame('NEEDS_CLARIFICATION', $r['status']);
        $this->assertSame('category', $r['missingField']);
        $this->assertSame('What category should I use?', $r['prompt']);
        $this->assertEquals(20, $r['amount']);
    }

    public function test_parser_is_readonly_and_supports_dollar_sign(): void
    {
        $r = $this->parser()->parse('Paid $25.50 for fuel', $this->categories(), '2026-09-16');

        $this->assertSame('READY_FOR_REVIEW', $r['status']);
        $this->assertEquals(25.50, $r['amount']);
        $this->assertSame('Transport', $r['category_name']);
        $this->assertSame('Fuel', $r['subcategory_name']);
        // Parser returns plain arrays — no Eloquent side effects possible here.
        $this->assertIsArray($r);
    }

    public function test_voice_parse_endpoint_is_readonly(): void
    {
        $user = \App\Models\User::factory()->create();
        $food = \App\Models\Category::create(['user_id' => $user->id, 'name' => 'Food', 'type' => 'expense']);
        \App\Models\Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense', 'parent_id' => $food->id]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/voice/parse', [
            'transcript' => 'Bought groceries for 25 dollars',
        ]);

        $response->assertOk()->assertJsonPath('status', 'READY_FOR_REVIEW');
        $this->assertDatabaseCount('expenses', 0);
    }
}
