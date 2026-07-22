<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\DeadLetterQueue;
use Psr\Log\NullLogger;

beforeEach(function () {
    $this->redis = new FakeRedisStore();
    $this->dlq = new DeadLetterQueue($this->redis, new NullLogger());
});

test('push adds entry and increments count', function () {
    $this->dlq->push([
        'index' => 0,
        'source_text' => 'failed text',
        'locale' => 'zh-CN',
        'type' => 'grammar',
        'error' => 'Connection timeout',
        'attempts' => 3,
    ]);

    expect($this->dlq->count())->toBe(1);
});

test('list returns all entries', function () {
    $this->dlq->push([
        'index' => 0,
        'source_text' => 'first failure',
        'locale' => 'zh-CN',
        'type' => 'grammar',
        'error' => 'Timeout',
        'attempts' => 3,
    ]);

    $this->dlq->push([
        'index' => 1,
        'source_text' => 'second failure',
        'locale' => 'ja-JP',
        'type' => 'clarity',
        'error' => 'Server error',
        'attempts' => 3,
    ]);

    $entries = $this->dlq->list();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]['error'])->toBe('Timeout')
        ->and($entries[1]['error'])->toBe('Server error');
});

test('pop removes entry and returns its data', function () {
    $this->dlq->push([
        'index' => 0,
        'source_text' => 'pop test',
        'locale' => 'de-DE',
        'type' => 'full',
        'error' => 'Rate limited',
        'attempts' => 3,
    ]);

    expect($this->dlq->count())->toBe(1);

    $entry = $this->dlq->pop(
        $this->redis->lrange('optimization:dlq:list', 0, 0)[0]
    );

    expect($entry)->not->toBeNull()
        ->and($entry['source_text'])->toBe('pop test')
        ->and($this->dlq->count())->toBe(0);
});

test('remove deletes specific entry', function () {
    $this->dlq->push([
        'index' => 0,
        'source_text' => 'remove me',
        'locale' => 'es-ES',
        'type' => 'tone',
        'error' => 'Bad request',
        'attempts' => 3,
    ]);

    $this->dlq->push([
        'index' => 1,
        'source_text' => 'keep me',
        'locale' => 'fr-FR',
        'type' => 'fluency',
        'error' => 'Timeout',
        'attempts' => 3,
    ]);

    $entries = $this->dlq->list();
    $this->dlq->remove($entries[0]['id']);

    expect($this->dlq->count())->toBe(1);
    $remaining = $this->dlq->list();
    expect($remaining[0]['source_text'])->toBe('keep me');
});

test('clear removes all entries', function () {
    $this->dlq->push([
        'index' => 0, 'source_text' => 'a', 'locale' => 'en-US',
        'type' => 'grammar', 'error' => 'err', 'attempts' => 3,
    ]);
    $this->dlq->push([
        'index' => 1, 'source_text' => 'b', 'locale' => 'en-US',
        'type' => 'clarity', 'error' => 'err2', 'attempts' => 3,
    ]);

    expect($this->dlq->count())->toBe(2);

    $this->dlq->clear();
    expect($this->dlq->count())->toBe(0);
});

test('list returns empty when no entries', function () {
    expect($this->dlq->list())->toBeEmpty()
        ->and($this->dlq->count())->toBe(0);
});

test('entries include failed_at timestamp and all fields', function () {
    $this->dlq->push([
        'index' => 0,
        'source_text' => 'field check',
        'locale' => 'ko-KR',
        'type' => 'grammar',
        'error' => 'Internal server error',
        'attempts' => 3,
    ]);

    $entries = $this->dlq->list();
    $entry = $entries[0];

    expect($entry)->toHaveKey('id')
        ->and($entry)->toHaveKey('source_text')
        ->and($entry)->toHaveKey('locale')
        ->and($entry)->toHaveKey('type')
        ->and($entry)->toHaveKey('error')
        ->and($entry)->toHaveKey('attempts')
        ->and($entry)->toHaveKey('failed_at')
        ->and($entry['locale'])->toBe('ko-KR')
        ->and($entry['error'])->toBe('Internal server error')
        ->and($entry['attempts'])->toBe(3);
});

test('pop returns null for non-existent entry', function () {
    expect($this->dlq->pop('nonexistent'))->toBeNull();
});
