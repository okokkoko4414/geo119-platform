<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $locale
 * @property string $namespace
 * @property string $key
 * @property string $value
 * @property string|null $source_value
 * @property float|null $quality_score
 * @property bool $is_machine_translated
 * @property bool $is_verified
 */
class Translation extends Model
{
    /** @use HasFactory<TranslationFactory> */
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'locale', 'namespace', 'key', 'value',
        'source_value', 'quality_score',
        'is_machine_translated', 'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'quality_score' => 'float',
            'is_machine_translated' => 'boolean',
            'is_verified' => 'boolean',
        ];
    }

    public function scopeLocale($query, string $locale)
    {
        return $query->where('locale', $locale);
    }

    public function scopeNamespace($query, string $namespace)
    {
        return $query->where('namespace', $namespace);
    }

    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function scopeMachineTranslated($query)
    {
        return $query->where('is_machine_translated', true);
    }
}
