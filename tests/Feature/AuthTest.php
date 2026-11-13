<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_register_and_automatically_gets_workspace_and_owner_role(): void
    {
        $response = $this->post('/register', [
            'name' => 'Alice Developer',
            'email' => 'alice@test.local',
            'workspace_name' => 'Acme Corporation',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect('/overview');
        $this->assertAuthenticated();

        $user = User::where('email', 'alice@test.local')->first();
        $this->assertNotNull($user);

        $workspace = Workspace::where('name', 'Acme Corporation')->first();
        $this->assertNotNull($workspace);
        $this->assertEquals($user->id, $workspace->owner_id);
        $this->assertEquals('owner', $user->roleInWorkspace($workspace));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Bob Analyst',
            'email' => 'bob@test.local',
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Bob Analytics',
            'slug' => 'bob-analytics',
            'owner_id' => $user->id,
        ]);

        $user->workspaces()->attach($workspace->id, [
            'role' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->post('/login', [
            'email' => 'bob@test.local',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/overview');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::create([
            'name' => 'Charlie',
            'email' => 'charlie@test.local',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'charlie@test.local',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'David',
            'email' => 'david@test.local',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}

