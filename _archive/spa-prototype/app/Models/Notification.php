<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'user', 'text', 'record', 'at', 'read'];

    protected $casts = ['read' => 'boolean'];

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user,
            'text' => $this->text,
            'record' => $this->record,
            'at' => $this->at,
            'read' => $this->read,
        ];
    }
}
