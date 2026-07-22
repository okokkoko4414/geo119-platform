<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Language Registry — 70 Languages Across 3 Quality Tiers
    |--------------------------------------------------------------------------
    |
    | Tier 1 (30): Full DeepSeek + auto QA, COMET >= 0.85
    | Tier 2 (35): DeepSeek + term validation + English fallback
    | Tier 3 (5):  Machine translation + English fallback annotation
    |
    | Structure: [code, name, native_name, tier, fallback_locale]
    |
    */

    'tiers' => [
        1 => ['threshold' => 0.85, 'label' => 'Premium'],
        2 => ['threshold' => 0.68, 'label' => 'Beta'],
        3 => ['threshold' => 0.70, 'label' => 'Community'],
    ],

    'languages' => [
        // ── Tier 1: High Resource (30 languages) ─────────────────────
        // Existing 25 + 5 new
        ['code' => 'en', 'name' => 'English',             'native_name' => 'English',              'tier' => 1],
        ['code' => 'zh', 'name' => 'Chinese (Simplified)', 'native_name' => '简体中文',             'tier' => 1],
        ['code' => 'es', 'name' => 'Spanish',              'native_name' => 'Español',              'tier' => 1],
        ['code' => 'ar', 'name' => 'Arabic',               'native_name' => 'العربية',             'tier' => 1],
        ['code' => 'pt', 'name' => 'Portuguese',           'native_name' => 'Português',            'tier' => 1],
        ['code' => 'ru', 'name' => 'Russian',              'native_name' => 'Русский',              'tier' => 1],
        ['code' => 'fr', 'name' => 'French',               'native_name' => 'Français',             'tier' => 1],
        ['code' => 'de', 'name' => 'German',               'native_name' => 'Deutsch',              'tier' => 1],
        ['code' => 'ja', 'name' => 'Japanese',             'native_name' => '日本語',               'tier' => 1],
        ['code' => 'ko', 'name' => 'Korean',               'native_name' => '한국어',               'tier' => 1],
        ['code' => 'it', 'name' => 'Italian',              'native_name' => 'Italiano',             'tier' => 1],
        ['code' => 'nl', 'name' => 'Dutch',                'native_name' => 'Nederlands',           'tier' => 1],
        ['code' => 'pl', 'name' => 'Polish',               'native_name' => 'Polski',               'tier' => 1],
        ['code' => 'sv', 'name' => 'Swedish',              'native_name' => 'Svenska',              'tier' => 1],
        ['code' => 'da', 'name' => 'Danish',               'native_name' => 'Dansk',                'tier' => 1],
        ['code' => 'fi', 'name' => 'Finnish',              'native_name' => 'Suomi',                'tier' => 1],
        ['code' => 'nb', 'name' => 'Norwegian',            'native_name' => 'Norsk',                'tier' => 1],
        ['code' => 'cs', 'name' => 'Czech',                'native_name' => 'Čeština',              'tier' => 1],
        ['code' => 'el', 'name' => 'Greek',                'native_name' => 'Ελληνικά',             'tier' => 1],
        ['code' => 'hu', 'name' => 'Hungarian',            'native_name' => 'Magyar',               'tier' => 1],
        ['code' => 'ro', 'name' => 'Romanian',             'native_name' => 'Română',               'tier' => 1],
        ['code' => 'sk', 'name' => 'Slovak',               'native_name' => 'Slovenčina',           'tier' => 1],
        ['code' => 'uk', 'name' => 'Ukrainian',            'native_name' => 'Українська',           'tier' => 1],
        ['code' => 'he', 'name' => 'Hebrew',               'native_name' => 'עברית',               'tier' => 1],
        ['code' => 'tr', 'name' => 'Turkish',              'native_name' => 'Türkçe',               'tier' => 1],
        ['code' => 'vi', 'name' => 'Vietnamese',           'native_name' => 'Tiếng Việt',           'tier' => 1],
        ['code' => 'th', 'name' => 'Thai',                 'native_name' => 'ไทย',                  'tier' => 1],
        ['code' => 'id', 'name' => 'Indonesian',           'native_name' => 'Bahasa Indonesia',     'tier' => 1],
        ['code' => 'ms', 'name' => 'Malay',                'native_name' => 'Bahasa Melayu',        'tier' => 1],
        ['code' => 'fil', 'name' => 'Filipino',            'native_name' => 'Filipino',             'tier' => 1],

        // ── Tier 2: Mid Resource (35 languages) ──────────────────────
        ['code' => 'hi', 'name' => 'Hindi',                'native_name' => 'हिन्दी',              'tier' => 2],
        ['code' => 'bn', 'name' => 'Bengali',              'native_name' => 'বাংলা',                'tier' => 2],
        ['code' => 'ta', 'name' => 'Tamil',                'native_name' => 'தமிழ்',               'tier' => 2],
        ['code' => 'te', 'name' => 'Telugu',               'native_name' => 'తెలుగు',              'tier' => 2],
        ['code' => 'mr', 'name' => 'Marathi',              'native_name' => 'मराठी',               'tier' => 2],
        ['code' => 'gu', 'name' => 'Gujarati',             'native_name' => 'ગુજરાતી',             'tier' => 2],
        ['code' => 'kn', 'name' => 'Kannada',              'native_name' => 'ಕನ್ನಡ',              'tier' => 2],
        ['code' => 'ml', 'name' => 'Malayalam',            'native_name' => 'മലയാളം',             'tier' => 2],
        ['code' => 'pa', 'name' => 'Punjabi',              'native_name' => 'ਪੰਜਾਬੀ',              'tier' => 2],
        ['code' => 'ur', 'name' => 'Urdu',                 'native_name' => 'اردو',                 'tier' => 2],
        ['code' => 'fa', 'name' => 'Persian',              'native_name' => 'فارسی',               'tier' => 2],
        ['code' => 'sw', 'name' => 'Swahili',              'native_name' => 'Kiswahili',            'tier' => 2],
        ['code' => 'am', 'name' => 'Amharic',              'native_name' => 'አማርኛ',               'tier' => 2],
        ['code' => 'ha', 'name' => 'Hausa',                'native_name' => 'Hausa',                'tier' => 2],
        ['code' => 'yo', 'name' => 'Yoruba',               'native_name' => 'Yorùbá',              'tier' => 2],
        ['code' => 'ig', 'name' => 'Igbo',                 'native_name' => 'Igbo',                 'tier' => 2],
        ['code' => 'zu', 'name' => 'Zulu',                 'native_name' => 'isiZulu',             'tier' => 2],
        ['code' => 'af', 'name' => 'Afrikaans',            'native_name' => 'Afrikaans',            'tier' => 2],
        ['code' => 'bg', 'name' => 'Bulgarian',            'native_name' => 'Български',           'tier' => 2],
        ['code' => 'hr', 'name' => 'Croatian',             'native_name' => 'Hrvatski',             'tier' => 2],
        ['code' => 'et', 'name' => 'Estonian',             'native_name' => 'Eesti',                'tier' => 2],
        ['code' => 'lt', 'name' => 'Lithuanian',           'native_name' => 'Lietuvių',            'tier' => 2],
        ['code' => 'lv', 'name' => 'Latvian',              'native_name' => 'Latviešu',            'tier' => 2],
        ['code' => 'sl', 'name' => 'Slovenian',            'native_name' => 'Slovenščina',          'tier' => 2],
        ['code' => 'sr', 'name' => 'Serbian',              'native_name' => 'Српски',              'tier' => 2],
        ['code' => 'is', 'name' => 'Icelandic',            'native_name' => 'Íslenska',            'tier' => 2],
        ['code' => 'mk', 'name' => 'Macedonian',           'native_name' => 'Македонски',          'tier' => 2],
        ['code' => 'sq', 'name' => 'Albanian',             'native_name' => 'Shqip',                'tier' => 2],
        ['code' => 'ka', 'name' => 'Georgian',             'native_name' => 'ქართული',             'tier' => 2],
        ['code' => 'mn', 'name' => 'Mongolian',            'native_name' => 'Монгол',              'tier' => 2],
        ['code' => 'ne', 'name' => 'Nepali',               'native_name' => 'नेपाली',              'tier' => 2],
        ['code' => 'si', 'name' => 'Sinhala',              'native_name' => 'සිංහල',              'tier' => 2],
        ['code' => 'kk', 'name' => 'Kazakh',               'native_name' => 'Қазақша',             'tier' => 2],
        ['code' => 'uz', 'name' => 'Uzbek',                'native_name' => 'Oʻzbekcha',            'tier' => 2],
        ['code' => 'az', 'name' => 'Azerbaijani',          'native_name' => 'Azərbaycanca',         'tier' => 2],

        // ── Tier 3: Low Resource (5 languages) ───────────────────────
        ['code' => 'lo', 'name' => 'Lao',                  'native_name' => 'ລາວ',                  'tier' => 3],
        ['code' => 'km', 'name' => 'Khmer',                'native_name' => 'ភាសាខ្មែរ',          'tier' => 3],
        ['code' => 'my', 'name' => 'Burmese',              'native_name' => 'မြန်မာဘာသာ',         'tier' => 3],
        ['code' => 'ps', 'name' => 'Pashto',               'native_name' => 'پښتو',                'tier' => 3],
        ['code' => 'ti', 'name' => 'Tigrinya',             'native_name' => 'ትግርኛ',               'tier' => 3],
    ],

    /*
    |--------------------------------------------------------------------------
    | RTL Languages
    |--------------------------------------------------------------------------
    */
    'rtl' => ['ar', 'he', 'fa', 'ur', 'ps'],

    /*
    |--------------------------------------------------------------------------
    | Gendered Languages (require gender-neutral prompt instructions)
    |--------------------------------------------------------------------------
    */
    'gendered' => ['ar', 'he', 'fr', 'es', 'pt', 'ru', 'pl', 'hi', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Existing 25 Languages (regression baseline)
    |--------------------------------------------------------------------------
    */
    'baseline_languages' => [
        'en', 'zh', 'es', 'ar', 'pt', 'ru', 'fr', 'de', 'ja', 'ko',
        'it', 'nl', 'pl', 'sv', 'da', 'fi', 'nb', 'cs', 'el', 'hu',
        'ro', 'sk', 'uk', 'he', 'tr',
    ],
];
