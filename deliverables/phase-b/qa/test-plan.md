# Phase B QA Test Plan

**Issue**: GEOA-15 Phase B QA — Quality Verification Across All Modules
**Date**: 2026-07-22
**Author**: QA Engineer (c44170af)
**Status**: READY — waiting for module completion
**Depends On**: B5, B4, B1, B2, B3 (verifies each as it completes)

---

## 1. Test Strategy Overview

### 1.1 Testing Layers

| Layer | Tool | Scope | Frequency |
|-------|------|-------|-----------|
| Unit tests | Pest/PHPUnit | All service classes, value objects, helpers | Every commit (CI) |
| Integration tests | Pest/PHPUnit | API endpoints, DB queries, queue jobs, cache | Every commit (CI) |
| Smoke tests | Pest/PHPUnit + curl | Health checks, critical path assertions | Every deploy |
| Visual audit | Browse (headless Chrome) | Screenshot comparison, layout checks, i18n rendering | Per module completion |
| Performance validation | curl + timed metrics | Throughput, latency, rollback timing | Per B3/B5 completion |
| Cross-browser | Browse with UA overrides | Chrome, Firefox, Safari, Mobile Safari | Per B4 completion |

### 1.2 Severity Classification

| Severity | Definition | Action |
|----------|-----------|--------|
| P0 | App crash, data loss, security breach, iron law violation | Block release, fix immediately |
| P1 | Core feature broken, acceptance criteria fails | Fix before release |
| P2 | Non-critical feature degraded, edge case broken | Fix before release if time permits |
| P3 | Cosmetic, alignment, typo, minor UX | Backlog, fix in next iteration |

### 1.3 CEO Non-Negotiable Verification

Each of these is a P0 if violated:

| # | Condition | Verification Method |
|---|-----------|-------------------|
| 1 | Cost visibility before payment | Visual audit of payment screens |
| 2 | English UI default + Vietnamese locale | Visual audit of all pages in /en/ and /vi/ |
| 3 | Before/after optimization scores | DB query on B3 results |
| 4 | No regression on existing 25 languages | B1 QualityGate delta check |
| 5 | MoMo/VNPay prioritized over Stripe/PayPal | Payment gateway order check |

---

## 2. Per-Module Test Plans

### 2.1 B5 — Infrastructure

**Gate**: Must pass before any other module testing begins.

#### Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B5-T1 | CI pipeline triggers on push | Push test commit, verify workflow starts | P0 |
| B5-T2 | Lint stage catches PSR-12 violations | Commit file with known violation, verify fail | P1 |
| B5-T3 | PHPStan level max passes on clean code | Verify `phpstan analyse` returns 0 errors | P1 |
| B5-T4 | Unit + feature tests run in CI | Verify test stage completes, coverage reported | P1 |
| B5-T5 | Docker build succeeds | Verify `docker build` completes | P0 |
| B5-T6 | Docker image pushed to registry | Verify image appears in ghcr.io | P1 |
| B5-T7 | K8s manifests apply (dry-run) | `kubectl apply --dry-run=server` all envs | P0 |
| B5-T8 | Health check endpoint returns 200 | `curl /health` | P0 |
| B5-T9 | PostgreSQL pgvector extension active | `SELECT * FROM pg_extension WHERE extname='vector'` | P1 |
| B5-T10 | Redis accepts connections | `redis-cli ping` → PONG | P1 |
| B5-T11 | Horizon processes job | Dispatch test job, verify completion | P1 |
| B5-T12 | claude_local endpoint reachable | Integration test with known prompt | P1 |

#### Manual Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B5-M1 | Rollback drill < 5 min | Deploy broken image, time `kubectl rollout undo` | P0 |
| B5-M2 | No secrets in ConfigMap | `kubectl describe configmap` audit | P0 |
| B5-M3 | Grafana dashboard renders | Screenshot of app health dashboard | P2 |
| B5-M4 | Telescope accessible in dev/staging | Browser navigation to Telescope URL | P2 |
| B5-M5 | DB backup runs | Check backup file exists, verify restore path | P1 |
| B5-M6 | Canary deploy (production) | Observe 10% canary → health check → full rollout | P1 |

#### Performance Tests

| Test ID | What | Target | Severity |
|---------|------|--------|----------|
| B5-P1 | Push-to-deploy time | < 5 min (CI pipeline end-to-end) | P0 |
| B5-P2 | Rollback time | < 5 min (timed drill) | P0 |
| B5-P3 | Health check response | < 500ms | P2 |

#### Iron Law Checks (B5)

| IL | Check | Method |
|----|-------|--------|
| IL-1 | Laravel 12 + PHP 8.4 | `composer.json` version check |
| IL-3 | PostgreSQL 16 + pgvector | `SELECT version()`, extension check |
| IL-4 | Redis 7 + Horizon | `redis-cli INFO server`, Horizon dashboard |
| IL-5 | Docker | Dockerfile + docker-compose.yml present |
| IL-6 | DeepSeek via claude_local only | Grep for OpenAI/Anthropic SDK imports — must be zero |
| IL-7-12 | No forbidden tech | Grep for vue/react/next/node/python/mongo/mysql |
| IL-17 | No secrets in committed code | Full codebase grep for API key patterns |

---

### 2.2 B4 — English UI + i18n Framework

**Gate**: Must pass before B2 (analytics dashboard depends on Blade components).

#### Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B4-T1 | All 70 locale routes return 200 | Iterate all locales, `GET /{locale}/`, assert 200 | P0 |
| B4-T2 | Zero Chinese characters in templates | `grep -rP '[\x{4e00}-\x{9fff}]' resources/views/` returns empty | P0 |
| B4-T3 | All strings use translation helpers | Grep for hardcoded English in templates | P0 |
| B4-T4 | Language fallback chain works | Request missing key, verify fallback to 'en' | P1 |
| B4-T5 | WordPress REST API returns localized content | `curl /wp-json/wp/v2/pages?lang=vi` | P1 |
| B4-T6 | SEO meta tags present per locale | Parse HTML, assert title/desc/hreflang/canonical | P1 |
| B4-T7 | RTL stylesheet loads for Arabic | `GET /ar/`, verify `dir="rtl"` on html element | P1 |
| B4-T8 | JSON-LD structured data validates | Google Rich Results Test or schema.org validator | P2 |

#### Manual Visual Audit

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B4-M1 | English UI reads native | Full page walkthrough at 1440px, screenshot every page | P0 |
| B4-M2 | Vietnamese locale renders correctly | `/vi/` prefix, screenshot comparison vs English | P0 |
| B4-M3 | Mobile responsive (375px) | Screenshot all pages at 375px width | P1 |
| B4-M4 | Tablet responsive (768px) | Screenshot key pages at 768px width | P2 |
| B4-M5 | Desktop responsive (1440px) | Baseline screenshots at 1440px | P1 |
| B4-M6 | Language switcher cycles all locales | Click through each available locale | P1 |
| B4-M7 | Keyboard navigation on all interactive elements | Tab through entire page | P1 |
| B4-M8 | Payment UI shows cost before confirmation | Screenshot payment confirmation screen | P0 |
| B4-M9 | Payment gateway order: MoMo/VNPay first | Screenshot payment method selection | P0 |
| B4-M10 | Zero Chinese characters (visual confirmation) | Eye-scan every rendered page | P0 |

#### Cross-Browser Tests

| Test ID | What | Browsers | Severity |
|---------|------|----------|----------|
| B4-C1 | Homepage renders | Chrome, Firefox, Safari, Mobile Safari | P1 |
| B4-C2 | Language switcher works | Chrome, Firefox, Safari | P1 |
| B4-C3 | Payment flow works | Chrome, Firefox, Safari | P1 |
| B4-C4 | RTL layout intact | Chrome, Firefox, Safari | P2 |

#### Performance Tests

| Test ID | What | Target | Severity |
|---------|------|--------|----------|
| B4-P1 | Lighthouse SEO score | >= 90 | P1 |
| B4-P2 | Lighthouse Accessibility score | >= 90 | P1 |
| B4-P3 | First Contentful Paint | < 2s | P2 |
| B4-P4 | Page load (full) | < 3s | P2 |

---

### 2.3 B1 — Language Expansion 25->70

**Gate**: Must pass before B3 (optimization engine builds on translation patterns).

#### Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B1-T1 | 70 languages defined in LanguageRegistry | Count languages in config/languages.php | P0 |
| B1-T2 | All 70 locales return 200 | Automated GET /{locale}/ for all 70 | P0 |
| B1-T3 | TranslationPipeline processes a string | Unit test with mock DeepSeek | P1 |
| B1-T4 | QualityGate scores translation | Unit test with known good/bad translations | P1 |
| B1-T5 | Fallback chain: missing locale -> en | Request missing key in Tier 3 lang, verify en fallback | P0 |
| B1-T6 | Fallback chain: missing key -> key string | Request entirely missing key, verify key name shown | P1 |
| B1-T7 | Translation cache hit returns cached | Two identical translate() calls, verify 2nd from cache | P1 |
| B1-T8 | Translation cache miss -> DeepSeek call -> cache write | Verify cache populated after first call | P1 |
| B1-T9 | HTML tags preserved in translation | Translate string with `<strong>` tags, verify tags intact | P0 |
| B1-T10 | ICU placeholders preserved | Translate string with `:name`, verify placeholder intact | P0 |
| B1-T11 | lang:expand CLI command triggers pipeline | `php artisan lang:expand {code}` | P1 |
| B1-T12 | Regression: existing 25 languages delta <= 2% | COMET score comparison vs baseline | P0 |
| B1-T13 | RTL languages have correct text direction | Screenshot Arabic/Hebrew pages | P1 |
| B1-T14 | Tier 3 untranslated keys show English, not blank | Visual check of Tier 3 language pages | P0 |

#### Manual Visual Audit

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B1-M1 | Tier 2 blind test: 10 languages, "understandable" > 90% | Random sample, 3 evaluators each | P0 |
| B1-M2 | Tier 1 quality spot-check: 5 languages, 20 keys each | Manual review of translation quality | P1 |
| B1-M3 | Tier 3 "beta" badge visible | Visual check on Tier 3 language pages | P2 |
| B1-M4 | Language switcher shows all 70 languages | Click through language dropdown | P2 |

#### Performance Tests

| Test ID | What | Target | Severity |
|---------|------|--------|----------|
| B1-P1 | Translation cache hit rate | > 60% | P2 |
| B1-P2 | 10k keys processed | < 1 hour | P1 |
| B1-P3 | Single translation latency (cache hit) | < 10ms | P2 |
| B1-P4 | Single translation latency (cache miss) | < 5s | P2 |

---

### 2.4 B2 — Effect Tracking

**Gate**: B-Core must pass before B-Growth begins.

#### B-Core Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B2-T1 | POST /api/e/track accepts valid event | curl with valid payload, assert 204, verify DB row | P0 |
| B2-T2 | POST /api/e/track rejects invalid payload | Missing type/target, assert 422 | P1 |
| B2-T3 | Events appear in dashboard within 30s | Fire event, poll dashboard SSE, measure latency | P0 |
| B2-T4 | Dashboard shows breakdown by language | Seed events in 3 languages, verify table rows | P1 |
| B2-T5 | CTR computed correctly | Seed known impressions + clicks, verify CTR value | P1 |
| B2-T6 | CTR shows "—" when impressions=0 | Query language with no impressions, verify display | P1 |
| B2-T7 | Language breakdown sorts correctly | Click sort header, verify order | P2 |
| B2-T8 | % change vs yesterday computed correctly | Seed yesterday + today data, verify % | P2 |
| B2-T9 | Bot traffic filtered from dashboard | Simulate >100 events/min pattern, verify excluded | P1 |
| B2-T10 | IP hashed before storage | Check DB, verify raw IP never stored | P0 |
| B2-T11 | No cookies for tracking | Verify no Set-Cookie header from /api/e/track | P1 |
| B2-T12 | Tracking beacon JS has zero console errors | Open browser console, navigate pages, verify clean | P1 |

#### B-Growth Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B2-T13 | Conversion funnel shows correct step counts | Seed event sequence, verify funnel visualization | P1 |
| B2-T14 | Retention cohort D1/D7/D30 correct | Seed multi-date events, verify cohort table | P1 |
| B2-T15 | Multi-dimension drill-down: lang x device | Apply both filters, verify results filtered | P1 |
| B2-T16 | Export API returns CSV with headers | GET /api/analytics/export?format=csv, validate | P1 |
| B2-T17 | Export API returns JSON with correct structure | GET /api/analytics/export?format=json, validate schema | P1 |
| B2-T18 | Export API handles empty date range | Range with no data, assert 200 + headers only | P2 |
| B2-T19 | Export API handles 90-day range | Large request, verify streaming response completes | P1 |
| B2-T20 | Export API rejects invalid format | format=xml, assert 422 | P2 |

#### Manual Visual Audit

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B2-M1 | Dashboard renders without console errors | Browser navigation, console check | P0 |
| B2-M2 | Real-time counter updates without page refresh | Watch counter while dispatching events in another tab | P1 |
| B2-M3 | Conversion funnel renders for Vietnamese filter | Apply language filter, screenshot funnel | P1 |
| B2-M4 | Multi-dimension drill-down UI updates chart + table | Change each filter, verify both update | P1 |
| B2-M5 | Export API CSV opens correctly in spreadsheet | Download CSV, open in LibreOffice/Excel | P2 |

#### Performance Tests

| Test ID | What | Target | Severity |
|---------|------|--------|----------|
| B2-P1 | Event ingestion latency | < 30s from POST to dashboard visible | P0 |
| B2-P2 | Dashboard page load | < 2s | P2 |
| B2-P3 | Export API 90-day streaming | Completes without timeout | P1 |

---

### 2.5 B3 — Batch Optimization Engine

**Gate**: Final module. Must pass all targets.

#### Automated Tests

| Test ID | What | How | Severity |
|---------|------|-----|----------|
| B3-T1 | POST /api/batch accepts valid payload | Submit batch of 20 texts, assert 202 + job_id | P0 |
| B3-T2 | POST /api/batch rejects empty batch | Submit [], assert 422 | P1 |
| B3-T3 | Dedup cache: identical input returns cached result | Submit same text twice, verify 2nd cost=$0 | P0 |
| B3-T4 | Dedup cache lock: concurrent identical requests | Submit same text from 2 threads, verify 1 processes, 1 waits | P1 |
| B3-T5 | Circuit breaker opens after 5 failures | Kill claude_local, submit 5 jobs, verify 6th = 503 | P0 |
| B3-T6 | Circuit breaker half-open probe succeeds | Restore claude_local, wait cooldown, verify recovery | P1 |
| B3-T7 | Circuit breaker half-open probe fails -> open again | Keep claude_local dead, verify stays open | P1 |
| B3-T8 | Retry with exponential backoff: 1s -> 2s -> 4s | Simulate transient failure, verify retry timing | P1 |
| B3-T9 | Retry only retries failed segments, not full batch | 20 texts, kill after 5 succeed, verify only 15 retried | P1 |
| B3-T10 | Concurrency semaphore max 20 active | Submit 30 concurrent, verify max 20 active | P1 |
| B3-T11 | Queue wait >30s returns 202 with polling URL | Exhaust semaphore, submit job, verify 202 + Location | P2 |
| B3-T12 | CostTracker records per-request metrics | Submit job, query cost_tracker, verify all fields | P1 |
| B3-T13 | Daily budget cap enforced | Set $0.10 cap, submit jobs exceeding, verify rejection | P0 |
| B3-T14 | Every OptimizationResult has before/after scores | SQL query, verify both columns non-null | P0 |
| B3-T15 | Before score equals after score when no improvement | Submit no-op text, verify both scores equal | P2 |
| B3-T16 | Long text split at sentence boundaries | Submit 5000+ char text, verify segments preserved | P2 |
| B3-T17 | Code blocks preserved in translation | Submit text with code blocks, verify code untouched | P1 |
| B3-T18 | Degrade to cache-only when budget exceeded | Exhaust budget, submit cached text -> works, uncached -> rejected | P1 |

#### Performance Tests

| Test ID | What | Target | Severity |
|---------|------|--------|----------|
| B3-P1 | Throughput: >= 10k words/hour | Load test: 50k words, measure completion time | P0 |
| B3-P2 | Cost: < $0.001/word aggregate | CostTracker report over 100k+ word test | P0 |
| B3-P3 | P99 latency: < 30s | Load test with percentile breakdown | P0 |
| B3-P4 | Dedup cache hit rate: > 70% | Repeated content test, measure hits | P1 |
| B3-P5 | Engine self-cost < savings generated | A/B comparison: optimized vs unoptimized cost | P0 |

---

## 3. Iron Law Compliance Audit

Full 17-point checklist verified per module and globally.

### 3.1 Tech Stack Iron Laws (IL-1 through IL-12)

| # | Law | Check | Module |
|---|------|-------|--------|
| IL-1 | Laravel 12 + PHP 8.4 | composer.json version check | All |
| IL-2 | WordPress + Blade + Tailwind | File structure audit | B4 |
| IL-3 | PostgreSQL 16 + pgvector | SELECT version(), pg_extension | B5 |
| IL-4 | Redis 7 + Horizon | redis-cli INFO, Horizon dashboard | B5 |
| IL-5 | Docker | Dockerfile + docker-compose.yml | B5 |
| IL-6 | DeepSeek via claude_local ONLY | grep -r "openai\|anthropic" app/ returns empty | All |
| IL-7 | No Vue.js | grep -r "vue" returns empty in package.json | All |
| IL-8 | No React | grep -r "react" returns empty in package.json | All |
| IL-9 | No Next.js | grep -r "next" returns empty in package.json | All |
| IL-10 | No Node.js backend | No server.js, app.js, Express imports | All |
| IL-11 | No Python backend | No .py files in backend paths | All |
| IL-12 | No MongoDB/MySQL | Only PostgreSQL in config/database.php | B5 |

### 3.2 Architectural Iron Laws (IL-13 through IL-17)

| # | Law | Check | Module |
|---|------|-------|--------|
| IL-13 | All AI calls go through claude_local | Single ClaudeLocal service class | All |
| IL-14 | No SPA — Blade server-rendered | No app.js mounting root div | B4 |
| IL-15 | Vanilla JS only, < 50 lines/file | JS file audit | B2, B4 |
| IL-16 | WordPress presentation, Laravel API | Architecture check | B4 |
| IL-17 | No secrets in committed code | grep for API keys, tokens, passwords | All |

---

## 4. Regression Test Suite Design

### 4.1 Critical Path Smoke Tests

```php
// tests/Smoke/HealthCheckTest.php
test('health endpoint returns 200', function () {
    $response = $this->get('/health');
    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);
});

// tests/Smoke/LocaleRoutesTest.php
test('all 70 locale routes return 200', function (string $locale) {
    $response = $this->get("/{$locale}/");
    $response->assertStatus(200);
})->with('locales');

// tests/Smoke/NoChineseCharactersTest.php
test('zero chinese characters in rendered HTML', function (string $route) {
    $response = $this->get($route);
    $response->assertStatus(200);
    expect($response->content())->not->toMatch('/\p{Han}/u');
})->with('routes');
```

### 4.2 CI Integration

Tests run automatically via `.github/workflows/ci.yml` test stage:
- `php artisan test --parallel --coverage-text`
- PostgreSQL + Redis service containers
- Migrations run fresh per CI run

### 4.3 Regression Test Naming Convention

- `tests/Smoke/*Test.php` — critical path smoke tests
- `tests/Feature/Module/*Test.php` — per-module feature tests
- `tests/Unit/Services/*Test.php` — service class unit tests
- New regression tests: `*Test.regression-N.php` suffix per Phase 8e.5 convention

---

## 5. QA Schedule

### 5.1 Verification Gates

| Gate | Module | Prerequisites | QA Activities |
|------|--------|--------------|---------------|
| G1 | B5 | B5 deployed to staging | CI/CD drill, rollback drill, secret audit, claude_local test |
| G2 | B4 | B4 deployed to staging | Visual audit (en/vi), Lighthouse, Chinese char scan, responsive check |
| G3 | B1 | B1 deployed to staging | 70-lang load check, Tier 2 blind test, regression baseline, cache hit rate |
| G4 | B2 B-Core | B2 B-Core deployed to staging | Event pipeline latency, dashboard render, tracking beacon test |
| G5 | B2 B-Growth | B2 B-Growth deployed to staging | Export API correctness, funnel render, cohort accuracy |
| G6 | B3 | B3 deployed to staging | Throughput test, cost test, P99 test, circuit breaker drill |
| G7 | Final | All modules on production | Full iron law audit, integration smoke test, cross-browser pass |

### 5.2 Estimated Effort

| Gate | Automated Tests | Manual Tests | Duration |
|------|----------------|--------------|----------|
| G1 (B5) | 12 tests | 6 manual checks | 1 hour |
| G2 (B4) | 8 tests | 10 visual checks | 2 hours |
| G3 (B1) | 14 tests | 4 manual checks | 3 hours |
| G4 (B2 B-Core) | 12 tests | 2 visual checks | 1 hour |
| G5 (B2 B-Growth) | 8 tests | 3 visual checks | 1.5 hours |
| G6 (B3) | 18 tests | 5 perf tests | 3 hours |
| G7 (Final) | 17 IL checks | 4 cross-browser | 2 hours |
| **Total** | **89 tests** | **34 manual checks** | **~13.5 hours** |

---

## 6. Bug Report Template

```markdown
### BUG-XXX: [One-line summary]

**Severity**: P0 / P1 / P2 / P3
**Module**: B1 / B2 / B3 / B4 / B5
**Page/Route**: [URL]
**Browser**: [Chrome/Firefox/Safari/Mobile]
**Viewport**: [width]

**Repro Steps**:
1. Navigate to [URL]
2. [Action]
3. [Action]
4. Observe [bug]

**Expected**: [What should happen]
**Actual**: [What actually happens]

**Screenshots**:
- Before: [path]
- After action: [path]

**Console Errors**: [paste if any]
**First Seen**: [date]
```

---

## 7. Deliverables Checklist

- [ ] QA test plan (this document)
- [ ] Automated regression test suite (Pest/PHPUnit) — created when first module lands
- [ ] Visual audit report: English UI (all pages)
- [ ] Visual audit report: Vietnamese locale (/vi/)
- [ ] Zero Chinese characters verification
- [ ] i18n completeness: all UI strings translated, no missing keys, no hardcoded text
- [ ] Performance validation: B5 rollback < 5min
- [ ] Performance validation: B3 throughput >= 10k/hr
- [ ] Performance validation: B3 P99 < 30s
- [ ] Cross-browser testing (Chrome, Firefox, Safari, mobile)
- [ ] Bug reports with repro steps + severity (bug-log.md)
- [ ] Iron law compliance audit (iron-law-audit.md)
- [ ] Acceptance criteria matrix (acceptance-test-matrix.md)
- [ ] Final QA report (qa-report-*.md)

---

## 8. Current Status

**2026-07-22**: QA test plan created. Blocked on module implementation. Zero of 5 modules have code to test.

| Module | Status | Code Exists | QA Gate |
|--------|--------|------------|---------|
| B5 Infrastructure | Not started | No | G1 |
| B4 English UI | Not started | No | G2 |
| B1 Language Expansion | Not started | No | G3 |
| B2 Effect Tracking | Not started | No | G4, G5 |
| B3 Batch Optimization | Not started | No | G6 |
| Final Integration | Not started | No | G7 |
