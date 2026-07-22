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
define('GEO119_DEFAULT_LOCALE', 'en');
define('GEO119_SUPPORTED_LOCALES', [
    // Tier 1 (30): Premium
    'en', 'zh', 'es', 'ar', 'pt', 'ru', 'fr', 'de', 'ja', 'ko',
    'it', 'nl', 'pl', 'sv', 'da', 'fi', 'nb', 'cs', 'el', 'hu',
    'ro', 'sk', 'uk', 'he', 'tr', 'vi', 'th', 'id', 'ms', 'fil',
    // Tier 2 (35): Beta
    'hi', 'bn', 'ta', 'te', 'mr', 'gu', 'kn', 'ml', 'pa', 'ur',
    'fa', 'sw', 'am', 'ha', 'yo', 'ig', 'zu', 'af', 'bg', 'hr',
    'et', 'lt', 'lv', 'sl', 'sr', 'is', 'mk', 'sq', 'ka', 'mn',
    'ne', 'si', 'kk', 'uz', 'az',
    // Tier 3 (5): Community
    'lo', 'km', 'my', 'ps', 'ti',
]);

define('GEO119_LANGUAGE_NAMES', [
    'en' => 'English',              'zh' => 'Chinese',
    'es' => 'Spanish',              'ar' => 'Arabic',
    'pt' => 'Portuguese',           'ru' => 'Russian',
    'fr' => 'French',               'de' => 'German',
    'ja' => 'Japanese',             'ko' => 'Korean',
    'it' => 'Italian',              'nl' => 'Dutch',
    'pl' => 'Polish',               'sv' => 'Swedish',
    'da' => 'Danish',               'fi' => 'Finnish',
    'nb' => 'Norwegian',            'cs' => 'Czech',
    'el' => 'Greek',                'hu' => 'Hungarian',
    'ro' => 'Romanian',             'sk' => 'Slovak',
    'uk' => 'Ukrainian',            'he' => 'Hebrew',
    'tr' => 'Turkish',              'vi' => 'Vietnamese',
    'th' => 'Thai',                 'id' => 'Indonesian',
    'ms' => 'Malay',                'fil' => 'Filipino',
    'hi' => 'Hindi',                'bn' => 'Bengali',
    'ta' => 'Tamil',                'te' => 'Telugu',
    'mr' => 'Marathi',              'gu' => 'Gujarati',
    'kn' => 'Kannada',              'ml' => 'Malayalam',
    'pa' => 'Punjabi',              'ur' => 'Urdu',
    'fa' => 'Persian',              'sw' => 'Swahili',
    'am' => 'Amharic',              'ha' => 'Hausa',
    'yo' => 'Yoruba',               'ig' => 'Igbo',
    'zu' => 'Zulu',                 'af' => 'Afrikaans',
    'bg' => 'Bulgarian',            'hr' => 'Croatian',
    'et' => 'Estonian',             'lt' => 'Lithuanian',
    'lv' => 'Latvian',              'sl' => 'Slovenian',
    'sr' => 'Serbian',              'is' => 'Icelandic',
    'mk' => 'Macedonian',           'sq' => 'Albanian',
    'ka' => 'Georgian',             'mn' => 'Mongolian',
    'ne' => 'Nepali',               'si' => 'Sinhala',
    'kk' => 'Kazakh',               'uz' => 'Uzbek',
    'az' => 'Azerbaijani',          'lo' => 'Lao',
    'km' => 'Khmer',                'my' => 'Burmese',
    'ps' => 'Pashto',               'ti' => 'Tigrinya',
]);

/**
 * Detect current locale from URL, cookie, or Accept-Language header.
 */
function geo119_detect_locale(): string
{
    // 1. URL segment: /vi/... -> vi
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if (preg_match('#^/([a-z]{2})(?:/|$)#', $request_uri, $m)) {
        if (in_array($m[1], GEO119_SUPPORTED_LOCALES, true)) {
            return $m[1];
        }
    }

    // 2. Cookie
    if (! empty($_COOKIE['geo119_locale'])) {
        $cookie_locale = substr((string) $_COOKIE['geo119_locale'], 0, 2);
        if (in_array($cookie_locale, GEO119_SUPPORTED_LOCALES, true)) {
            return $cookie_locale;
        }
    }

    // 3. Accept-Language header — match first supported locale
    if (! empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $locales = explode(',', (string) $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach ($locales as $locale_str) {
            $part = trim(strtok($locale_str, ';'));
            $lang = substr($part, 0, 2);
            if (in_array($lang, GEO119_SUPPORTED_LOCALES, true)) {
                return $lang;
            }
        }
    }

    // 4. Default
    return GEO119_DEFAULT_LOCALE;
}

/**
 * Filter WordPress locale based on detection.
 */
function geo119_filter_locale(string $locale): string
{
    if (is_admin()) {
        return $locale;
    }

    $detected = geo119_detect_locale();

    if (in_array($detected, GEO119_SUPPORTED_LOCALES, true)) {
        return $detected;
    }

    return GEO119_DEFAULT_LOCALE;
}

/**
 * Return the native name for a supported locale code.
 */
function geo119_language_display_name(string $code): string
{
    return GEO119_LANGUAGE_NAMES[$code] ?? $code;
}
add_filter('locale', 'geo119_filter_locale');

/**
 * Set the locale cookie after detection.
 */
function geo119_set_locale_cookie(): void
{
    $detected = geo119_detect_locale();
    if (empty($_COOKIE['geo119_locale']) || $_COOKIE['geo119_locale'] !== $detected) {
        setcookie('geo119_locale', $detected, [
            'expires' => time() + DAY_IN_SECONDS * 365,
            'path' => '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
add_action('init', 'geo119_set_locale_cookie');

/**
 * Add rewrite rules for locale-prefixed URLs (/vi/...).
 */
function geo119_add_rewrite_rules(): void
{
    $locale_pattern = implode('|', GEO119_SUPPORTED_LOCALES);
    add_rewrite_rule(
        '^('.$locale_pattern.')/(.+?)/?$',
        'index.php?geo119_locale=$matches[1]&pagename=$matches[2]',
        'top'
    );
    add_rewrite_rule(
        '^('.$locale_pattern.')/?$',
        'index.php?geo119_locale=$matches[1]',
        'top'
    );
}
add_action('init', 'geo119_add_rewrite_rules');

/**
 * Register geo119_locale as a public query var.
 */
function geo119_query_vars(array $vars): array
{
    $vars[] = 'geo119_locale';

    return $vars;
}
add_filter('query_vars', 'geo119_query_vars');

/**
 * Home URL filter: strip locale prefix for clean canonical URLs.
 */
function geo119_home_url(string $url, string $path, ?string $scheme): string
{
    $detected = geo119_detect_locale();
    if ($detected === GEO119_DEFAULT_LOCALE) {
        return $url;
    }

    return rtrim($url, '/').'/'.$detected;
}
add_filter('home_url', 'geo119_home_url', 10, 3);

/**
 * Reload theme textdomain after locale detection (init fires after locale is set).
 * load_theme_textdomain inside after_setup_theme uses en_US locale; this ensures
 * the correct .mo file is loaded when /vi/ route or Accept-Language: vi is detected.
 */
function geo119_reload_textdomain(): void
{
    $locale = get_locale();
    $mo_file = GEO119_THEME_DIR.'/languages/geo119-'.$locale.'.mo';
    if (is_readable($mo_file)) {
        unload_textdomain('geo119');
        load_textdomain('geo119', $mo_file);
    }
}
add_action('init', 'geo119_reload_textdomain', 1);

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

    wp_localize_script('geo119-app', 'geo119Data', [
        'locale' => geo119_detect_locale(),
        'supportedLocales' => GEO119_SUPPORTED_LOCALES,
        'localeNames' => GEO119_LANGUAGE_NAMES,
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
            'description' => 'Content locale (ISO 639-1)',
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
