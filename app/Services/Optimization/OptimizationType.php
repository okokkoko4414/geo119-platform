<?php

declare(strict_types=1);

namespace App\Services\Optimization;

enum OptimizationType: string
{
    case Grammar = 'grammar';
    case Clarity = 'clarity';
    case Tone = 'tone';
    case Conciseness = 'conciseness';
    case Fluency = 'fluency';
    case Full = 'full';
}
