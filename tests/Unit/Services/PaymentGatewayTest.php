<?php

declare(strict_types=1);

use App\Services\Payment\PaymentGateway;

test('available methods prioritizes MoMo and VNPay over Stripe and PayPal', function (): void {
    $gateway = new PaymentGateway;
    $methods = $gateway->availableMethods();

    $ids = array_column($methods, 'id');
    $momoPos = array_search('momo', $ids, true);
    $vnpayPos = array_search('vnpay', $ids, true);
    $stripePos = array_search('stripe', $ids, true);
    $paypalPos = array_search('paypal', $ids, true);

    // MoMo and VNPay should come before Stripe and PayPal
    expect($momoPos)->toBeLessThan($stripePos);
    expect($momoPos)->toBeLessThan($paypalPos);
    expect($vnpayPos)->toBeLessThan($stripePos);
    expect($vnpayPos)->toBeLessThan($paypalPos);
});

test('available methods returns all 4 gateways', function (): void {
    $gateway = new PaymentGateway;
    $methods = $gateway->availableMethods();

    expect($methods)->toHaveCount(4);
    expect(array_column($methods, 'id'))->toEqualCanonicalizing([
        'stripe', 'paypal', 'momo', 'vnpay',
    ]);
});
