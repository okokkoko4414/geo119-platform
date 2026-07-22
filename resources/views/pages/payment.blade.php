@extends('layouts.app')

@php
$demoAmount = 250.00;
$currency = 'USD';
@endphp

@section('content')
<section class="section">
    <div class="page-container max-w-3xl">
        <div class="mb-10">
            <h1 class="text-3xl font-bold text-surface-900">{{ __('ui.payment.title') }}</h1>
            <p class="mt-2 text-surface-600">{{ __('ui.payment.select_method') }}</p>
        </div>

        <x-payment.cost-display :amount="$demoAmount" :subtotal="227.27" :tax="22.73" :currency="$currency" :locale="$locale" class="mb-8" />

        <div class="space-y-6">
            <x-payment.stripe publishableKey="{{ env('STRIPE_KEY', 'pk_test_placeholder') }}" :amount="$demoAmount" :locale="$locale" />
            <x-payment.paypal clientId="{{ env('PAYPAL_CLIENT_ID', 'test') }}" :amount="$demoAmount" />
            <x-payment.momo :amount="5750000" currency="VND" orderId="{{ 'ORD-' . Str::random(10) }}" />
            <x-payment.vnpay :amount="5750000" currency="VND" orderId="{{ 'ORD-' . Str::random(10) }}" />
        </div>
    </div>
</section>
@stop
