<?php

/**
 * Theme Name: GEO119
 * Theme URI: https://geo119.com
 * Description: GEO119 custom WordPress theme with Blade template engine, Tailwind CSS, and i18n support. English default, Vietnamese locale.
 * Version: 1.0.0
 * Author: GEO119 Team
 * Text Domain: geo119
 * Domain Path: /languages
 * Requires PHP: 8.4
 * Requires WP: 6.0
 */

declare(strict_types=1);

// Prevent direct access
if (! defined('ABSPATH')) {
    exit;
}

define('GEO119_THEME_VERSION', '1.0.0');
define('GEO119_THEME_DIR', get_template_directory());
define('GEO119_THEME_URL', get_template_directory_uri());

/**
 * Theme setup: i18n, theme supports, nav menus.
 */
function geo119_theme_setup(): void
{
    load_theme_textdomain('geo119', GEO119_THEME_DIR.'/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
    add_theme_support('custom-logo');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');

    register_nav_menus([
        'primary' => __('Primary Navigation', 'geo119'),
        'footer' => __('Footer Navigation', 'geo119'),
    ]);
}
add_action('after_setup_theme', 'geo119_theme_setup');

/**
 * Enqueue theme styles and scripts.
 */
function geo119_enqueue_assets(): void
{
    // Tailwind compiled CSS from Laravel Vite build
    wp_enqueue_style(
        'geo119-tailwind',
        GEO119_THEME_URL.'/assets/css/tailwind.css',
        [],
        GEO119_THEME_VERSION
    );

    wp_enqueue_script(
        'geo119-app',
        GEO119_THEME_URL.'/assets/js/app.js',
        [],
        GEO119_THEME_VERSION,
        true
    );

    // Pass locale data to JS
    wp_localize_script('geo119-app', 'geo119Data', [
        'locale' => determine_locale(),
        'restUrl' => rest_url('wp/v2/'),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
}
add_action('wp_enqueue_scripts', 'geo119_enqueue_assets');

/**
 * Register REST API fields for localized content.
 */
function geo119_register_rest_fields(): void
{
    register_rest_field('page', 'locale', [
        'get_callback' => fn (array $object): string => get_post_meta($object['id'], '_geo119_locale', true) ?: 'en',
        'schema' => [
            'type' => 'string',
            'description' => 'Content locale (en, vi)',
        ],
    ]);

    register_rest_field('page', 'translation_id', [
        'get_callback' => fn (array $object): int => (int) get_post_meta($object['id'], '_geo119_translation_id', true),
        'schema' => [
            'type' => 'integer',
            'description' => 'Parent page ID for translation grouping',
        ],
    ]);
}
add_action('rest_api_init', 'geo119_register_rest_fields');

/**
 * Add CORS headers for REST API.
 */
function geo119_rest_cors(): void
{
    header('Access-Control-Allow-Origin: '.home_url());
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Credentials: true');
}
add_action('rest_api_init', 'geo119_rest_cors');

/**
 * Force UTF-8 charset.
 */
function geo119_force_utf8(): void
{
    header('Content-Type: text/html; charset=UTF-8');
}
add_action('wp_head', 'geo119_force_utf8', 0);
