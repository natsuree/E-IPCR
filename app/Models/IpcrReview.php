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

    /** Reviewer decisions; these carry per-target ratings. */
    public const DECISIONS = ['approved', 'returned', 'completed'];

    protected $fillable = ['ipcr_id', 'user_id', 'role', 'action', 'remarks', 'item_ratings', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return ['role' => Role::class, 'item_ratings' => 'array'];
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

    public function isDecision(): bool
    {
        return in_array($this->action, self::DECISIONS, true);
    }

    /** A decision saved before per-target ratings were recorded with each review. */
    public function lacksRatingSnapshot(): bool
    {
        return $this->isDecision() && $this->item_ratings === null;
    }
}
