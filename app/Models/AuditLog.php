<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Read-only trail of important events. Never stores passwords or tokens. */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    public const EVENT_GROUPS = [
        'auth' => 'Sign-in and passwords',
        'user' => 'User accounts',
        'unit' => 'Colleges, offices and departments',
        'ipcr' => 'IPCR actions',
        'template' => 'IPCR templates',
        'period' => 'Rating periods',
        'settings' => 'Rating settings',
    ];

    protected $fillable = ['user_id', 'event', 'description', 'subject_type', 'subject_id', 'changes', 'ip_address'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function groupLabel(): string
    {
        return self::EVENT_GROUPS[strtok($this->event, '.')] ?? 'Other';
    }
}
