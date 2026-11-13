<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    public function test_can_issue_sanctum_api_token_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'API Tester',
            'email' => 'api_token_' . Str::random(6) . '@test.local',
            'password' => Hash::make('secretpass123'),
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'secretpass123',
            'token_name' => 'test-device',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => ['id', 'name', 'email'],
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        // Test authenticated endpoint with the issued token
        $ws = Workspace::create([
            'name' => 'API Token Workspace',
            'slug' => 'ws-' . Str::lower(Str::random(6)),
            'owner_id' => $user->id,
        ]);
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $authResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/workspaces');

        $authResponse->assertStatus(200)
            ->assertJsonFragment(['name' => 'API Token Workspace']);
    }

    public function test_rejects_token_issue_with_invalid_credentials(): void
    {
        $user = User::create([
            'name' => 'API Tester 2',
            'email' => 'api_fail_' . Str::random(6) . '@test.local',
            'password' => Hash::make('correctpass'),
        ]);

        $response = $this->postJson('/api/v1/tokens', [
            'email' => $user->email,
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(422);
    }
}
