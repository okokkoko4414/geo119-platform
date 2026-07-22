@props([
    'name' => '',
    'type' => 'text',
    'label' => '',
    'placeholder' => '',
    'value' => '',
    'required' => false,
    'disabled' => false,
    'error' => '',
    'helper' => '',
    'class' => '',
])

<div class="{{ $class }}">
    @if ($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-surface-700 mb-1">
            {{ $label }}
            @if ($required)
                <span class="text-red-500" aria-label="{{ __('ui.form.required') }}">*</span>
            @else
                <span class="text-surface-400 text-xs">({{ __('ui.form.optional') }})</span>
            @endif
        </label>
    @endif
    <input
        type="{{ $type }}"
        name="{{ $name }}"
        id="{{ $name }}"
        value="{{ old($name, $value) }}"
        placeholder="{{ $placeholder }}"
        {{ $required ? 'required' : '' }}
        {{ $disabled ? 'disabled' : '' }}
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border px-3 py-2 text-sm shadow-sm placeholder:text-surface-400 focus:outline-none focus:ring-2 focus:ring-offset-0' . ($error ? ' border-red-300 focus:ring-red-500' : ' border-surface-300 focus:ring-primary-500') . ($disabled ? ' bg-surface-100 text-surface-500 cursor-not-allowed' : ' bg-white text-surface-900')]) }}
    />
    @if ($helper && ! $error)
        <p class="mt-1 text-xs text-surface-500">{{ $helper }}</p>
    @endif
    @if ($error)
        <p class="mt-1 text-xs text-red-600" role="alert">{{ $error }}</p>
    @endif
</div>
