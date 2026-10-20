<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpcrRecord extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ipcr_records';

    public $timestamps = false;

    protected $fillable = [
        'id', 'owner_id', 'name', 'position', 'unit', 'department', 'type',
        'period', 'status', 'created', 'updated', 'commitment', 'development',
        'mfo', 'ratee_date', 'discussion_date', 'rating_settings', 'final_rating',
    ];

    protected $casts = [
        'mfo' => 'array',
        'rating_settings' => 'array',
        'final_rating' => 'array',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(IpcrRow::class, 'record_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(IpcrHistoryEntry::class, 'record_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(IpcrSubmission::class, 'record_id');
    }

    /** Prototype-shaped JSON payload for the SPA. */
    public function toApi(bool $redact = false): array
    {
        if ($redact) {
            // Content of other people's drafts is never sent; only enough
            // metadata for roster progress ("Draft" state) in analytics.
            return [
                'id' => $this->id,
                'owner' => $this->owner_id,
                'name' => $this->name,
                'position' => $this->position,
                'unit' => $this->unit,
                'department' => $this->department,
                'type' => $this->type,
                'period' => $this->period,
                'status' => 'Draft',
                'created' => $this->created,
                'updated' => $this->updated,
                'commitment' => '',
                'development' => '',
                'rows' => [],
                'history' => [],
                'submissions' => [],
                'mfo' => null,
                'ratingSettings' => $this->rating_settings,
            ];
        }

        return [
            'id' => $this->id,
            'owner' => $this->owner_id,
            'name' => $this->name,
            'position' => $this->position,
            'unit' => $this->unit,
            'department' => $this->department,
            'type' => $this->type,
            'period' => $this->period,
            'status' => $this->status,
            'created' => $this->created,
            'updated' => $this->updated,
            'commitment' => $this->commitment,
            'development' => $this->development,
            'mfo' => $this->mfo,
            'rateeDate' => $this->ratee_date ?? '',
            'discussionDate' => $this->discussion_date ?? '',
            'rows' => $this->rows->map(fn (IpcrRow $row) => $row->toApi())->values()->all(),
            'history' => $this->history->map(fn (IpcrHistoryEntry $entry) => $entry->toApi())->values()->all(),
            'submissions' => $this->submissions->map(fn (IpcrSubmission $submission) => $submission->toApi())->values()->all(),
            'ratingSettings' => $this->rating_settings,
            'finalRating' => $this->final_rating,
        ];
    }
}
