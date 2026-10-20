<?php

namespace App\Http\Controllers;

use App\Models\IpcrRecord;
use App\Models\Notification;
use App\Models\Setting;
use App\Services\Ipcr\IpcrException;
use App\Services\Ipcr\RecordService;
use App\Services\Ipcr\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly RecordService $records,
    ) {
    }

    /** Create a new Draft for the signed-in employee (prototype edit/new). */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isEmployee()) {
            throw new IpcrException('Your account cannot create IPCR records.');
        }

        $data = $request->validate([
            'period' => ['required', 'string'],
        ]);

        if (! in_array($data['period'], $user->periodIds(), true)) {
            throw new IpcrException('You are not assigned to this evaluation period. Contact the administrator.');
        }

        $settings = Setting::current();
        $record = $this->workflow->createDraft($user, $data['period'], [
            'weights' => $settings->weights,
            'scale' => $settings->scale,
        ]);

        return response()->json(['record' => $record->toApi()]);
    }

    /** Editor autosave / explicit save of an editable record. */
    public function update(Request $request, IpcrRecord $record): JsonResponse
    {
        $record = $this->records->updateEditable($request->user(), $record, $request->all());

        return response()->json(['record' => $record->toApi()]);
    }

    /** Submit / approve / return — server-authoritative workflow. */
    public function transition(Request $request, IpcrRecord $record): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string'],
            'comment' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $updated = $this->workflow->transition($user, $record, $data['action'], $data['comment'] ?? '');

        return response()->json([
            'record' => $updated->toApi(),
            'notifications' => Notification::where('user', $user->id)->get()->map->toApi()->values(),
        ]);
    }
}
