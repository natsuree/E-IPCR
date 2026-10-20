<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $name = trim((string) $request->input('name'));
        $position = trim((string) $request->input('position'));

        if ($name === '' || $position === '') {
            return response()->json(['message' => 'Name and position are required.'], 422);
        }

        /** @var User $user */
        $user = $request->user();
        $user->name = $name;
        $user->position = $position;
        $user->save();

        return response()->json(['user' => $user->toApi(true)]);
    }
}
