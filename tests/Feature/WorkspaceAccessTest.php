<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    protected function createWorkspaceWithUser(string $email, string $role = 'owner'): array
    {
        $user = User::create([
            'name' => 'User ' . Str::random(5),
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);

        $workspace = Workspace::create([
            'name' => 'Workspace ' . Str::random(5),
            'slug' => 'ws-' . Str::lower(Str::random(8)),
            'owner_id' => $user->id,
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }

    public function test_user_cannot_access_another_workspace_datasets(): void
    {
        [$userA, $workspaceA] = $this->createWorkspaceWithUser('userA@test.local');
        [$userB, $workspaceB] = $this->createWorkspaceWithUser('userB@test.local');

        $datasetB = Dataset::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceB->id,
            'created_by' => $userB->id,
            'name' => 'Secret Dataset B',
            'original_filename' => 'secret_b.csv',
            'storage_path' => 'datasets/secret_b.csv',
            'file_size_bytes' => 1024,
            'file_checksum_sha256' => hash('sha256', 'content'),
            'status' => 'ready',
        ]);

        // User A tries to view Dataset B directly
        $response = $this->actingAs($userA)
            ->withSession(['current_workspace_id' => $workspaceA->id])
            ->get("/datasets/{$datasetB->id}");

        $response->assertStatus(403);
    }

    public function test_viewer_role_cannot_upload_datasets(): void
    {
        [$owner, $workspace] = $this->createWorkspaceWithUser('owner@test.local');

        $viewer = User::create([
            'name' => 'Viewer User',
            'email' => 'viewer_test@test.local',
            'password' => Hash::make('password123'),
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($viewer)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get('/datasets/create');

        $response->assertStatus(403);
    }

    public function test_analyst_can_view_upload_page_in_own_workspace(): void
    {
        [$owner, $workspace] = $this->createWorkspaceWithUser('owner_analyst@test.local');

        $analyst = User::create([
            'name' => 'Analyst User',
            'email' => 'analyst_test@test.local',
            'password' => Hash::make('password123'),
        ]);

        WorkspaceMember::create([
            'workspace_id' => $workspace->id,
            'user_id' => $analyst->id,
            'role' => 'analyst',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($analyst)
            ->withSession(['current_workspace_id' => $workspace->id])
            ->get('/datasets/create');

        $response->assertStatus(200);
    }
}

