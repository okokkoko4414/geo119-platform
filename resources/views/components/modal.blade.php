@props([
    'id' => 'modal-' . bin2hex(random_bytes(4)),
    'title' => '',
    'showClose' => true,
    'size' => 'md',
])

@php
$sizes = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-lg',
    'lg' => 'max-w-2xl',
    'xl' => 'max-w-4xl',
];
$sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<dialog id="{{ $id }}" {{ $attributes->merge(['class' => "$sizeClass w-full rounded-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm open:flex open:flex-col p-0 border-0 shadow-xl"]) }} aria-labelledby="{{ $id }}-title">
    <div class="flex items-center justify-between border-b border-surface-200 px-6 py-4">
        <h2 id="{{ $id }}-title" class="text-lg font-semibold text-surface-900">{{ $title }}</h2>
        @if ($showClose)
            <button data-modal-close class="p-1 text-surface-400 hover:text-surface-600 focus-ring rounded" aria-label="{{ __('ui.button.close') }}">
                @include('components.icon', ['name' => 'x', 'class' => 'w-5 h-5'])
            </button>
        @endif
    </div>
    <div class="overflow-y-auto px-6 py-4">
        {{ $slot }}
    </div>
    @if (isset($footer))
        <div class="border-t border-surface-200 px-6 py-4 flex justify-end gap-3">
            {{ $footer }}
        </div>
    @endif
</dialog>
