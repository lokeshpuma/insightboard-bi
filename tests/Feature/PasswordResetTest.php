<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_can_request_reset_link_and_reset_password(): void
    {
        $user = User::create([
            'name' => 'Reset Tester',
            'email' => 'reset_' . Str::random(6) . '@test.local',
            'password' => Hash::make('old_password123'),
        ]);

        // 1. Request reset link
        $resp = $this->post('/forgot-password', [
            'email' => $user->email,
        ]);
        $resp->assertRedirect();
        $resp->assertSessionHas('status');

        // Verify token saved in DB
        $tokenRecord = DB::table('password_reset_tokens')->where('email', $user->email)->first();
        $this->assertNotNull($tokenRecord);

        // 2. Simulate user with valid plain token
        $plainToken = Str::random(64);
        DB::table('password_reset_tokens')->where('email', $user->email)->update([
            'token' => Hash::make($plainToken),
            'created_at' => now(),
        ]);

        // 3. Reset password
        $resetResp = $this->post('/reset-password', [
            'token' => $plainToken,
            'email' => $user->email,
            'password' => 'new_secure_password',
            'password_confirmation' => 'new_secure_password',
        ]);

        $resetResp->assertRedirect('/login');
        $resetResp->assertSessionHas('success');

        // 4. Verify user can log in with new password
        $user->refresh();
        $this->assertTrue(Hash::check('new_secure_password', $user->password));

        // 5. Verify token was deleted
        $this->assertNull(DB::table('password_reset_tokens')->where('email', $user->email)->first());
    }

    public function test_rejects_invalid_token(): void
    {
        $user = User::create([
            'name' => 'Invalid Token User',
            'email' => 'invalid_' . Str::random(6) . '@test.local',
            'password' => Hash::make('some_password123'),
        ]);

        $response = $this->post('/reset-password', [
            'token' => 'completely_invalid_token',
            'email' => $user->email,
            'password' => 'new_password123',
            'password_confirmation' => 'new_password123',
        ]);

        $response->assertSessionHasErrors('email');
    }
}
