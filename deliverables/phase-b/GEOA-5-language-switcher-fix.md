# GEOA-5: Language Switcher 70-Language Fix

## Problem

Board review identified: "Only 10 of 23 languages appear in the UI switcher."

Root cause: WordPress theme hardcoded `['en', 'vi']` in 4 locations:
- `GEO119_SUPPORTED_LOCALES` constant
- Language detection Accept-Language handler
- Rewrite rules regex
- JS URL rewriting

Laravel side already handled all 70 via `LocaleDetector::availableLocales()` with `en.json` fallback for display names.

## Fix: Path B — All 70 Languages

### WordPress (3 files)

**`functions.php`:**
- `GEO119_SUPPORTED_LOCALES`: expanded from 2 to all 70 language codes across 3 tiers
- Added `GEO119_LANGUAGE_NAMES`: native-name lookup for all 70 languages (matches `config/languages.php`)
- `geo119_detect_locale()`: Accept-Language handler generalized from hardcoded en/vi to check all 70
- `geo119_filter_locale()`: generalized from vi-only check to all supported locales
- Added `geo119_language_display_name()`: returns native name for a locale code
- Rewrite rules: regex built dynamically from full locale list
- REST field schema: updated description from "(en, vi)" to "(ISO 639-1)"
- `wp_localize_script`: now passes `supportedLocales` and `localeNames` to JS

**`header.php`:**
- Language switcher dropdown: replaced `$code === 'vi' ? 'Vietnamese' : 'English'` with `geo119_language_display_name($code)` — shows correct native name for all 70

**`assets/js/app.js`:**
- URL rewriting: replaced hardcoded `['en', 'vi']` with `window.geo119Data.supportedLocales`

### Laravel Locale Files

**50 new stub files** for languages with no prior translations (af, am, az, bg, cs, da, el, et, fa, fi, fil, gu, ha, he, hr, hu, ig, is, ka, kk, km, kn, lo, lt, lv, mk, ml, mn, mr, ms, my, nb, ne, pa, ps, ro, si, sk, sl, sq, sr, sv, sw, te, ti, uk, ur, uz, yo, zu).
Each contains: `{"ui.language.XX": "Native Name"}`

**18 existing files updated** with missing `ui.language.{self}` keys (ar, bn, de, es, fr, hi, id, it, ja, ko, nl, pl, pt, ru, ta, th, tr, zh).

**2 files already complete**: en.json, vi.json.

### Result

| Layer | Before | After |
|-------|--------|-------|
| WordPress supported locales | 2 (en, vi) | 70 |
| WordPress language display names | Hardcoded en/vi | Native names for all 70 |
| WordPress JS URL handling | Hardcoded [en, vi] | Dynamic from server |
| Laravel locale files | 21 JSON files | 70 JSON files |
| Language switcher experience | Shows 2 languages | Shows all 70 with native names |

### What This Does NOT Do

- The 49 new languages render the UI in English (Laravel falls back to `en.json` for untranslated keys). Full translation generation is Phase C work requiring DeepSeek batching.
- The `tl.json` (Tagalog) file is a legacy duplicate of `fil.json` (Filipino) — not used by the switcher.
