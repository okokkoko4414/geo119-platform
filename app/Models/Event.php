<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'user_id',
        'session_id',
        'locale',
        'country',
        'device_type',
        'browser',
        'is_bot',
        'target_url',
        'referrer_url',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_bot' => 'boolean',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
