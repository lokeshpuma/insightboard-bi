<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ApiTokenController extends ApiController
{
    /**
     * Issue a new personal access token
     */
    public function issue(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'token_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $tokenName = $validated['token_name'] ?? 'api-token';
        $token = $user->createToken($tokenName);

        return $this->success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 'API token created successfully', 201);
    }

    /**
     * List user tokens
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->get(['id', 'name', 'last_used_at', 'created_at']);

        return $this->success($tokens, 'Tokens retrieved successfully');
    }

    /**
     * Revoke current token
     */
    public function revokeCurrent(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Current token revoked successfully');
    }

    /**
     * Revoke a specific token
     */
    public function revoke(Request $request, int $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (!$token) {
            return $this->error('Token not found', 404);
        }

        $token->delete();

        return $this->success(null, 'Token revoked successfully');
    }
}
