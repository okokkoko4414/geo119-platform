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
            <table class="w-full text-sm text-left" id="language-table">
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
<script>
document.addEventListener('DOMContentLoaded', function () {
    var evtSource = new EventSource('/api/e/live');
    evtSource.addEventListener('counters', function (e) {
        var data = JSON.parse(e.data);
        document.getElementById('counter-impressions').textContent = data.impressions;
        document.getElementById('counter-clicks').textContent = data.clicks;
        document.getElementById('counter-ctr').textContent =
            data.ctr !== null ? data.ctr + '%' : '—';
    });

    var ctx = document.getElementById('time-series-chart').getContext('2d');
    var chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [
                {
                    label: 'Impressions',
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.05)',
                    fill: true,
                    tension: 0.2,
                },
                {
                    label: 'Clicks',
                    data: [],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16,185,129,0.05)',
                    fill: true,
                    tension: 0.2,
                },
            ],
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: '#f3f4f6' } },
            },
        },
    });

    fetch('/api/analytics/time-series?days=30')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            chart.data.labels = data.map(function (d) { return d.day; });
            chart.data.datasets[0].data = data.map(function (d) { return d.impressions; });
            chart.data.datasets[1].data = data.map(function (d) { return d.clicks; });
            chart.update();
        });

    fetch('/api/analytics/language-breakdown')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            var tbody = document.querySelector('#language-table tbody');
            if (!data.length) {
                tbody.innerHTML = '<tr><td colspan="5" class="py-10 text-center text-surface-400">' + '{{ __('ui.dashboard.no_data') }}' + '</td></tr>';
                return;
            }
            tbody.innerHTML = data.map(function (d) {
                var pctClass = d.pct_change > 0 ? 'text-green-600' : d.pct_change < 0 ? 'text-red-500' : 'text-surface-400';
                var pctText = d.pct_change !== null ? (d.pct_change > 0 ? '+' : '') + d.pct_change + '%' : '—';
                return '<tr class="border-b border-surface-100">' +
                    '<td class="py-3 pr-4 font-medium text-surface-900">' + he(d.locale) + '</td>' +
                    '<td class="py-3 pr-4 text-right">' + d.impressions.toLocaleString() + '</td>' +
                    '<td class="py-3 pr-4 text-right">' + d.clicks.toLocaleString() + '</td>' +
                    '<td class="py-3 pr-4 text-right">' + (d.ctr !== null ? d.ctr + '%' : '—') + '</td>' +
                    '<td class="py-3 text-right ' + pctClass + '">' + pctText + '</td>' +
                    '</tr>';
            }).join('');
        });

    function he(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>
@endpush
