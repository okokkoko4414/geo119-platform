<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LanguageRegistry
{
    private const CONFIG_KEY = 'languages.languages';

    /** @var array<string, array{code: string, name: string, native_name: string, tier: int}> */
    private array $definitions;

    public function __construct()
    {
        $this->definitions = [];
        foreach (config(self::CONFIG_KEY, []) as $lang) {
            $this->definitions[$lang['code']] = $lang;
        }
    }

    public function all(): Collection
    {
        return Language::orderBy('tier')->orderBy('name')->get();
    }

    public function active(): Collection
    {
        return Language::active()->orderBy('tier')->orderBy('name')->get();
    }

    public function tier(int $tier): Collection
    {
        return Language::active()->tier($tier)->orderBy('name')->get();
    }

    public function count(): int
    {
        return Language::active()->count();
    }

    public function tierCounts(): array
    {
        return [
            1 => Language::active()->tier(1)->count(),
            2 => Language::active()->tier(2)->count(),
            3 => Language::active()->tier(3)->count(),
        ];
    }

    public function findByCode(string $code): ?Language
    {
        return Language::where('code', $code)->first();
    }

    public function findActiveByCode(string $code): ?Language
    {
        return Language::active()->where('code', $code)->first();
    }

    public function getDefinition(string $code): ?array
    {
        return $this->definitions[$code] ?? null;
    }

    /** @return array<int, array{code: string, name: string, native_name: string, tier: int}> */
    public function getDefinitions(): array
    {
        return array_values($this->definitions);
    }

    public function definitionCount(): int
    {
        return count($this->definitions);
    }

    public function getBaselineLanguages(): array
    {
        return config('languages.baseline_languages', []);
    }

    public function isRtl(string $code): bool
    {
        return in_array($code, config('languages.rtl', []), true);
    }

    public function isGendered(string $code): bool
    {
        return in_array($code, config('languages.gendered', []), true);
    }

    public function activate(string $code): Language
    {
        $def = $this->getDefinition($code);
        if ($def === null) {
            throw new RuntimeException("Unknown language code: {$code}");
        }

        return Language::updateOrCreate(
            ['code' => $code],
            [
                'name' => $def['name'],
                'native_name' => $def['native_name'],
                'tier' => $def['tier'],
                'is_active' => true,
            ]
        );
    }

    public function deactivate(string $code): void
    {
        $lang = $this->findByCode($code);
        if ($lang) {
            $lang->update(['is_active' => false]);
        }
    }

    public function setQualityScore(string $code, float $score): void
    {
        $lang = $this->findByCode($code);
        if ($lang) {
            $lang->update(['quality_score' => $score]);
        }
    }

    public function setBaselineScore(string $code, float $score): void
    {
        $lang = $this->findByCode($code);
        if ($lang) {
            $lang->update(['baseline_score' => $score]);
        }
    }

    public function getQualityThreshold(int $tier): float
    {
        return config("languages.tiers.{$tier}.threshold", 0.0);
    }

    public function boot(): void
    {
        DB::transaction(function (): void {
            Language::where('is_active', true)->update(['is_active' => false]);

            foreach ($this->definitions as $def) {
                Language::updateOrCreate(
                    ['code' => $def['code']],
                    [
                        'name' => $def['name'],
                        'native_name' => $def['native_name'],
                        'tier' => $def['tier'],
                        'is_active' => true,
                    ]
                );
            }
        });
    }
}
