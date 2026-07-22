@extends('layouts.app')

@section('content')
<section class="section">
    <div class="page-container">
        <div class="mb-12">
            <h1 class="text-3xl sm:text-4xl font-bold text-surface-900">{{ __('ui.component_gallery.title') }}</h1>
            <p class="mt-3 text-lg text-surface-600">{{ __('ui.component_gallery.subtitle') }}</p>
        </div>

        <section class="mb-16" aria-labelledby="section-buttons">
            <h2 id="section-buttons" class="text-xl font-semibold text-surface-900 mb-6">{{ __('ui.gallery.section_buttons') }}</h2>
            <x-card>
                <div class="flex flex-wrap gap-3 items-end">
                    <x-button variant="primary" size="sm">{{ __('ui.gallery.variant_small', ['variant' => __('ui.gallery.variant_primary')]) }}</x-button>
                    <x-button variant="primary">{{ __('ui.gallery.variant_medium', ['variant' => __('ui.gallery.variant_primary')]) }}</x-button>
                    <x-button variant="primary" size="lg">{{ __('ui.gallery.variant_large', ['variant' => __('ui.gallery.variant_primary')]) }}</x-button>
                    <x-button variant="secondary">{{ __('ui.gallery.variant_secondary') }}</x-button>
                    <x-button variant="danger">{{ __('ui.gallery.variant_danger') }}</x-button>
                    <x-button variant="ghost">{{ __('ui.gallery.variant_ghost') }}</x-button>
                    <x-button variant="primary" loading>{{ __('ui.gallery.variant_loading') }}</x-button>
                    <x-button variant="primary" disabled>{{ __('ui.gallery.variant_disabled') }}</x-button>
                </div>
            </x-card>
        </section>

        <section class="mb-16" aria-labelledby="section-badges">
            <h2 id="section-badges" class="text-xl font-semibold text-surface-900 mb-6">{{ __('ui.gallery.section_badges') }}</h2>
            <x-card>
                <div class="flex flex-wrap gap-3 items-center">
                    <x-badge variant="default">{{ __('ui.gallery.variant_default') }}</x-badge>
                    <x-badge variant="primary">{{ __('ui.gallery.variant_primary') }}</x-badge>
                    <x-badge variant="success">{{ __('ui.gallery.variant_success') }}</x-badge>
                    <x-badge variant="warning">{{ __('ui.gallery.variant_warning') }}</x-badge>
                    <x-badge variant="danger">{{ __('ui.gallery.variant_danger') }}</x-badge>
                    <x-badge size="lg">{{ __('ui.gallery.large_badge') }}</x-badge>
                </div>
            </x-card>
        </section>

        <section class="mb-16" aria-labelledby="section-inputs">
            <h2 id="section-inputs" class="text-xl font-semibold text-surface-900 mb-6">{{ __('ui.gallery.section_inputs') }}</h2>
            <div class="grid sm:grid-cols-2 gap-6">
                <x-card>
                    <x-input name="demo-text" label="{{ __('ui.gallery.input_label') }}" placeholder="{{ __('ui.gallery.input_placeholder') }}" required />
                </x-card>
                <x-card>
                    <x-input name="demo-error" label="With Error" value="bad value" error="{{ __('ui.gallery.input_error') }}" />
                </x-card>
                <x-card>
                    <x-select name="demo-select" label="{{ __('ui.gallery.select_label') }}" :options="['option1' => 'Option 1', 'option2' => 'Option 2', 'option3' => 'Option 3']" placeholder="{{ __('ui.gallery.select_placeholder') }}" />
                </x-card>
                <x-card>
                    <x-input name="demo-disabled" label="{{ __('ui.gallery.input_disabled_label') }}" value="{{ __('ui.gallery.input_disabled_value') }}" disabled helper="{{ __('ui.gallery.input_disabled_helper') }}" />
                </x-card>
            </div>
        </section>

        <section class="mb-16" aria-labelledby="section-table">
            <h2 id="section-table" class="text-xl font-semibold text-surface-900 mb-6">{{ __('ui.gallery.section_table') }}</h2>
            <x-table
                :headers="['Name', 'Status', 'Role', 'Joined']"
                :rows="[
                    ['Alice Chen', '<x-badge variant=&quot;success&quot;>Active</x-badge>', 'Admin', '2026-01-15'],
                    ['Bob Nguyen', '<x-badge variant=&quot;primary&quot;>Active</x-badge>', 'Editor', '2026-03-22'],
                    ['Carol Tran', '<x-badge variant=&quot;warning&quot;>Pending</x-badge>', 'Viewer', '2026-06-01'],
                ]"
            />
        </section>

        <section class="mb-16" aria-labelledby="section-modal">
            <h2 id="section-modal" class="text-xl font-semibold text-surface-900 mb-6">{{ __('ui.gallery.section_modal') }}</h2>
            <x-card>
                <x-button data-modal-open="demo-modal" variant="primary">{{ __('ui.modal.confirm_title') }}</x-button>
            </x-card>
            <x-modal id="demo-modal" title="{{ __('ui.modal.confirm_title') }}">
                <p class="text-surface-600">{{ __('ui.modal.confirm_message') }}</p>
                <x-slot name="footer">
                    <x-button data-modal-close variant="secondary">{{ __('ui.button.cancel') }}</x-button>
                    <x-button variant="primary">{{ __('ui.button.confirm') }}</x-button>
                </x-slot>
            </x-modal>
        </section>
    </div>
</section>
@stop
