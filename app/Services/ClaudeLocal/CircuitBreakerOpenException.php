<?php

declare(strict_types=1);

namespace App\Services\ClaudeLocal;

use RuntimeException;

final class CircuitBreakerOpenException extends RuntimeException {}
