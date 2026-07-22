<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Language;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

final class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'code' => Str::lower(Str::random(2)),
            'name' => fake()->language(),
            'native_name' => fake()->word(),
            'tier' => fake()->numberBetween(1, 3),
            'is_active' => true,
            'fallback_locale' => 'en',
            'quality_score' => null,
            'baseline_score' => null,
        ];
    }

    public function tier(int $tier): static
    {
        return $this->state(fn (array $attributes) => ['tier' => $tier]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
