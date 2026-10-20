<?php

namespace App\Http\Controllers;

use App\Models\EvaluationPeriod;
use App\Models\IpcrRecord;
use App\Models\Notification;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ipcr\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** The SPA's single boot payload: everything the signed-in user may see. */
class BootstrapController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            Auth::logout();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $records = app(WorkflowService::class)->visibleQuery($user)->get();

        $settings = Setting::current();

        return response()->json([
            'version' => 1,
            'user' => $user->toApi(true),
            'users' => User::orderByRaw('rowid')
                ->get()
                ->map(fn (User $person) => $person->toApi($user->isAdmin() || $person->id === $user->id))
                ->values(),
            'units' => Unit::orderByRaw('rowid')
                ->get()
                ->map(fn (Unit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'departments' => $unit->departments,
                ])
                ->values(),
            'periods' => EvaluationPeriod::orderByRaw('rowid')
                ->get()
                ->map(fn (EvaluationPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'start' => $period->start,
                    'end' => $period->end,
                    'active' => $period->active,
                ])
                ->values(),
            'settings' => ['weights' => $settings->weights, 'scale' => $settings->scale],
            'records' => $records
                ->map(fn (IpcrRecord $record) => $record->toApi(
                    $record->status === 'Draft' && $record->owner_id !== $user->id
                ))
                ->values(),
            'notifications' => Notification::where('user', $user->id)
                ->get()
                ->map->toApi()
                ->values(),
            'recordCount' => IpcrRecord::count(),
        ]);
    }
}
