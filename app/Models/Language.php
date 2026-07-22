<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LanguageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $native_name
 * @property int $tier
 * @property bool $is_active
 * @property string $fallback_locale
 * @property float|null $quality_score
 * @property float|null $baseline_score
 */
class Language extends Model
{
    /** @use HasFactory<LanguageFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'native_name', 'tier',
        'is_active', 'fallback_locale',
        'quality_score', 'baseline_score',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
            'is_active' => 'boolean',
            'quality_score' => 'float',
            'baseline_score' => 'float',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTier($query, int $tier)
    {
        return $query->where('tier', $tier);
    }

    public function scopeBaseline($query)
    {
        return $query->whereIn('code', config('languages.baseline_languages'));
    }

    public function isRtl(): bool
    {
        return in_array($this->code, config('languages.rtl', []), true);
    }

    public function isGendered(): bool
    {
        return in_array($this->code, config('languages.gendered', []), true);
    }
}
