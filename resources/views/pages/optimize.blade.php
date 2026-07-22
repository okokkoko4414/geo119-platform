@extends('layouts.app')

@section('content')
<div class="page-container py-8">
    <h1 class="text-2xl font-bold mb-2 text-surface-900">{{ __('ui.optimize.title') }}</h1>
    <p class="text-sm text-surface-500 mb-8">{{ __('ui.optimize.subtitle') }}</p>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 p-6 max-w-2xl">
        <form id="batch-optimize-form" method="POST" action="/api/v1/batch/optimize">
            <div class="mb-6">
                <label for="target_locale" class="block text-sm font-medium text-surface-700 mb-2">
                    {{ __('ui.optimize.locale_label') }}
                </label>
                <select id="target_locale" name="target_locale" required
                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100">
                    <option value="">{{ __('ui.optimize.locale_placeholder') }}</option>
                    @foreach ($languages as $lang)
                    <option value="{{ $lang['code'] }}">
                        {{ $lang['name'] }} ({{ $lang['code'] }}) — Tier {{ $lang['tier'] }}
                    </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-6">
                <label for="source_urls" class="block text-sm font-medium text-surface-700 mb-2">
                    {{ __('ui.optimize.url_label') }}
                </label>
                <textarea id="source_urls" name="source_urls" rows="8" required
                    class="w-full rounded-lg border border-surface-300 px-4 py-2.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-100 font-mono"
                    placeholder="{{ __('ui.optimize.url_placeholder') }}"></textarea>
                <p class="mt-1 text-xs text-surface-400">{{ __('ui.optimize.url_hint') }}</p>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-300 transition-colors">
                    @include('components.icon', ['name' => 'check', 'class' => 'w-4 h-4'])
                    {{ __('ui.optimize.submit') }}
                </button>
                <button type="reset"
                    class="text-sm text-surface-500 hover:text-surface-700 transition-colors">
                    {{ __('ui.button.cancel') }}
                </button>
            </div>

            <div id="form-status" class="mt-4 hidden"></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.getElementById('batch-optimize-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    var status = document.getElementById('form-status');
    var btn = this.querySelector('button[type="submit"]');
    var origHtml = btn.innerHTML;

    var urls = document.getElementById('source_urls').value.trim().split('\n').filter(Boolean);
    var locale = document.getElementById('target_locale').value;

    if (!locale || urls.length === 0) {
        status.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm';
        status.textContent = 'Please select a target locale and enter at least one URL.';
        status.classList.remove('hidden');
        return;
    }

    var items = urls.map(function(url) {
        return {
            source_text: url.trim(),
            target_locale: locale,
            optimization_type: 'full'
        };
    });

    btn.disabled = true;
    btn.innerHTML = 'Loading...';
    status.classList.add('hidden');

    try {
        var res = await fetch('/api/v1/batch/optimize', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ items: items })
        });

        var data = await res.json();

        if (res.ok) {
            status.className = 'mt-4 p-3 rounded-lg bg-green-50 text-green-700 text-sm';
            status.innerHTML = data.job_id
                ? 'Batch submitted. Job ID: ' + data.job_id + '. Track progress on the Results page.'
                : 'Optimization completed. View results below.';
        } else {
            status.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm';
            status.textContent = data.message || data.error || 'Optimization request failed.';
        }
    } catch (err) {
        status.className = 'mt-4 p-3 rounded-lg bg-red-50 text-red-700 text-sm';
        status.textContent = 'Optimization request failed. Please try again.';
    } finally {
        btn.disabled = false;
        btn.innerHTML = origHtml;
    }

    status.classList.remove('hidden');
});
</script>
@endpush
