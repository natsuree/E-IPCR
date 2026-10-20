<?php

namespace App\Services\Ipcr;

use App\Models\IpcrRecord;
use App\Models\IpcrRow;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Handles editor saves (prototype autosave → PUT /api/records/{id}). */
class RecordService
{
    /**
     * Apply an editor payload to a Draft/Returned record. Only editable fields
     * are accepted; status, history, submissions, ownership and ratings
     * snapshots are server-managed and silently ignored.
     */
    public function updateEditable(User $user, IpcrRecord $record, array $input): IpcrRecord
    {
        if (! $user->isEmployee() || $record->owner_id !== $user->id) {
            throw new IpcrException('Only the owner can edit this IPCR.');
        }

        if (! in_array($record->status, ['Draft', 'Returned'], true)) {
            throw new IpcrException('Only Draft or returned IPCRs can be edited.');
        }

        $allowedPeriods = $user->periodIds();
        $allowedPeriods[] = $record->period;

        $period = $input['period'] ?? $record->period;
        if (! is_string($period) || ! in_array($period, array_unique($allowedPeriods), true)) {
            throw new IpcrException('You are not assigned to this evaluation period. Contact the administrator.');
        }

        $scalar = fn ($value, string $fallback = ''): string => is_scalar($value) ? (string) $value : $fallback;

        DB::transaction(function () use ($user, $record, $input, $period, $scalar) {
            $record->name = $scalar($input['name'] ?? $record->name);
            $record->position = $scalar($input['position'] ?? $record->position);
            $record->period = $period;
            $record->commitment = $scalar($input['commitment'] ?? $record->commitment);
            $record->development = $scalar($input['development'] ?? $record->development);
            $record->ratee_date = trim($scalar($input['rateeDate'] ?? '')) ?: null;
            $record->discussion_date = trim($scalar($input['discussionDate'] ?? '')) ?: null;
            $record->mfo = is_array($input['mfo'] ?? null) ? $input['mfo'] : null;
            $record->updated = WorkflowService::nowIso();
            $record->save();

            $this->syncRows($record, is_array($input['rows'] ?? null) ? $input['rows'] : []);
        });

        return $record->fresh()->load(['rows', 'history', 'submissions']);
    }

    /** Replace the record's rows with the posted set, preserving client row ids. */
    private function syncRows(IpcrRecord $record, array $rowsInput): void
    {
        $existing = $record->rows->keyBy('id');
        $seen = [];
        $keep = [];

        foreach ($rowsInput as $data) {
            if (! is_array($data)) {
                continue;
            }

            $id = $data['id'] ?? null;
            $id = is_string($id) && $id !== '' ? $id : (string) Str::uuid();
            if (isset($seen[$id])) {
                $id = (string) Str::uuid();
            }
            $seen[$id] = true;
            $keep[] = $id;

            $attributes = IpcrRowFactory::attributes($data);
            $row = $existing->get($id);

            if ($row) {
                $row->fill($attributes)->save();
            } else {
                IpcrRow::create(['id' => $id, 'record_id' => $record->id] + $attributes);
            }
        }

        $record->rows()->whereNotIn('id', $keep)->delete();
    }
}
