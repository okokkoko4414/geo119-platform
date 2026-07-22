<!DOCTYPE html>
<html lang="{{ $locale ?? app()->getLocale() }}" dir="{{ in_array($locale ?? 'en', ['ar', 'he', 'fa']) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {!! app(App\Services\Seo\MetaBuilder::class)->render($meta ?? [], $locale ?? app()->getLocale()) !!}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen flex flex-col">
    <header class="border-b border-surface-200 bg-white">
        <div class="page-container">
            <nav class="flex h-16 items-center justify-between" aria-label="Main navigation">
                <a href="/{{ $locale !== 'en' ? $locale : '' }}" class="flex items-center gap-2 text-xl font-bold text-primary-700">
                    @include('components.logo')
                </a>

                <div class="hidden md:flex items-center gap-6">
                    <a href="/{{ $locale !== 'en' ? $locale . '/' : '' }}" class="text-sm text-surface-600 hover:text-surface-900">
                        @include('components.icon', ['name' => 'home', 'class' => 'w-4 h-4 inline-block mr-1'])
                        {{ __('ui.nav.home') }}
                    </a>
                    <a href="{{ route('component-gallery', ['locale' => $locale !== 'en' ? $locale : null]) }}" class="text-sm text-surface-600 hover:text-surface-900">
                        @include('components.icon', ['name' => 'grid', 'class' => 'w-4 h-4 inline-block mr-1'])
                        {{ __('ui.nav.component_gallery') }}
                    </a>
                    <a href="{{ route('payment.show', ['locale' => $locale !== 'en' ? $locale : null]) }}" class="text-sm text-surface-600 hover:text-surface-900">
                        @include('components.icon', ['name' => 'credit-card', 'class' => 'w-4 h-4 inline-block mr-1'])
                        {{ __('ui.nav.payment') }}
                    </a>
                    @include('components.language-switcher')
                </div>

                <button data-mobile-menu-toggle aria-expanded="false" class="md:hidden p-2 text-surface-600 focus-ring rounded">
                    @include('components.icon', ['name' => 'menu', 'class' => 'w-6 h-6'])
                </button>
            </nav>

            <div data-mobile-menu class="hidden md:hidden pb-4">
                <div class="flex flex-col gap-3 pt-2">
                    <a href="/{{ $locale !== 'en' ? $locale . '/' : '' }}" class="text-sm text-surface-600 hover:text-surface-900 py-2">
                        {{ __('ui.nav.home') }}
                    </a>
                    <a href="{{ route('component-gallery', ['locale' => $locale !== 'en' ? $locale : null]) }}" class="text-sm text-surface-600 hover:text-surface-900 py-2">
                        {{ __('ui.nav.component_gallery') }}
                    </a>
                    <a href="{{ route('payment.show', ['locale' => $locale !== 'en' ? $locale : null]) }}" class="text-sm text-surface-600 hover:text-surface-900 py-2">
                        {{ __('ui.nav.payment') }}
                    </a>
                    @include('components.language-switcher')
                </div>
            </div>
        </div>
    </header>

    <main class="flex-1">
        @yield('content')
    </main>

    <footer class="border-t border-surface-200 bg-white py-8">
        <div class="page-container flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-surface-500">
            <p>{{ __('ui.footer.copyright') }}</p>
            <div class="flex gap-4">
                <a href="#" class="hover:text-surface-700">{{ __('ui.footer.privacy') }}</a>
                <a href="#" class="hover:text-surface-700">{{ __('ui.footer.terms') }}</a>
                <a href="#" class="hover:text-surface-700">{{ __('ui.footer.contact') }}</a>
            </div>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
