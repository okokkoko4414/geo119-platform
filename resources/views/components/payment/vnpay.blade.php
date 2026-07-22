@props([
    'amount' => 0,
    'currency' => 'VND',
    'orderId' => '',
    'class' => '',
])

<div {{ $attributes->merge(['class' => "card-base p-6 $class"]) }}>
    <h3 class="text-sm font-semibold text-surface-700 mb-3">{{ __('ui.payment.method.vnpay') }}</h3>
    <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
            <span class="text-blue-600 font-bold text-sm">VN</span>
        </div>
        <div class="flex-1">
            <p class="text-sm text-surface-700">{{ __('ui.payment.cost_before_confirm', ['amount' => number_format($amount, 0), 'currency' => $currency]) }}</p>
        </div>
    </div>
    <form method="POST" action="{{ route('payment.process') }}" class="mt-4">
        @csrf
        <input type="hidden" name="payment_method" value="vnpay">
        <input type="hidden" name="amount" value="{{ $amount }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="order_id" value="{{ $orderId }}">
        <x-button type="submit" variant="primary" size="lg" class="w-full">
            {{ __('ui.payment.confirm_button', ['amount' => number_format($amount, 0) . ' ' . $currency]) }}
        </x-button>
    </form>
</div>
