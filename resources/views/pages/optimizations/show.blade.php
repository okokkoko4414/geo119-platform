@extends('layouts.app')

@section('content')
<div class="page-container py-8">
    <nav class="mb-6">
        <a href="{{ route('analytics.dashboard', ['locale' => $locale !== 'en' ? $locale : null]) }}"
           class="text-sm text-primary-600 hover:text-primary-800">
            &larr; {{ __('ui.dashboard.title') }}
        </a>
    </nav>

    <div class="mb-8">
        <h1 class="text-2xl font-bold text-surface-900">{{ __('ui.optimization.detail_title') }}</h1>
        <p class="text-sm text-surface-500 mt-1">
            {{ $result->optimization_type }} &middot; {{ $result->target_locale }} &middot; {{ $result->created_at->format('Y-m-d H:i') }}
        </p>
    </div>

    {{-- Score cards --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.optimization.before_score') }}</p>
            <p class="text-3xl font-bold mt-2 text-surface-900">{{ number_format($result->before_score * 100, 1) }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.optimization.after_score') }}</p>
            <p class="text-3xl font-bold mt-2 text-green-600">{{ number_format($result->after_score * 100, 1) }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.optimization.improvement') }}</p>
            <p class="text-3xl font-bold mt-2 {{ $result->improvement >= 0 ? 'text-green-600' : 'text-red-500' }}">
                {{ $result->improvement >= 0 ? '+' : '' }}{{ number_format($result->improvement * 100, 1) }}%
            </p>
        </div>
    </div>

    {{-- Source vs Optimized text --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-3 text-surface-900">{{ __('ui.optimization.source_text') }}</h2>
            <div class="prose prose-sm max-w-none text-surface-700 whitespace-pre-wrap">{{ $result->source_text }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <h2 class="text-lg font-semibold mb-3 text-surface-900">{{ __('ui.optimization.optimized_text') }}</h2>
            <div class="prose prose-sm max-w-none text-surface-700 whitespace-pre-wrap">{{ $result->optimized_text }}</div>
        </div>
    </div>

    {{-- Metadata --}}
    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold mb-4 text-surface-900">{{ __('ui.optimization.metadata') }}</h2>
        <dl class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.type') }}</dt>
                <dd class="font-medium text-surface-900">{{ $result->optimization_type }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.locale') }}</dt>
                <dd class="font-medium text-surface-900">{{ $result->target_locale }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.model') }}</dt>
                <dd class="font-medium text-surface-900">{{ $result->model }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.cost') }}</dt>
                <dd class="font-medium text-surface-900">${{ number_format($result->cost_cents / 100, 4) }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.input_tokens') }}</dt>
                <dd class="font-medium text-surface-900">{{ number_format($result->input_tokens) }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.output_tokens') }}</dt>
                <dd class="font-medium text-surface-900">{{ number_format($result->output_tokens) }}</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.latency') }}</dt>
                <dd class="font-medium text-surface-900">{{ $result->latency_ms }}ms</dd>
            </div>
            <div>
                <dt class="text-surface-500">{{ __('ui.optimization.cached') }}</dt>
                <dd class="font-medium text-surface-900">{{ $result->from_cache ? __('ui.common.yes') : __('ui.common.no') }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
