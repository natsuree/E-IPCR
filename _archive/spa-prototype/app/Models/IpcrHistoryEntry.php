<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrHistoryEntry extends Model
{
    protected $table = 'ipcr_history';

    public $timestamps = false;

    protected $fillable = ['record_id', 'at', 'actor', 'role', 'action', 'comment'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(IpcrRecord::class, 'record_id');
    }

    public function toApi(): array
    {
        return [
            'at' => $this->at,
            'actor' => $this->actor,
            'role' => $this->role,
            'action' => $this->action,
            'comment' => $this->comment,
        ];
    }
}
