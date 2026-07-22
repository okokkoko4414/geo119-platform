<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CostLog extends Model
{
    protected $fillable = [
        'operation_type',
        'input_tokens',
        'output_tokens',
        'model',
        'latency_ms',
        'cost_cents',
        'source_text_hash',
        'locale',
        'log_date',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'latency_ms' => 'integer',
        'cost_cents' => 'float',
        'log_date' => 'date',
    ];
}
