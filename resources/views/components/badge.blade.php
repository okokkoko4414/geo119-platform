@props([
    'variant' => 'default',
    'size' => 'md',
    'class' => '',
])

@php
$variants = [
    'default' => 'bg-surface-100 text-surface-600',
    'primary' => 'bg-primary-100 text-primary-700',
    'success' => 'bg-accent-100 text-accent-700',
    'warning' => 'bg-yellow-100 text-yellow-700',
    'danger' => 'bg-red-100 text-red-700',
];
$sizes = [
    'sm' => 'px-2 py-0.5 text-xs',
    'md' => 'px-2.5 py-1 text-xs',
    'lg' => 'px-3 py-1.5 text-sm',
];
$variantClass = $variants[$variant] ?? $variants['default'];
$sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center font-medium rounded-full $variantClass $sizeClass $class"]) }}>
    {{ $slot }}
</span>
