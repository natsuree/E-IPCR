<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    /** Keys never written to the audit trail. */
    private const HIDDEN = ['password', 'password_confirmation', 'current_password', 'remember_token', 'token'];

    public static function log(string $event, string $description, ?Model $subject = null, ?array $changes = null, ?User $by = null): AuditLog
    {
        $by ??= auth()->user();

        if ($changes !== null) {
            $changes = array_diff_key($changes, array_flip(self::HIDDEN));
        }

        return AuditLog::create([
            'user_id' => $by?->id,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => $changes ?: null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
