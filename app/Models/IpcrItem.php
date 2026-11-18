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
        'evidence_url',
        'rating',
        'remarks',
        'sort_order',
        'employee_target_key',
    ];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'retired' => 'boolean', 'removed_at' => 'datetime'];
    }

    public function ipcr(): BelongsTo
    {
        return $this->belongsTo(Ipcr::class);
    }

    /** Added by the employee, not copied from an HR template. */
    public function isEmployeeTarget(): bool
    {
        return $this->employee_target_key !== null;
    }
}
