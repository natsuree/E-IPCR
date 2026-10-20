<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpcrRow extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'ipcr_rows';

    public $timestamps = false;

    protected $fillable = [
        'id', 'record_id', 'category', 'applicable', 'program', 'outcome',
        'indicator_type', 'indicator', 'target', 'actual', 'q', 'e', 't', 'remarks',
    ];

    protected $casts = [
        'applicable' => 'boolean',
        'q' => 'json',
        'e' => 'json',
        't' => 'json',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(IpcrRecord::class, 'record_id');
    }

    /** Prototype-shaped row payload. Raw Q/E/T values are preserved exactly (number or ''). */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'applicable' => $this->applicable,
            'program' => $this->program,
            'outcome' => $this->outcome,
            'indicatorType' => $this->indicator_type,
            'indicator' => $this->indicator,
            'target' => $this->target,
            'actual' => $this->actual,
            'q' => $this->q,
            'e' => $this->e,
            't' => $this->t,
            'remarks' => $this->remarks,
        ];
    }
}
