<?php

declare(strict_types=1);

namespace App\Services\Payment;

class CostEstimator
{
    /**
     * Estimate cost for language quality optimization.
     * CEO non-negotiable #1: cost displayed before every payment confirmation.
     *
     * @param array{character_count: int, language_pair: string, service_level: string} $params
     * @return array{subtotal: float, tax: float, total: float, currency: string, breakdown: array}
     */
    public function estimate(array $params): array
    {
        $characterCount = $params['character_count'];
        $rate = $this->ratePerCharacter($params['language_pair'], $params['service_level']);

        $subtotal = round($characterCount * $rate, 2);
        $tax = round($subtotal * 0.10, 2);
        $total = round($subtotal + $tax, 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'currency' => 'USD',
            'breakdown' => [
                [
                    'label' => "Translation ({$params['language_pair']})",
                    'characters' => $characterCount,
                    'rate' => $rate,
                    'amount' => $subtotal,
                ],
                [
                    'label' => 'Tax (10%)',
                    'amount' => $tax,
                ],
            ],
        ];
    }

    private function ratePerCharacter(string $languagePair, string $serviceLevel): float
    {
        $baseRates = [
            'standard' => 0.00005,
            'premium' => 0.00012,
            'enterprise' => 0.00025,
        ];

        return $baseRates[$serviceLevel] ?? $baseRates['standard'];
    }
}
