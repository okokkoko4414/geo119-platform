<?php
/**
 * WordPress header template — used by all theme pages.
 */

declare(strict_types=1);

$locale = function_exists('geo119_detect_locale') ? geo119_detect_locale() : 'en';
$dir = in_array(substr($locale, 0, 2), ['ar', 'he', 'fa']) ? 'rtl' : 'ltr';
$supported = defined('GEO119_SUPPORTED_LOCALES') ? GEO119_SUPPORTED_LOCALES : ['en', 'vi'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr($dir); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('min-h-screen flex flex-col bg-surface-50 text-surface-900 antialiased'); ?>>
<?php wp_body_open(); ?>

<header class="border-b border-surface-200 bg-white sticky top-0 z-50">
    <div class="page-container">
        <nav class="flex h-16 items-center justify-between" aria-label="<?php esc_attr_e('Primary Navigation', 'geo119'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="text-xl font-bold text-primary-700 hover:text-primary-800 transition-colors">
                GEO119
            </a>

            <div class="hidden md:flex items-center gap-6">
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container' => false,
                    'items_wrap' => '<ul class="flex items-center gap-6">%3$s</ul>',
                    'fallback_cb' => false,
                ]);
?>
            </div>

            <div class="flex items-center gap-4">
                <!-- Language Switcher -->
                <div class="relative">
                    <label for="lang-switcher-header" class="sr-only"><?php _e('Language', 'geo119'); ?></label>
                    <select id="lang-switcher-header" data-language-switcher
                            class="appearance-none rounded-md border border-surface-300 bg-white py-1.5 pl-3 pr-8 text-sm text-surface-700 focus-ring cursor-pointer">
                        <?php foreach ($supported as $code) { ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($locale, $code); ?>>
                                <?php echo esc_html(geo119_language_display_name($code)); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <svg class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 h-4 w-4 text-surface-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>

                <!-- Mobile menu toggle -->
                <button data-mobile-menu-toggle aria-expanded="false"
                        class="md:hidden inline-flex items-center justify-center rounded-md p-2 text-surface-600 hover:bg-surface-100 focus-ring"
                        aria-label="Toggle menu">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>
        </nav>

        <!-- Mobile menu (hidden by default) -->
        <div data-mobile-menu class="hidden md:hidden border-t border-surface-200 pb-4">
            <?php
            wp_nav_menu([
'theme_location' => 'primary',
'container' => false,
'menu_class' => 'flex flex-col gap-2 pt-4',
'fallback_cb' => false,
            ]);
?>
        </div>
    </div>
</header>
