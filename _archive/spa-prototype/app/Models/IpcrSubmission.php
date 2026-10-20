<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrSubmission extends Model
{
    protected $table = 'ipcr_submissions';

    public $timestamps = false;

    protected $fillable = ['record_id', 'at', 'snapshot'];

    protected $casts = ['snapshot' => 'array'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(IpcrRecord::class, 'record_id');
    }

    /** Prototype submissions carry the snapshot fields inline alongside `at`. */
    public function toApi(): array
    {
        return ['at' => $this->at] + $this->snapshot;
    }
}
