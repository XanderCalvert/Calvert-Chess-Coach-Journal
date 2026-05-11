<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'display_name'          => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'display_name' => $data['display_name'],
            'email'        => $data['email'],
            'password'     => Hash::make($data['password']),
        ]);

        $token = $user->createToken('web-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->append('has_connected_accounts'),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        // One active web-session token per user.
        $user->tokens()->where('name', 'web-session')->delete();
        $token = $user->createToken('web-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user'  => $user->append('has_connected_accounts'),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        } else {
            // Fallback: revoke all web-session tokens (covers test transient token path).
            $request->user()->tokens()->where('name', 'web-session')->delete();
        }

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->append('has_connected_accounts');

        $quota = $user->isPremium()
            ? ['quota_limit' => null, 'quota_used' => null, 'quota_remaining' => null, 'quota_period_start' => null]
            : [
                'quota_limit'        => $user->quotaLimit(),
                'quota_used'         => $user->analysis_quota_used,
                'quota_remaining'    => $user->quotaRemaining(),
                'quota_period_start' => $user->quota_period_start?->toDateString(),
            ];

        return response()->json(array_merge($user->toArray(), [
            'subscription_tier' => $user->subscription_tier,
            'quota'             => $quota,
        ]));
    }
}
