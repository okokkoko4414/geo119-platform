<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptimizationResult extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'source_text',
        'optimized_text',
        'target_locale',
        'optimization_type',
        'before_score',
        'after_score',
        'improvement',
        'cost_cents',
        'input_tokens',
        'output_tokens',
        'model',
        'latency_ms',
        'source_hash',
        'from_cache',
        'cached_at',
    ];

    protected $casts = [
        'before_score' => 'float',
        'after_score' => 'float',
        'improvement' => 'float',
        'cost_cents' => 'float',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'latency_ms' => 'integer',
        'from_cache' => 'boolean',
        'cached_at' => 'datetime',
    ];
}
