# GEOA-7-B4 — English UI + i18n Framework

**Parent**: GEOA-7 (Phase B CTO Technical Execution Plan)
**Owner**: Staff Engineer (Product)
**Sprint**: Sprint 2 (Week 2)
**Depends On**: B5 (Infrastructure — deployment pipeline, K8s, database)
**Blocks**: B2 (analytics dashboard UI depends on Blade component library)

## Objective

Build the English-default UI with Vietnamese locale support, WordPress integration, i18n framework, Blade component library, responsive design, and SEO readiness. Zero hardcoded Chinese characters — enforced by CI lint rule.

## Technical Specification

See `deliverables/phase-b/cto-technical-execution-plan.md` Section 2.2 for full WordPress integration architecture, i18n framework design, Blade component library spec, SEO requirements, and payment UI integration.

### Key Components to Build

1. **WordPress Integration**
   - Custom theme (`geo119`) with Blade/Tailwind
   - WordPress as headless CMS: REST API serves localized content
   - Hybrid routing: `/wp/*` → WordPress, `/*` → Laravel (calls WP REST API server-side for content)
   - Plugins: ACF for content modeling, WPML/Polylang for multilingual, Yoast/Rank Math for SEO

2. **i18n Framework**
   - Locale detection pipeline: URL segment → Cookie → Accept-Language → 'en' fallback
   - Translation file structure: `lang/{locale}/{namespace}.json` (ui, errors, emails)
   - Laravel `__()` helper with JSON translation files
   - WordPress `__()`, `_e()`, `_x()` with `geo119` text domain
   - Fallback chain: requested locale → 'en' → key string → ''

3. **Blade Component Library**
   - Components: button, card, modal, input, select, table, badge, language-switcher
   - All components responsive (mobile-first), accessible (WCAG 2.1 AA)
   - Variants and sizes via props

4. **Tailwind Configuration**
   - Design tokens: colors, spacing, typography
   - Content paths for both Laravel views and WordPress theme
   - RTL support readiness (logical properties, `dir` attribute handling)

5. **SEO Setup**
   - Yoast/Rank Math SEO plugin active
   - JSON-LD structured data (Article, BreadcrumbList, Organization)
   - XML Sitemap with `hreflang` tags
   - Clean URLs: `/{locale}/{page-slug}/`

6. **Zero-Chinese Enforcement**
   - CI step: `grep -rP '[\x{4e00}-\x{9fff}]' resources/views/ wordpress/wp-content/themes/geo119/` — fails build on match

7. **Payment UI**
   - Stripe Elements (embedded), PayPal Buttons (embedded), MoMo/VNPay (redirect)
   - Cost display before every payment confirmation (CEO non-negotiable #1)

## Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B4.1 | All UI strings in English (default locale) | Visual audit of every page |
| B4.2 | Vietnamese locale loads correctly via `/vi/` prefix | Screenshot comparison |
| B4.3 | Zero hardcoded Chinese characters (CI lint passes) | `grep` returns empty |
| B4.4 | All Blade components render correctly on mobile (375px) and desktop (1440px) | Screenshots |
| B4.5 | WordPress REST API returns localized content | `curl /wp-json/wp/v2/pages?lang=vi` |
| B4.6 | Language switcher cycles through available locales | Click-through test |
| B4.7 | SEO meta tags present and correct per locale | View source audit |
| B4.8 | Payment UI shows cost before confirmation | Screenshot of payment screen |
| B4.9 | Keyboard navigation works on all interactive elements | Tab-through test |
| B4.10 | RTL stylesheet loads for Arabic without layout breakage | Set `?lang=ar`, verify layout |

## Edge Cases

| Scenario | Handling |
|----------|----------|
| WordPress is down, user hits content page | Laravel serves cached version from Redis (WP content cached on write) |
| Translation key missing for a locale | Fallback chain: requested → en → key display string → empty string (no crash) |
| User switches locale mid-session | Cookie updated, redirect to same URL with new locale prefix |
| RTL + LTR content mixed (English term in Arabic UI) | CSS `dir="auto"` on content areas; `bdi` elements for isolated text |
| Very long translation strings overflow UI | Blade components use `truncate` or `line-clamp` Tailwind utilities |
| Missing WordPress plugin during deploy | Composer-based WP plugin management (wpackagist); `composer install` installs plugins |
| Vietnamese tones not rendering correctly | UTF-8 enforced at every layer (DB, PHP, HTML meta charset) |

## Definition of Done

- [ ] English UI complete — all pages, all states (loading, empty, error, edge cases)
- [ ] Vietnamese locale fully translated and switchable
- [ ] CI lint for Chinese characters passing (zero matches)
- [ ] WordPress theme built with Blade + Tailwind
- [ ] All Blade components documented in a component gallery page
- [ ] Accessibility audit passed (axe-core or Lighthouse ≥ 90)
- [ ] SEO structured data validated (Google Rich Results Test)
- [ ] Payment UI with cost display functional
- [ ] All 10 acceptance criteria verified by QA Engineer
