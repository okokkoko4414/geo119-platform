@extends('layouts.app')

@push('head')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endpush

@section('content')
<div class="page-container py-8">
    <h1 class="text-2xl font-bold mb-6 text-surface-900">{{ __('ui.dashboard.title') }}</h1>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-8" id="overview-cards">
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.dashboard.impressions_today') }}</p>
            <p class="text-3xl font-bold mt-2 text-surface-900" id="counter-impressions">{{ $impressions }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.dashboard.clicks_today') }}</p>
            <p class="text-3xl font-bold mt-2 text-surface-900" id="counter-clicks">{{ $clicks }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
            <p class="text-sm text-surface-500 uppercase tracking-wide">{{ __('ui.dashboard.ctr_today') }}</p>
            <p class="text-3xl font-bold mt-2 text-surface-900" id="counter-ctr">
                {{ $ctr !== null ? $ctr . '%' : '—' }}
            </p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 mb-8">
        <h2 class="text-lg font-semibold mb-4 text-surface-900">{{ __('ui.dashboard.chart_title') }}</h2>
        <canvas id="time-series-chart" height="80"></canvas>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6">
        <h2 class="text-lg font-semibold mb-4 text-surface-900">{{ __('ui.dashboard.language_breakdown') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left" id="language-table" data-no-data="{{ __('ui.dashboard.no_data') }}">
                <thead class="border-b border-surface-200">
                    <tr>
                        <th class="py-3 pr-4 font-medium text-surface-500">{{ __('ui.dashboard.header_language') }}</th>
                        <th class="py-3 pr-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_impressions') }}</th>
                        <th class="py-3 pr-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_clicks') }}</th>
                        <th class="py-3 pr-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_ctr') }}</th>
                        <th class="py-3 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_change') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="py-10 text-center text-surface-400">{{ __('ui.dashboard.loading') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/analytics.js') }}"></script>
@endpush
