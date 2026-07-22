@props([
    'padding' => 'md',
    'class' => '',
])

@php
$paddings = [
    'none' => '',
    'sm' => 'p-4',
    'md' => 'p-6',
    'lg' => 'p-8',
];
$paddingClass = $paddings[$padding] ?? $paddings['md'];
@endphp

<div {{ $attributes->merge(['class' => "card-base $paddingClass $class"]) }}>
    {{ $slot }}
</div>
