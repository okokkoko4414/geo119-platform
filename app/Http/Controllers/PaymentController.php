<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Payment\PaymentGateway;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(PaymentGateway $gateway): View
    {
        return view('pages.payment', [
            'locale' => app()->getLocale(),
            'methods' => $gateway->availableMethods(),
        ]);
    }

    public function process(): void
    {
        // Payment processing handled by frontend SDKs + API
    }
}
