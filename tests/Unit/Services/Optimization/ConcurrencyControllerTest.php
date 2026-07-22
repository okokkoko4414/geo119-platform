<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Optimization;

use App\Services\Optimization\ConcurrencyController;

beforeEach(function () {
    $this->redis = new FakeRedisStore();
    $this->controller = new ConcurrencyController($this->redis, maxConcurrent: 5);
});

test('initialize sets max concurrent slots', function () {
    $this->controller->initialize();
    expect($this->controller->availableSlots())->toBe(5);
});

test('initialize is idempotent', function () {
    $this->controller->initialize();
    $this->controller->acquire(); // Use one slot
    expect($this->controller->availableSlots())->toBe(4);

    $this->controller->initialize(); // Should not reset
    expect($this->controller->availableSlots())->toBe(4);
});

test('acquire decrements available slots', function () {
    $this->controller->initialize();
    expect($this->controller->acquire())->toBeTrue();
    expect($this->controller->availableSlots())->toBe(4);

    expect($this->controller->acquire())->toBeTrue();
    expect($this->controller->availableSlots())->toBe(3);
});

test('release increments available slots', function () {
    $this->controller->initialize();
    $this->controller->acquire();
    $this->controller->acquire();
    expect($this->controller->availableSlots())->toBe(3);

    $this->controller->release();
    expect($this->controller->availableSlots())->toBe(4);
});

test('acquire returns false when no slots available', function () {
    $this->controller->initialize();

    // Exhaust all 5 slots
    for ($i = 0; $i < 5; $i++) {
        expect($this->controller->acquire())->toBeTrue();
    }

    expect($this->controller->availableSlots())->toBe(0);
    expect($this->controller->acquire())->toBeFalse();
});

test('release does not exceed max concurrent', function () {
    $this->controller->initialize();
    $this->controller->release(); // Release without acquire
    $this->controller->release();
    $this->controller->release();

    expect($this->controller->availableSlots())->toBe(5);
});

test('B3.9: max concurrent respected with many acquires', function () {
    $this->controller->initialize();

    $acquired = 0;
    for ($i = 0; $i < 30; $i++) {
        if ($this->controller->acquire()) {
            $acquired++;
        }
    }

    expect($acquired)->toBe(5)
        ->and($acquired)->toBeLessThan(6);
});

test('reconcile resets to max concurrent', function () {
    $this->controller->initialize();
    $this->controller->acquire();
    $this->controller->acquire();
    $this->controller->acquire();
    expect($this->controller->availableSlots())->toBe(2);

    $this->controller->reconcile();
    expect($this->controller->availableSlots())->toBe(5);
});

test('default max concurrent matches issue spec (10)', function () {
    $defaultController = new ConcurrencyController($this->redis);
    $defaultController->initialize();
    expect($defaultController->availableSlots())->toBe(10);
});

test('acquire and release cycle maintains correct count', function () {
    $this->controller->initialize();

    for ($cycle = 0; $cycle < 10; $cycle++) {
        expect($this->controller->acquire())->toBeTrue();
        $this->controller->release();
    }

    expect($this->controller->availableSlots())->toBe(5);
});
