@extends('layouts.app')

@section('content')
<section class="section">
    <div class="page-container text-center">
        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-bold text-surface-900 text-balance">
            {{ __('ui.home.title') }}
        </h1>
        <p class="mt-6 max-w-2xl mx-auto text-lg text-surface-600 text-balance">
            {{ __('ui.home.subtitle') }}
        </p>
        <div class="mt-10 flex flex-wrap gap-4 justify-center">
            <x-button href="{{ route('component-gallery', ['locale' => $locale !== 'en' ? $locale : null]) }}" variant="primary" size="lg">
                {{ __('ui.home.cta') }}
            </x-button>
            <x-button href="#" variant="secondary" size="lg">
                {{ __('ui.nav.docs') }}
            </x-button>
        </div>
    </div>
</section>

<section class="section bg-white">
    <div class="page-container">
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <x-card>
                <div class="w-12 h-12 rounded-lg bg-primary-100 flex items-center justify-center mb-4">
                    @include('components.icon', ['name' => 'check', 'class' => 'w-6 h-6 text-primary-600'])
                </div>
                <h3 class="text-lg font-semibold text-surface-900">{{ __('ui.home.feature_scoring') }}</h3>
                <p class="mt-2 text-sm text-surface-600">{{ __('ui.home.feature_scoring_desc') }}</p>
            </x-card>
            <x-card>
                <div class="w-12 h-12 rounded-lg bg-accent-100 flex items-center justify-center mb-4">
                    @include('components.icon', ['name' => 'grid', 'class' => 'w-6 h-6 text-accent-600'])
                </div>
                <h3 class="text-lg font-semibold text-surface-900">{{ __('ui.home.feature_optimization') }}</h3>
                <p class="mt-2 text-sm text-surface-600">{{ __('ui.home.feature_optimization_desc') }}</p>
            </x-card>
            <x-card>
                <div class="w-12 h-12 rounded-lg bg-yellow-100 flex items-center justify-center mb-4">
                    @include('components.icon', ['name' => 'search', 'class' => 'w-6 h-6 text-yellow-600'])
                </div>
                <h3 class="text-lg font-semibold text-surface-900">{{ __('ui.home.feature_tracking') }}</h3>
                <p class="mt-2 text-sm text-surface-600">{{ __('ui.home.feature_tracking_desc') }}</p>
            </x-card>
        </div>
    </div>
</section>
@stop
