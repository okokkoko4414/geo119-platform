@extends('layouts.app')

@section('content')
<div class="page-container py-8">
    <h1 class="text-2xl font-bold mb-2 text-surface-900">{{ __('ui.results.title') }}</h1>
    <p class="text-sm text-surface-500 mb-8">
        {{ $results->total() }} total optimization results across all languages.
    </p>

    <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="border-b border-surface-200 bg-surface-50">
                    <tr>
                        <th class="py-3 px-4 font-medium text-surface-500">{{ __('ui.dashboard.header_type') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500">{{ __('ui.dashboard.header_locale') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_before') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_after') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_improvement') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500 text-right">{{ __('ui.optimization.cost') }}</th>
                        <th class="py-3 px-4 font-medium text-surface-500 text-right">{{ __('ui.dashboard.header_time') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($results as $result)
                    <tr class="border-b border-surface-100 hover:bg-surface-50 cursor-pointer transition-colors"
                        onclick="window.location='{{ route('optimizations.show', ['id' => $result->id, 'locale' => $locale]) }}'">
                        <td class="py-3 px-4 font-medium text-surface-900">{{ $result->optimization_type }}</td>
                        <td class="py-3 px-4 text-surface-600">{{ $result->target_locale }}</td>
                        <td class="py-3 px-4 text-right text-surface-600">{{ number_format($result->before_score * 100, 1) }}%</td>
                        <td class="py-3 px-4 text-right text-surface-600">{{ number_format($result->after_score * 100, 1) }}%</td>
                        <td class="py-3 px-4 text-right {{ $result->improvement >= 0 ? 'text-green-600' : 'text-red-500' }}">
                            {{ $result->improvement >= 0 ? '+' : '' }}{{ number_format($result->improvement * 100, 1) }}%
                        </td>
                        <td class="py-3 px-4 text-right text-surface-600">${{ number_format($result->cost_cents / 100, 4) }}</td>
                        <td class="py-3 px-4 text-right text-surface-400 text-xs whitespace-nowrap">{{ $result->created_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center text-surface-400">
                            <p class="text-lg font-medium">{{ __('ui.results.empty') }}</p>
                            <p class="text-sm mt-1">{{ __('ui.results.empty_hint') }}</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($results->hasPages())
        <div class="border-t border-surface-200 px-4 py-3 flex items-center justify-between">
            <p class="text-sm text-surface-500">
                Showing {{ $results->firstItem() }}–{{ $results->lastItem() }} of {{ $results->total() }}
            </p>
            <div class="flex gap-2">
                @if ($results->onFirstPage())
                <span class="px-3 py-1.5 text-sm text-surface-300 rounded-lg bg-surface-100 cursor-not-allowed">
                    {{ __('ui.pagination.previous') }}
                </span>
                @else
                <a href="{{ $results->previousPageUrl() }}"
                    class="px-3 py-1.5 text-sm text-surface-600 hover:text-surface-900 rounded-lg hover:bg-surface-100 transition-colors">
                    {{ __('ui.pagination.previous') }}
                </a>
                @endif

                @if ($results->hasMorePages())
                <a href="{{ $results->nextPageUrl() }}"
                    class="px-3 py-1.5 text-sm text-surface-600 hover:text-surface-900 rounded-lg hover:bg-surface-100 transition-colors">
                    {{ __('ui.pagination.next') }}
                </a>
                @else
                <span class="px-3 py-1.5 text-sm text-surface-300 rounded-lg bg-surface-100 cursor-not-allowed">
                    {{ __('ui.pagination.next') }}
                </span>
                @endif
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
