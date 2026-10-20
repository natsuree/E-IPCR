<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function readAll(Request $request): JsonResponse
    {
        Notification::where('user', $request->user()->id)->update(['read' => true]);

        return response()->json([
            'notifications' => Notification::where('user', $request->user()->id)
                ->get()
                ->map->toApi()
                ->values(),
        ]);
    }
}
