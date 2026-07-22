<?php

declare(strict_types=1);

use App\Services\EventTracking\UserAgentParser;

it('detects desktop device type', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

    expect($result['device_type'])->toBe('desktop');
});

it('detects mobile device type', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15');

    expect($result['device_type'])->toBe('mobile');
});

it('detects tablet device type', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X) AppleWebKit/605.1.15');

    expect($result['device_type'])->toBe('tablet');
});

it('detects Chrome browser', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (Windows NT 10.0) Chrome/120.0.0.0 Safari/537.36');

    expect($result['browser'])->toBe('Chrome');
});

it('detects Safari browser', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/605.1.15');

    expect($result['browser'])->toBe('Safari');
});

it('detects Firefox browser', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (Windows NT 10.0) Firefox/120.0');

    expect($result['browser'])->toBe('Firefox');
});

it('detects Edge browser', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (Windows NT 10.0) Edg/120.0.0.0');

    expect($result['browser'])->toBe('Edge');
});

it('identifies bot user agents', function (): void {
    $parser = new UserAgentParser;

    expect($parser->isBot('Googlebot/2.1'))->toBeTrue()
        ->and($parser->isBot('bingbot/2.0'))->toBeTrue()
        ->and($parser->isBot('HeadlessChrome'))->toBeTrue()
        ->and($parser->isBot('puppeteer/1.0'))->toBeTrue();
});

it('identifies non-bot user agents', function (): void {
    $parser = new UserAgentParser;

    expect($parser->isBot('Mozilla/5.0 Chrome/120'))->toBeFalse();
});

it('flags empty user agent as bot', function (): void {
    $parser = new UserAgentParser;

    expect($parser->isBot(''))->toBeTrue()
        ->and($parser->isBot('-'))->toBeTrue();
});

it('returns complete structured result', function (): void {
    $parser = new UserAgentParser;
    $result = $parser->parse('Mozilla/5.0 (iPhone) Chrome/120 Mobile');

    expect($result)->toHaveKeys(['device_type', 'browser', 'is_bot'])
        ->and($result['device_type'])->toBeString()
        ->and($result['browser'])->toBeString()
        ->and($result['is_bot'])->toBeBool();
});
