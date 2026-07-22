<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Translation;
use Illuminate\Database\Eloquent\Factories\Factory;

final class TranslationFactory extends Factory
{
    protected $model = Translation::class;

    public function definition(): array
    {
        return [
            'locale' => 'en',
            'namespace' => 'ui',
            'key' => fake()->word(),
            'value' => fake()->sentence(),
            'source_value' => fake()->sentence(),
            'quality_score' => fake()->randomFloat(4, 0.5, 1.0),
            'is_machine_translated' => true,
            'is_verified' => false,
        ];
    }

    public function locale(string $locale): static
    {
        return $this->state(fn (array $attributes) => ['locale' => $locale]);
    }

    public function namespace(string $namespace): static
    {
        return $this->state(fn (array $attributes) => ['namespace' => $namespace]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => ['is_verified' => true]);
    }
}
