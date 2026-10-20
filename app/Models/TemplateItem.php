<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateItem extends Model
{
    protected $fillable = ['ipcr_template_id', 'section', 'program_outcome', 'indicator', 'target', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(IpcrTemplate::class, 'ipcr_template_id');
    }
}
