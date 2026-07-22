@props([
    'amount' => 0,
    'currency' => 'USD',
    'subtotal' => null,
    'tax' => null,
    'locale' => 'en',
    'class' => '',
])

<div {{ $attributes->merge(['class' => "card-base p-6 $class"]) }} aria-label="{{ __('ui.payment.cost_summary') }}">
    <h3 class="text-sm font-semibold text-surface-500 uppercase tracking-wide mb-4">{{ __('ui.payment.subtotal') }}</h3>
    <dl class="space-y-3">
        <div class="flex justify-between text-sm">
            <dt class="text-surface-500">{{ __('ui.payment.subtotal') }}</dt>
            <dd class="font-medium text-surface-900">{{ number_format($subtotal ?? $amount, 2) }} {{ $currency }}</dd>
        </div>
        <div class="flex justify-between text-sm">
            <dt class="text-surface-500">{{ __('ui.payment.tax') }}</dt>
            <dd class="font-medium text-surface-900">{{ number_format($tax ?? 0, 2) }} {{ $currency }}</dd>
        </div>
        <hr class="border-surface-200" />
        <div class="flex justify-between text-base">
            <dt class="font-semibold text-surface-900">{{ __('ui.payment.total') }}</dt>
            <dd class="font-bold text-primary-700">{{ number_format($amount, 2) }} {{ $currency }}</dd>
        </div>
    </dl>
</div>
