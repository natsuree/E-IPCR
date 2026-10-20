<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Public list of active sample accounts for the sign-in page. */
    public function accounts(): JsonResponse
    {
        return response()->json(
            User::where('active', true)
                ->orderByRaw('rowid')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'role' => $user->role,
                    'email' => $user->email,
                ])
                ->values()
        );
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::whereRaw('lower(email) = ?', [mb_strtolower($data['email'])])->first();

        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->active) {
            return response()->json([
                'message' => 'The email or password is incorrect, or this sample account is inactive.',
            ], 422);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->toApi(true)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
