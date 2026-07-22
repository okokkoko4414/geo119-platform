@props([
    'name' => '',
    'label' => '',
    'options' => [],
    'value' => '',
    'required' => false,
    'disabled' => false,
    'error' => '',
    'placeholder' => '',
    'class' => '',
])

<div class="{{ $class }}">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-surface-700 mb-1">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-label="{{ __('ui.form.required') }}">*</span>
            @endif
        </label>
    @endif
    <select
        name="{{ $name }}"
        id="{{ $name }}"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0' . ($error ? ' border-red-300 focus:ring-red-500' : ' border-surface-300 focus:ring-primary-500') . ($disabled ? ' bg-surface-100 text-surface-500 cursor-not-allowed' : ' bg-white text-surface-900')]) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" {{ (string) $optValue === (string) old($name, $value) ? 'selected' : '' }}>
                {{ $optLabel }}
            </option>
        @endforeach
    </select>
    @if ($error)
        <p class="mt-1 text-xs text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
