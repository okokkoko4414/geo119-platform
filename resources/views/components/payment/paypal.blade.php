@props([
    'clientId' => '',
    'amount' => 0,
    'currency' => 'USD',
    'class' => '',
])

<div {{ $attributes->merge(['class' => "card-base p-6 $class"]) }} data-paypal-container>
    <h3 class="text-sm font-semibold text-surface-700 mb-3">{{ __('ui.payment.method.paypal') }}</h3>
    <div id="paypal-button-container"></div>
    <p class="mt-3 text-xs text-surface-500">
        {{ __('ui.payment.cost_before_confirm', ['amount' => number_format($amount, 2), 'currency' => $currency]) }}
    </p>
</div>

@push('scripts')
<script type="module">
    import { initPayPal } from '{{ Vite::asset('resources/js/app.js') }}';
    (async () => {
        const paypal = await initPayPal('{{ $clientId }}');
        if (!paypal) return;
        paypal.Buttons({
            createOrder: (data, actions) => actions.order.create({
                purchase_units: [{ amount: { currency_code: '{{ $currency }}', value: '{{ $amount }}' } }],
            }),
            onApprove: (data, actions) => actions.order.capture().then(() => {
                window.location.href = '/payment/success';
            }),
        }).render('#paypal-button-container');
    })();
</script>
@endpush
