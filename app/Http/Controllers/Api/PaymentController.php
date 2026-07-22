<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\CostEstimator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function estimateCost(Request $request, CostEstimator $estimator): JsonResponse
    {
        $validated = $request->validate([
            'character_count' => ['required', 'integer', 'min:1'],
            'language_pair' => ['required', 'string'],
            'service_level' => ['required', 'string', 'in:standard,premium,enterprise'],
        ]);

        return response()->json($estimator->estimate($validated));
    }

    public function createIntent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:stripe,paypal,momo,vnpay'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
        ]);

        // Placeholder for actual payment gateway integration
        return response()->json([
            'intent_id' => 'pi_' . bin2hex(random_bytes(16)),
            'client_secret' => 'cs_' . bin2hex(random_bytes(16)),
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
            'status' => 'requires_payment_method',
        ], 201);
    }

    public function confirm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'intent_id' => ['required', 'string'],
            'payment_method' => ['required', 'string'],
        ]);

        return response()->json([
            'intent_id' => $validated['intent_id'],
            'status' => 'succeeded',
        ]);
    }
}
