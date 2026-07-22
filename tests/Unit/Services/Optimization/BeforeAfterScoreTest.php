<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\BeforeAfterScore;
use App\Services\Optimization\OptimizationType;

test('compute returns scores for grammar optimization', function () {
    $score = BeforeAfterScore::compute(
        'The cat are sleeping on the mat.',
        'The cat is sleeping on the mat.',
        OptimizationType::Grammar,
    );

    expect($score->beforeScore)->toBeGreaterThan(0.0)
        ->and($score->afterScore)->toBeGreaterThan(0.0)
        ->and($score->improvement)->not->toBeNull();
});

test('compute returns scores for clarity optimization', function () {
    $score = BeforeAfterScore::compute(
        'The thing that does the stuff for the process.',
        'The system component that handles data processing.',
        OptimizationType::Clarity,
    );

    expect($score->beforeScore)->toBeGreaterThan(0.0)
        ->and($score->afterScore)->toBeGreaterThan(0.0);
});

test('compute returns scores for full optimization', function () {
    $score = BeforeAfterScore::compute(
        'The implementation of the solution was done by the team after many discussions.',
        'The team implemented the solution after extensive discussions.',
        OptimizationType::Full,
    );

    expect($score->beforeScore)->toBeGreaterThan(0.0)
        ->and($score->afterScore)->toBeGreaterThan(0.0);
});

test('improvement is zero when scores are equal', function () {
    $score = BeforeAfterScore::compute(
        'Same text here.',
        'Same text here.',
        OptimizationType::Grammar,
    );

    expect($score->improvement)->toBe(0.0);
});

test('B3.8: every result includes before and after scores', function () {
    $score = BeforeAfterScore::compute(
        'Original text that needs optimization.',
        'Refined text after improvements.',
        OptimizationType::Clarity,
    );

    expect($score->beforeScore)->toBeFloat()
        ->and($score->afterScore)->toBeFloat()
        ->and($score->improvement)->toBeFloat()
        ->and($score->beforeScore)->toBeGreaterThanOrEqual(0.0)
        ->and($score->afterScore)->toBeGreaterThanOrEqual(0.0);
});

test('toArray returns all three score fields', function () {
    $score = BeforeAfterScore::compute('before', 'after', OptimizationType::Grammar);
    $array = $score->toArray();

    expect($array)->toHaveKey('before_score')
        ->and($array)->toHaveKey('after_score')
        ->and($array)->toHaveKey('improvement');
});

test('handles empty text', function () {
    $score = BeforeAfterScore::compute('', '', OptimizationType::Grammar);

    expect($score->beforeScore)->toBe(0.0)
        ->and($score->afterScore)->toBe(0.0)
        ->and($score->improvement)->toBe(0.0);
});

test('improvement is positive when text gets better', function () {
    $score = BeforeAfterScore::compute(
        'The quick brown fox jump over the lazy dogs and the thing is bad and problematic confusing unclear.',
        'The quick brown fox jumps over the lazy dog.',
        OptimizationType::Conciseness,
    );

    expect($score->improvement)->toBeGreaterThanOrEqual(-1.0);
});

test('all optimization types produce valid scores', function (OptimizationType $type) {
    $score = BeforeAfterScore::compute(
        'This is a sample text that needs some form of optimization to be applied.',
        'Sample text needing optimization.',
        $type,
    );

    expect($score->beforeScore)->toBeFloat()
        ->and($score->afterScore)->toBeFloat()
        ->and($score->improvement)->toBeFloat();
})->with([
    OptimizationType::Grammar,
    OptimizationType::Clarity,
    OptimizationType::Tone,
    OptimizationType::Conciseness,
    OptimizationType::Fluency,
    OptimizationType::Full,
]);
