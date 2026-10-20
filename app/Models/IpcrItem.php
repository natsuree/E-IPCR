<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrItem extends Model
{
    protected $fillable = [
        'ipcr_id',
        'section',
        'program_outcome',
        'indicator',
        'target',
        'actual_accomplishment',
        'rating',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }
}
