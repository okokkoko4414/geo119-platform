<?php
declare(strict_types=1);

$langDir = __DIR__ . '/../lang';

$nativeNames = [
    'en'  => 'English',              'zh'  => '简体中文',
    'es'  => 'Español',              'ar'  => 'العربية',
    'pt'  => 'Português',            'ru'  => 'Русский',
    'fr'  => 'Français',             'de'  => 'Deutsch',
    'ja'  => '日本語',               'ko'  => '한국어',
    'it'  => 'Italiano',             'nl'  => 'Nederlands',
    'pl'  => 'Polski',               'sv'  => 'Svenska',
    'da'  => 'Dansk',                'fi'  => 'Suomi',
    'nb'  => 'Norsk',                'cs'  => 'Čeština',
    'el'  => 'Ελληνικά',             'hu'  => 'Magyar',
    'ro'  => 'Română',               'sk'  => 'Slovenčina',
    'uk'  => 'Українська',           'he'  => 'עברית',
    'tr'  => 'Türkçe',               'vi'  => 'Tiếng Việt',
    'th'  => 'ไทย',                  'id'  => 'Bahasa Indonesia',
    'ms'  => 'Bahasa Melayu',        'fil' => 'Filipino',
    'hi'  => 'हिन्दी',              'bn'  => 'বাংলা',
    'ta'  => 'தமிழ்',               'te'  => 'తెలుగు',
    'mr'  => 'मराठी',               'gu'  => 'ગુજરાતી',
    'kn'  => 'ಕನ್ನಡ',              'ml'  => 'മലയാളം',
    'pa'  => 'ਪੰਜਾਬੀ',              'ur'  => 'اردو',
    'fa'  => 'فارسی',               'sw'  => 'Kiswahili',
    'am'  => 'አማርኛ',               'ha'  => 'Hausa',
    'yo'  => 'Yorùbá',              'ig'  => 'Igbo',
    'zu'  => 'isiZulu',             'af'  => 'Afrikaans',
    'bg'  => 'Български',           'hr'  => 'Hrvatski',
    'et'  => 'Eesti',                'lt'  => 'Lietuvių',
    'lv'  => 'Latviešu',            'sl'  => 'Slovenščina',
    'sr'  => 'Српски',              'is'  => 'Íslenska',
    'mk'  => 'Македонски',          'sq'  => 'Shqip',
    'ka'  => 'ქართული',             'mn'  => 'Монгол',
    'ne'  => 'नेपाली',              'si'  => 'සිංහල',
    'kk'  => 'Қазақша',             'uz'  => 'Oʻzbekcha',
    'az'  => 'Azərbaycanca',         'lo'  => 'ລາວ',
    'km'  => 'ភាសាខ្មែរ',          'my'  => 'မြန်မာဘာသာ',
    'ps'  => 'پښتو',                'ti'  => 'ትግርኛ',
];

$created = 0;
$updated = 0;

foreach ($nativeNames as $code => $nativeName) {
    $filePath = $langDir . '/' . $code . '.json';

    if (file_exists($filePath)) {
        $data = json_decode(file_get_contents($filePath), true);
        if (!is_array($data)) {
            $data = [];
        }
        $key = "ui.language.{$code}";
        if (!isset($data[$key])) {
            $data[$key] = $nativeName;
            file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            echo "Updated: {$code}.json (added {$key})\n";
            $updated++;
        } else {
            echo "Skipped: {$code}.json (already has {$key})\n";
        }
    } else {
        $data = [
            "ui.language.{$code}" => $nativeName,
        ];
        file_put_contents($filePath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        echo "Created: {$code}.json\n";
        $created++;
    }
}

echo "\nDone: {$created} created, {$updated} updated.\n";
