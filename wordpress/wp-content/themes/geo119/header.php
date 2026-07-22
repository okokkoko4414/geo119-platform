<?php
/**
 * WordPress header template — used by all theme pages.
 */

declare(strict_types=1);

$locale = determine_locale();
$dir = in_array(substr($locale, 0, 2), ['ar', 'he', 'fa']) ? 'rtl' : 'ltr';
?><!DOCTYPE html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr($dir); ?>">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('min-h-screen flex flex-col bg-surface-50 text-surface-900 antialiased'); ?>>
<?php wp_body_open(); ?>

<header class="border-b border-surface-200 bg-white">
    <div class="page-container">
        <nav class="flex h-16 items-center justify-between" aria-label="<?php esc_attr_e('Primary Navigation', 'geo119'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="text-xl font-bold text-primary-700">
                <?php bloginfo('name'); ?>
            </a>
            <?php
            wp_nav_menu([
                'theme_location' => 'primary',
                'container_class' => 'hidden md:flex items-center gap-6',
                'fallback_cb' => false,
            ]);
?>
        </nav>
    </div>
</header>
