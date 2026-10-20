<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateChangeLog extends Model
{
    protected $fillable = ['ipcr_template_id', 'user_id', 'description'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(IpcrTemplate::class, 'ipcr_template_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
