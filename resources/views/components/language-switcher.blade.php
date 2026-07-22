@php
$currentLocale = $locale ?? app()->getLocale();
$available = $availableLocales ?? app(App\Services\I18n\LocaleDetector::class)->availableLocales();
$currentUrl = request()->path();
@endphp

<form method="POST" action="{{ route('language.switch') }}" class="inline-flex items-center" data-language-switcher>
    @csrf
    <input type="hidden" name="redirect_to" value="/{{ Str::after($currentUrl, $currentLocale . '/') }}">
    <div class="relative">
        <select name="locale" aria-label="{{ __('ui.language.switch') }}" class="appearance-none rounded-md border border-surface-200 bg-white py-1.5 pl-3 pr-8 text-xs text-surface-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            @foreach ($available as $loc)
                <option value="{{ $loc }}" {{ $loc === $currentLocale ? 'selected' : '' }}>
                    {{ __("ui.language.{$loc}", [], $loc) }}
                </option>
            @endforeach
        </select>
        @include('components.icon', ['name' => 'chevron-down', 'class' => 'w-3 h-3 absolute right-2 top-1/2 -translate-y-1/2 pointer-events-none text-surface-400'])
    </div>
</form>
