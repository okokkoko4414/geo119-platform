<?php

declare(strict_types=1);

namespace App\Services\Payment;

class PaymentGateway
{
    public const STRIPE = 'stripe';
    public const PAYPAL = 'paypal';
    public const MOMO = 'momo';
    public const VNPAY = 'vnpay';

    /**
     * @return list<array{id: string, name: string, type: string, priority: int}>
     */
    public function availableMethods(string $country = 'VN'): array
    {
        $all = [
            ['id' => self::STRIPE, 'name' => 'Stripe', 'type' => 'card', 'priority' => 30],
            ['id' => self::PAYPAL, 'name' => 'PayPal', 'type' => 'wallet', 'priority' => 20],
            ['id' => self::MOMO, 'name' => 'MoMo', 'type' => 'wallet', 'priority' => 50],
            ['id' => self::VNPAY, 'name' => 'VNPay', 'type' => 'bank_transfer', 'priority' => 40],
        ];

        // CEO non-negotiable #5: MoMo + VNPay prioritized over Stripe/PayPal
        usort($all, fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $all;
    }
}
