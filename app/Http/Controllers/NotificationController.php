<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('notifications.index', [
            'notifications' => $request->user()->notifications()->paginate(20),
        ]);
    }

    /** Mark as read, then follow the notification's link. */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $user = $request->user();
        $item = $user->notifications()->findOrFail($notification);
        $item->markAsRead();

        $route = $item->data['route'] ?? null;
        $ownPrefix = $user->routePrefix().'.';

        // Only follow links into the user's own area.
        if ($route && str_starts_with($route, $ownPrefix) && Route::has($route)) {
            return redirect()->route($route, $item->data['params'] ?? []);
        }

        return redirect()->route($ownPrefix.'notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications are marked as read.');
    }
}
