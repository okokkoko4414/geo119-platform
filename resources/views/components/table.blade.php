@props([
    'headers' => [],
    'rows' => [],
    'emptyText' => '',
    'loading' => false,
    'class' => '',
])

@php
$emptyText = $emptyText ?: __('ui.table.no_results');
@endphp

<div class="overflow-x-auto rounded-lg border border-surface-200 {{ $class }}">
    <table class="min-w-full divide-y divide-surface-200 text-sm">
        @if ($headers)
            <thead class="bg-surface-50">
                <tr>
                    @foreach ($headers as $header)
                        <th scope="col" class="px-4 py-3 text-left font-semibold text-surface-600">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="divide-y divide-surface-200 bg-white">
            @if ($loading)
                <tr>
                    <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-12 text-center text-surface-500">
                        <div class="flex items-center justify-center gap-2">
                            <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                            </svg>
                            {{ __('ui.table.loading') }}
                        </div>
                    </td>
                </tr>
            @elseif (empty($rows))
                <tr>
                    <td colspan="{{ count($headers) ?: 1 }}" class="px-4 py-12 text-center text-surface-500">
                        {{ $emptyText }}
                    </td>
                </tr>
            @else
                @foreach ($rows as $row)
                    <tr class="hover:bg-surface-50 transition-colors">
                        @foreach ($row as $cell)
                            <td class="px-4 py-3 text-surface-700">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
