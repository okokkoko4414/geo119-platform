@props([
    'publishableKey' => '',
    'amount' => 0,
    'currency' => 'usd',
    'locale' => 'en',
    'class' => '',
])

<div {{ $attributes->merge(['class' => "card-base p-6 $class"]) }} data-stripe-container>
    <h3 class="text-sm font-semibold text-surface-700 mb-3">{{ __('ui.payment.method.stripe') }}</h3>
    <div id="stripe-card-element" class="rounded-lg border border-surface-300 p-3 min-h-[42px]"></div>
    <div id="stripe-card-errors" class="mt-2 text-xs text-red-600" role="alert" hidden></div>
    <p class="mt-3 text-xs text-surface-500">
        {{ __('ui.payment.cost_before_confirm', ['amount' => number_format($amount, 2), 'currency' => strtoupper($currency)]) }}
    </p>
</div>

@push('scripts')
<script type="module">
    import { initStripe } from '{{ Vite::asset('resources/js/app.js') }}';
    (async () => {
        const stripe = await initStripe('{{ $publishableKey }}');
        if (!stripe) return;
        const elements = stripe.elements({ locale: '{{ $locale }}' });
        const card = elements.create('card', {
            style: {
                base: { fontFamily: '"Inter", system-ui, sans-serif', fontSize: '14px', color: '#1e293b' },
            },
        });
        card.mount('#stripe-card-element');
        card.on('change', (e) => {
            const errorsEl = document.getElementById('stripe-card-errors');
            errorsEl.textContent = e.error?.message || '';
            errorsEl.hidden = !e.error;
        });
    })();
</script>
@endpush
