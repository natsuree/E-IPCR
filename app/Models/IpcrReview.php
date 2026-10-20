<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrReview extends Model
{
    public const ACTIONS = [
        'submitted' => 'Submitted',
        'resubmitted' => 'Resubmitted',
        'approved' => 'Approved & forwarded',
        'returned' => 'Returned for correction',
        'completed' => 'Approved (final)',
    ];

    protected $fillable = ['ipcr_id', 'user_id', 'role', 'action', 'remarks', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['role' => Role::class];
    }

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? ucfirst($this->action);
    }
}
