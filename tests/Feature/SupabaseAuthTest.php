<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupabaseAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function fakeSupabaseUser(array $overrides = []): void
    {
        Http::fake([
            '*/auth/v1/user' => Http::response(array_merge([
                'id' => 'a35e639c-bfc2-4231-b20c-d14ad6a701fd',
                'email' => 'supa@example.com',
                'user_metadata' => ['name' => 'Supa User', 'currency' => 'USD'],
            ], $overrides)),
        ]);
    }

    public function test_sync_creates_app_user_from_supabase_session()
    {
        $this->fakeSupabaseUser();

        $res = $this->postJson('/api/auth/supabase/sync', [
            'access_token' => 'valid-supabase-jwt',
            'name' => 'Supa User',
            'currency' => 'USD',
        ]);

        $res->assertStatus(201)->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', [
            'email' => 'supa@example.com',
            'supabase_id' => 'a35e639c-bfc2-4231-b20c-d14ad6a701fd',
        ]);
    }

    public function test_sync_links_existing_email_account()
    {
        $existing = User::factory()->create(['email' => 'supa@example.com']);
        $this->fakeSupabaseUser();

        $res = $this->postJson('/api/auth/supabase/sync', [
            'access_token' => 'valid-supabase-jwt',
        ]);

        $res->assertStatus(200);
        $this->assertEquals($existing->id, $res->json('user.id'));
        $this->assertDatabaseHas('users', [
            'id' => $existing->id,
            'supabase_id' => 'a35e639c-bfc2-4231-b20c-d14ad6a701fd',
        ]);
    }

    public function test_sync_rejects_invalid_supabase_token()
    {
        Http::fake(['*/auth/v1/user' => Http::response(['msg' => 'bad'], 401)]);

        $res = $this->postJson('/api/auth/supabase/sync', [
            'access_token' => 'bogus',
        ]);

        $res->assertStatus(401);
    }
}
