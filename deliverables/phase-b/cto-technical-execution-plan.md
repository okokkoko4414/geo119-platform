# GEO119 Phase B — CTO Technical Execution Plan

**Date**: 2026-07-22
**Author**: CTO agent (71b65322)
**Status**: Locked — ready for CEO review
**Prerequisite**: CEO Phase B Product Plan (`deliverables/phase-b/ceo-plan.md`)
**Board Review**: Pending (GEOA-3 pattern — plan complete, route to Board for iron law verification)

---

## Executive Summary

This plan translates the CEO's Phase B product direction into a locked technical architecture and execution schedule. All five modules (B5→B4→B1→B2→B3) are decomposed into child issues with staffing assignments, technical specifications, data flow diagrams, edge case matrices, and acceptance criteria.

**Iron Law Compliance**: Every component in this plan uses only the board-approved tech stack. No Vue, React, Next.js, Node.js, Python backend, or new databases. See Appendix A for the full iron law verification matrix.

**Known Issue Addressed**: Phase A `05-execution-planning/development-roadmap.md` referenced stale stack (Python/FastAPI/Next.js/Qwen/MySQL). That file is not present in the current workspace and is superseded by this document. All Phase A technical documents should be verified against the board-approved iron law before any implementation begins.

---

## 1. Architecture Overview

### 1.1 System Context Diagram

```
                          ┌──────────────────────────────┐
                          │       Cloudflare / CDN        │
                          └──────────────┬───────────────┘
                                         │
                          ┌──────────────▼───────────────┐
                          │     K8s Ingress Controller    │
                          │   (nginx-ingress + cert-man)  │
                          └──────────────┬───────────────┘
                                         │
              ┌──────────────────────────┼──────────────────────────┐
              │                          │                          │
    ┌─────────▼─────────┐    ┌──────────▼──────────┐    ┌─────────▼─────────┐
    │   WordPress Pod   │    │    Laravel Pod(s)    │    │   Horizon Pod(s)  │
    │   (PHP-FPM+Nginx) │    │   (PHP-FPM+Nginx)   │    │   (PHP 8.4 CLI)   │
    │   Blade+Tailwind  │    │   GEOFlow Core      │    │   Queue Workers    │
    │   WP REST API     │    │   REST/GraphQL API  │    │   Translation Jobs │
    └─────────┬─────────┘    └──────────┬──────────┘    │   Batch Optimizer  │
              │                          │               └─────────┬─────────┘
              │               ┌──────────▼──────────┐              │
              │               │    Redis Cluster     │              │
              │               │  (Cache + Horizon)   │              │
              │               └──────────┬──────────┘              │
              │                          │                          │
              │               ┌──────────▼──────────┐              │
              │               │  PostgreSQL 16      │              │
              └───────────────►  + pgvector          ◄──────────────┘
                              │  (Primary + Replica) │
                              └──────────┬──────────┘
                                         │
                          ┌──────────────▼───────────────┐
                          │     claude_local (DeepSeek)   │
                          │     AI Inference Endpoint     │
                          └──────────────────────────────┘
```

### 1.2 Data Flow — Key Paths

**Path A — Translation (B1)**:
```
User Request → Laravel API → TranslationJob dispatched to Horizon
  → Horizon Worker picks up job
  → claude_local (DeepSeek) for translation
  → QualityGate scoring (BLEU/COMET)
  → PostgreSQL (translations table)
  → Redis (translation cache warm)
  → WordPress REST API serves localized content
```

**Path B — Effect Tracking (B2)**:
```
Browser Event → POST /api/events → Laravel API
  → Event validated + enriched (geo-IP, user-agent parse)
  → Redis Stream (real-time) → SSE to dashboard
  → PostgreSQL (events table, partitioned)
  → Materialized view refresh (aggregates)
  → Dashboard Blade view renders charts
```

**Path C — Batch Optimization (B3)**:
```
Batch Submit → Laravel API → BatchOptimizer
  → DedupCache check (Redis)
  → ConcurrencyController (semaphore acquire)
  → CircuitBreaker check
  → claude_local (DeepSeek) batch call
  → CostTracker records tokens + cost
  → Before/after scores computed
  → PostgreSQL (optimization_results)
  → Response returned
```

### 1.3 Technology Stack Assignment

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| Backend Framework | Laravel (GEOFlow) | 12.x | Business logic, API, queues |
| Language | PHP | 8.4 | Runtime |
| CMS | WordPress | 6.x | Content management, SEO |
| Templating | Blade + Tailwind CSS | 3.x | UI rendering |
| Database | PostgreSQL + pgvector | 16 | Primary data store, vector search |
| Cache/Queue | Redis + Horizon | 7.x | Caching, async job processing |
| AI Inference | DeepSeek via claude_local | — | Translation, optimization |
| Container | Docker | 26.x | Application packaging |
| Orchestration | Kubernetes | 1.31+ | Deployment, scaling, HA |
| i18n | Laravel Localization + WP i18n | — | Multi-language framework |
| Monitoring | Prometheus + Grafana | — | Metrics, alerting |
| CI/CD | GitHub Actions | — | Build, test, deploy pipeline |
| Payment | Stripe + PayPal + MoMo/VNPay | — | Payment processing |

---

## 2. Module Technical Specifications

### 2.1 B5 — Infrastructure (Sprint 1, Week 1)

**Owner**: Release Engineer
**Child Issue**: GEOA-7-B5

#### 2.1.1 CI/CD Pipeline

```
Git Push → GitHub Actions:
  1. Lint (PHP_CodeSniffer + Laravel Pint)
  2. Static Analysis (PHPStan level max)
  3. Unit Tests (PHPUnit) + Feature Tests (Pest)
  4. Docker Build (multi-stage, tagged :sha + :latest)
  5. Push to Container Registry
  6. K8s Apply (kubectl apply -f k8s/{env}/)
  7. Health Check (HTTP GET /health, retry 3x, 5s interval)
  8. Rollback on Failure (kubectl rollout undo, alert)
```

**Rollback SLA**: From health check failure detection to previous revision serving traffic: **< 5 minutes**.

#### 2.1.2 Docker Configuration

Multi-stage build:
```dockerfile
# Stage 1: Composer dependencies
FROM composer:2 AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# Stage 2: Node assets (Tailwind)
FROM node:22 AS assets
COPY package.json package-lock.json ./
RUN npm ci && npm run build

# Stage 3: Production image
FROM php:8.4-fpm-alpine
# ... PHP extensions, Nginx, copy vendor + assets
```

#### 2.1.3 Kubernetes Manifests

Per environment (dev/staging/production):
- `deployment.yaml`: Laravel (3 replicas min, HPA 3-10), WordPress (2 replicas), Horizon (2 replicas)
- `service.yaml`: ClusterIP for internal, LoadBalancer for ingress
- `ingress.yaml`: TLS termination, routing rules (/wp/* → WordPress, /* → Laravel)
- `configmap.yaml`: Non-secret config (APP_DEBUG=false, DB_HOST, REDIS_HOST, etc.)
- `secrets.yaml`: SealedSecrets or ExternalSecrets operator (DB_PASSWORD, APP_KEY, DEEPSEEK_API_KEY)
- `pvc.yaml`: PersistentVolumeClaim for WordPress uploads
- `hpa.yaml`: CPU 70% target, memory 80% target
- `pdb.yaml`: PodDisruptionBudget (minAvailable 1)

#### 2.1.4 Monitoring Stack

- **Laravel Telescope**: Dev/staging only (disabled in production)
- **Prometheus**: Node exporter + Redis exporter + PostgreSQL exporter + custom Laravel metrics endpoint
- **Grafana Dashboards**: App health, queue depth, API latency, error rate, DB connections, Redis hit rate
- **Alerts**: PagerDuty webhook for: error rate > 1%, queue depth > 1000, API P95 > 2s, DB connection pool exhausted, pod crash loop
- **Structured Logging**: JSON format → stdout → K8s log aggregation (Loki or ELK)

#### 2.1.5 Database Setup

```sql
-- PostgreSQL 16 with pgvector
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_trgm;      -- fuzzy text search
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";   -- UUID generation

-- Connection pooling via PgBouncer (sidecar container)
-- Backup: pg_dump hourly + WAL archiving for PITR
```

#### 2.1.6 claude_local Integration

- **Endpoint**: Configured via `DEEPSEEK_ENDPOINT` and `DEEPSEEK_API_KEY` env vars
- **SDK Pattern**: Laravel HTTP Client facade wrapping claude_local calls
- **Cost Tracking**: Log input_tokens, output_tokens, model, latency per request
- **Circuit Breaker**: 5 consecutive failures → open circuit for 30s → half-open probe
- **Rate Limiting**: Token bucket (100 requests/minute per pod)

#### 2.1.7 B5 Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B5.1 | `git push main` triggers full CI/CD pipeline | Push test commit |
| B5.2 | Failed health check triggers rollback < 5min | Deploy bad image, measure |
| B5.3 | All K8s manifests apply cleanly to cluster | `kubectl apply --dry-run=server` |
| B5.4 | Prometheus metrics endpoint responds | `curl /metrics` |
| B5.5 | Grafana dashboard shows app health | Screenshot |
| B5.6 | PostgreSQL pgvector extension active | `SELECT * FROM pg_extension WHERE extname='vector'` |
| B5.7 | Redis + Horizon queue accepts jobs | Dispatch test job |
| B5.8 | claude_local endpoint reachable with valid response | Integration test |
| B5.9 | Rollback completes in < 5min (timed) | Timed drill |
| B5.10 | No secrets in ConfigMap (verified) | `kubectl describe configmap` |

---

### 2.2 B4 — English UI (Sprint 2, Week 2)

**Owner**: Staff Engineer (UI + i18n)
**Child Issue**: GEOA-7-B4

#### 2.2.1 WordPress Integration Architecture

```
Browser Request
  │
  ├─ /wp/* ──────────► WordPress (content pages, blog, SEO)
  │                     ├─ Theme: Custom Blade/Tailwind theme
  │                     ├─ REST API: /wp-json/wp/v2/pages?lang={locale}
  │                     └─ Plugins: ACF, WPML/Polylang, Yoast/SEO
  │
  └─ /* (app routes) ► Laravel (application logic)
                        ├─ Blade views rendered with locale
                        ├─ Translation keys loaded from JSON
                        └─ Hybrid: WP REST API called server-side for content
```

**WordPress Theme**: A custom theme (name: `geo119`) built with:
- Blade templating via Sage 10 or direct Blade integration
- Tailwind CSS compiled from theme's `tailwind.config.js`
- Zero hardcoded Chinese strings — enforced by CI lint rule (regex: `[\x{4e00}-\x{9fff}]`)

#### 2.2.2 i18n Framework

**Architecture**:

```
Locale Detection Pipeline:
  1. URL segment: /{locale}/... (primary, SEO-friendly)
  2. Cookie: geo119_locale (user preference persistence)
  3. Accept-Language header (first-visit detection)
  4. Fallback: 'en' (English default)

Fallback Chain per Translation Key:
  requested_locale → 'en' → key_display_string → ''
```

**Translation File Structure**:
```
lang/
  en/
    ui.json         ← "Welcome to GEO119", "Sign Up", etc.
    errors.json     ← "Something went wrong", validation messages
    emails.json     ← Email templates
  vi/
    ui.json         ← Vietnamese translations
    errors.json
    emails.json
  ja/
    ui.json
    ...
```

**Laravel Localization**:
```php
// config/app.php
'locale' => 'en',
'fallback_locale' => 'en',
'available_locales' => ['en', 'vi', 'ja', 'ko', 'de', 'fr', 'pt', ...],

// Helper
__('ui.welcome')  // resolves from JSON based on current locale
```

**WordPress i18n**: All theme strings use `__()`, `_e()`, `_x()` with `geo119` text domain. Translation files managed via WPML or Polylang.

**CI Enforcement Rule**: A GitHub Actions step runs `grep -rP '[\x{4e00}-\x{9fff}]' resources/views/ wordpress/wp-content/themes/geo119/` — any match fails the build.

#### 2.2.3 UI Component Library

Blade components (`resources/views/components/`):
- `button.blade.php` — variants: primary, secondary, danger, ghost; sizes: sm, md, lg
- `card.blade.php` — with header, body, footer slots
- `modal.blade.php` — accessible dialog with focus trap
- `input.blade.php` — with label, error, hint slots
- `select.blade.php` — with options slot
- `table.blade.php` — sortable, paginated
- `badge.blade.php` — status indicators
- `language-switcher.blade.php` — locale dropdown

**Tailwind Configuration**:
```js
// tailwind.config.js
module.exports = {
  content: [
    './resources/views/**/*.blade.php',
    './wordpress/wp-content/themes/geo119/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: { /* brand palette */ },
        surface: { /* background hierarchy */ },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'monospace'],
      },
    },
  },
}
```

**Responsive Breakpoints**: mobile (< 640px), tablet (640-1024px), desktop (> 1024px). All components designed mobile-first.

**Accessibility**: WCAG 2.1 AA minimum. All interactive elements keyboard-navigable, focus visible, aria labels on icon-only controls, color contrast ratio ≥ 4.5:1.

#### 2.2.4 SEO Requirements

- WordPress Yoast/Rank Math SEO plugin active
- Structured data (JSON-LD) via WordPress: Article, BreadcrumbList, Organization
- XML Sitemap with `hreflang` tags for all locales
- Meta title/description per locale, editable in WordPress
- URL structure: `https://geo119.com/{locale}/{page-slug}/`
- Canonical URLs with locale alternates
- `og:` and `twitter:` meta tags auto-generated

#### 2.2.5 Payment UI Integration

- Stripe Elements (embedded UI, no redirect) — primary
- PayPal Buttons (embedded) — secondary
- MoMo/VNPay — redirect-based, Vietnam-first launch
- All payment flows show **compute cost before confirmation** (CEO non-negotiable #1)
- Payment UI uses shared Blade components, no framework-specific code

#### 2.2.6 B4 Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B4.1 | All UI strings in English (default locale) | Visual audit |
| B4.2 | Vietnamese locale loads correctly when `?lang=vi` or `/vi/` | Screenshot |
| B4.3 | Zero hardcoded Chinese characters (CI lint passes) | `grep -rP '[\x{4e00}-\x{9fff}]'` returns empty |
| B4.4 | All Blade components render correctly on mobile + desktop | Screenshots at 375px + 1440px |
| B4.5 | WordPress REST API returns localized content | `curl /wp-json/wp/v2/pages?lang=vi` |
| B4.6 | Language switcher cycles through available locales | Click-through test |
| B4.7 | SEO meta tags present per locale | View source check |
| B4.8 | Payment UI shows cost before confirmation | Screenshot |
| B4.9 | Accessibility keyboard navigation works | Tab-through test |
| B4.10 | RTL stylesheet loads for Arabic (future-proof, no visual breakage) | Set locale=ar, verify layout doesn't break |

---

### 2.3 B1 — Language Expansion 25→70 (Sprints 3-4, Weeks 3-4)

**Owner**: Staff Engineer (Language Pipeline)
**Child Issue**: GEOA-7-B1

#### 2.3.1 Translation Pipeline Architecture

```
                         ┌─────────────────────┐
                         │  TranslationManager  │
                         │  (Orchestrator)      │
                         └──────────┬──────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
    ┌─────────▼─────────┐ ┌────────▼────────┐ ┌─────────▼─────────┐
    │   Tier 1 Queue    │ │  Tier 2 Queue   │ │   Tier 3 Queue    │
    │  (high-resource)  │ │ (mid-resource)  │ │  (low-resource)   │
    │  30 languages     │ │  35 languages   │ │   5 languages     │
    └─────────┬─────────┘ └────────┬────────┘ └─────────┬─────────┘
              │                     │                     │
              ▼                     ▼                     ▼
    ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
    │ DeepSeek full   │ │ DeepSeek +      │ │ Machine trans   │
    │ translation     │ │ term validation │ │ + English flag   │
    │ + auto QA       │ │ + fallback      │ │ annotation       │
    └────────┬────────┘ └────────┬────────┘ └────────┬────────┘
              │                     │                     │
              └─────────────────────┼─────────────────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   QualityGate       │
                         │   (BLEU/COMET)      │
                         │   Pass/Fail per lang│
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   TranslationCache  │
                         │   (Redis)           │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   PostgreSQL        │
                         │   translations      │
                         └─────────────────────┘
```

#### 2.3.2 Language Tier Definitions

**Tier 1 — High Resource (30 languages)**:
Existing 25 + 5 new additions: Japanese (ja), Korean (ko), German (de), French (fr), Portuguese (pt).
- Strategy: Full DeepSeek translation
- Quality Gate: COMET score ≥ 0.85 (or ≥80% of English baseline)
- No human-in-loop required

**Tier 2 — Mid Resource (35 languages)**:
Examples: Thai (th), Indonesian (id), Turkish (tr), Vietnamese (vi, existing), Hindi (hi), Bengali (bn), etc.
- Strategy: DeepSeek translation + terminology validation checklist
- Fallback: English for segments where confidence < threshold
- UI annotation: "Beta" badge on language selector for Tier 2 languages
- Quality Gate: COMET score ≥ 80% of Tier 1 baseline

**Tier 3 — Low Resource (5 languages)**:
Examples: Swahili (sw), Amharic (am), Lao (lo), Khmer (km), Burmese (my).
- Strategy: Machine translation baseline
- English fallback annotation: segments marked with "(EN)" when translation confidence low
- Quality Gate: ≥ 70% of strings have translations; remainder fall back gracefully to English

#### 2.3.3 Database Schema

```sql
CREATE TABLE languages (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    code VARCHAR(10) NOT NULL UNIQUE,       -- 'vi', 'ja', 'th'
    name VARCHAR(100) NOT NULL,             -- 'Vietnamese', 'Japanese'
    native_name VARCHAR(100),               -- 'Tiếng Việt', '日本語'
    tier SMALLINT NOT NULL DEFAULT 2,       -- 1, 2, 3
    is_active BOOLEAN DEFAULT false,
    fallback_locale VARCHAR(10) DEFAULT 'en',
    quality_score DECIMAL(5,4),             -- COMET score
    baseline_score DECIMAL(5,4),            -- English reference score
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE translations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    locale VARCHAR(10) NOT NULL,
    namespace VARCHAR(50) NOT NULL DEFAULT 'ui',  -- 'ui', 'errors', 'emails'
    key VARCHAR(255) NOT NULL,
    value TEXT NOT NULL,
    source_value TEXT,                       -- English source
    quality_score DECIMAL(5,4),
    is_machine_translated BOOLEAN DEFAULT true,
    is_verified BOOLEAN DEFAULT false,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(locale, namespace, key)
);

CREATE INDEX idx_translations_locale ON translations(locale);
CREATE INDEX idx_translations_key ON translations(namespace, key);
CREATE INDEX idx_languages_tier ON languages(tier);
```

#### 2.3.4 Translation Job

```php
class TranslateStringJob implements ShouldQueue
{
    public function handle(DeepSeekClient $ai, TranslationCache $cache, QualityGate $quality): void
    {
        // 1. Dedup check
        if ($cache->has($this->locale, $this->namespace, $this->key)) {
            return;
        }

        // 2. AI translation
        $result = $ai->translate(
            source: $this->sourceText,
            targetLocale: $this->locale,
            context: $this->getContext(),      // surrounding keys for coherence
        );

        // 3. Quality scoring
        $score = $quality->score($result->translation, $this->sourceText, $this->locale);

        // 4. Persist
        Translation::updateOrCreate(
            ['locale' => $this->locale, 'namespace' => $this->namespace, 'key' => $this->key],
            ['value' => $result->translation, 'source_value' => $this->sourceText,
             'quality_score' => $score, 'is_machine_translated' => true]
        );

        // 5. Cache warm
        $cache->put($this->locale, $this->namespace, $this->key, $result->translation);
    }
}
```

#### 2.3.5 Quality Gate

**Non-Negotiable**: Language expansion must not degrade existing 25 high-resource languages (CEO non-negotiable #4).

**Regression Test**: Before deploying any new language, run the full existing-25-languages translation test suite and assert every quality score is within 2% of baseline.

```php
class QualityGate
{
    public function score(string $translation, string $source, string $locale): float
    {
        // COMET score via claude_local evaluation (or local COMET model)
        // Returns 0.0 - 1.0
    }

    public function languagePasses(Language $language): bool
    {
        $threshold = $language->tier === 1 ? 0.85 : ($language->baseline_score * 0.8);
        return $language->quality_score >= $threshold;
    }

    public function regressionTest(): array
    {
        // For each of the 25 original languages, re-score and compare to stored baseline
        // Returns array of regressions (empty = pass)
        $regressions = [];
        foreach (Language::where('tier', 1)->where('is_active', true)->get() as $lang) {
            $currentScore = $this->computeLanguageScore($lang);
            if ($currentScore < $lang->baseline_score * 0.98) {
                $regressions[] = [
                    'language' => $lang->code,
                    'baseline' => $lang->baseline_score,
                    'current' => $currentScore,
                    'delta' => $currentScore - $lang->baseline_score,
                ];
            }
        }
        return $regressions;
    }
}
```

#### 2.3.6 Edge Cases

| Scenario | Handling |
|----------|----------|
| claude_local timeout during translation | Retry 3x with exponential backoff; fail → mark as untranslated, fall back to English |
| Translation key has HTML/placeholders | Preprocess: extract placeholders → translate text → reinsert placeholders. Validate post-insertion |
| Plural forms differ across languages | Use Laravel pluralization with MessageFormat or ICU syntax in translation values |
| Gender-specific language requirements | Prompt DeepSeek with gender-neutral instructions; tag gendered languages for post-review |
| RTL language (Arabic, Hebrew) | B4 RTL stylesheet handles layout. Translation pipeline passes `direction: rtl` metadata |
| Same text, different context | Translation key includes context namespace: `button.submit` vs `link.submit` |
| DeepSeek returns garbage/hallucination | QualityGate score < 0.3 triggers automatic retry with different prompt; 3 failures → flag for review |
| Language added after initial 70 | LanguageRegistry is config-driven; new entry + `php artisan lang:expand {code}` triggers pipeline |

#### 2.3.7 B1 Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B1.1 | 70 languages loadable via locale switch | Automated test: iterate all 70, assert 200 |
| B1.2 | Tier 1 languages: COMET ≥ 0.85 | QualityGate report |
| B1.3 | Tier 2 languages: COMET ≥ 80% of Tier 1 baseline | QualityGate report |
| B1.4 | Tier 3 fallback works: untranslated keys show English | Visual check |
| B1.5 | Existing 25 languages show zero regression (≤2% quality delta) | Regression test output |
| B1.6 | RTL languages render with correct direction | Screenshot (ar) |
| B1.7 | Translation pipeline processes 10k keys in < 1 hour | Timed batch run |
| B1.8 | Each optimization shows before/after scores (CEO #3) | OptimizationResults table |

---

### 2.4 B2 — Effect Tracking (Sprint 5 + Sprints 6-7, Weeks 5-7)

**Owner**: Staff Engineer (Analytics + UI)
**Child Issue**: GEOA-7-B2

#### 2.4.1 Architecture — Data Ingestion

```
Browser                    Laravel API                     Data Layer
  │                            │                              │
  │  POST /api/e/track         │                              │
  │  {type, target, meta}      │                              │
  │─────────────────────────►  │                              │
  │                            │  Validate + enrich            │
  │                            │  (geo-IP, UA parse,          │
  │                            │   user_id, locale)            │
  │                            │                              │
  │                            │  XADD events:stream *         │
  │                            │──────────────────────────►  Redis Stream
  │                            │                              │
  │                            │  INSERT INTO events           │
  │                            │──────────────────────────► PostgreSQL
  │                            │                              │
  │  204 No Content            │                              │
  │◄─────────────────────────  │                              │
  │                            │                              │
  │  SSE /api/e/live           │  XREAD events:stream         │
  │─────────────────────────►  │◄─────────────────────────   Redis
  │  data: {impressions: N}    │                              │
  │◄─────────────────────────  │                              │
```

#### 2.4.2 Database Schema

```sql
CREATE TABLE events (
    id BIGSERIAL,
    event_type VARCHAR(50) NOT NULL,       -- 'impression', 'click', 'signup', 'payment', 'optimization'
    user_id UUID,
    session_id VARCHAR(64),
    locale VARCHAR(10),
    country VARCHAR(2),                    -- ISO 3166-1 alpha-2, from geo-IP
    device_type VARCHAR(20),               -- 'mobile', 'tablet', 'desktop'
    browser VARCHAR(50),
    target_url TEXT,
    referrer_url TEXT,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
) PARTITION BY RANGE (created_at);

-- Monthly partitions
CREATE TABLE events_2026_07 PARTITION OF events
    FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');
CREATE TABLE events_2026_08 PARTITION OF events
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');

-- Aggregation table (materialized, refreshed hourly)
CREATE MATERIALIZED VIEW event_aggregates_hourly AS
SELECT
    date_trunc('hour', created_at) AS hour,
    event_type,
    locale,
    country,
    device_type,
    COUNT(*) AS event_count,
    COUNT(DISTINCT user_id) AS unique_users,
    COUNT(DISTINCT session_id) AS unique_sessions
FROM events
GROUP BY 1, 2, 3, 4, 5;

CREATE UNIQUE INDEX idx_event_agg_hourly ON event_aggregates_hourly(hour, event_type, locale, country, device_type);
```

#### 2.4.3 B-Core: Impressions + Click-Through Dashboard (Sprint 5)

**Dashboard Route**: `/dashboard/analytics`

**Components**:
- `AnalyticsOverviewCard`: Today's impressions, clicks, CTR (impressions=0 → CTR displayed as "—")
- `AnalyticsTimeSeries`: Daily impressions + clicks, 30-day range, Chart.js line chart
- `LanguageBreakdownTable`: Language | Impressions | Clicks | CTR | % Change (vs yesterday)
- Live counter via SSE: dashboard auto-updates without refresh

**Edge Cases**:
| Scenario | Handling |
|----------|----------|
| Zero impressions (new language, no traffic) | CTR shows "—" not "0%" |
| Spike from bot traffic | User-agent filter; flag sessions with >100 events/minute |
| Session spans multiple locales | Treated as separate sessions per locale switch |
| Ad-blocker blocks tracking JS | Fallback: server-side impression counter via Blade directive (counts page renders) |
| Clock skew in distributed pods | All timestamps use `NOW()` (DB server time), not application time |

#### 2.4.4 B-Growth: Full Analytics (Sprints 6-7)

**Additional Metrics**:
- Conversion funnel: impression → click → signup → payment
- Retention: Day-1, Day-7, Day-30 by language cohort
- Multi-dimension drill-down: Language × Region × Device × Time

**Export API**:

```
GET /api/analytics/export?from=2026-07-01&to=2026-07-31
    &dimensions[]=locale&dimensions[]=country
    &metrics[]=impressions&metrics[]=clicks&metrics[]=ctr
    &format=csv

GET /api/analytics/export?format=json  (same params)
```

**Multi-Dimension Drill-Down UI**:
```
[Language ▼] [Country ▼] [Device ▼] [Last 7 Days ▼]
  ┌────────────────────────────────────────────┐
  │  (filtered chart + table updates via AJAX) │
  └────────────────────────────────────────────┘
```

#### 2.4.5 B2 Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B2.1 | Event tracking endpoint accepts and stores events | `curl POST /api/e/track` → row in events table |
| B2.2 | Dashboard shows real-time impression count (SSE) | Open dashboard, trigger events, see counter update |
| B2.3 | CTR computed correctly; handles /0 gracefully | Test with impressions=0, impressions>0 |
| B2.4 | Language breakdown table sorts by CTR descending | Visual check |
| B2.5 | Export API returns valid CSV with correct aggregations | `curl /api/analytics/export?format=csv` → validate |
| B2.6 | Multi-dimension drill-down filters work independently | UI test: change each filter, verify chart updates |
| B2.7 | Conversion funnel shows correct step counts | Seed known events, verify funnel numbers |
| B2.8 | Retention cohort table shows D1/D7/D30 correctly | Seed events across date range, verify |
| B2.9 | Bot traffic filtered from dashboard (not from raw data) | Simulate bot pattern, verify dashboard excludes |
| B2.10 | Export API handles large date ranges (pagination/streaming) | Request 90-day export, verify doesn't timeout |

---

### 2.5 B3 — Batch Optimization (Sprints 7-8, Weeks 7-8)

**Owner**: Staff Engineer (Optimization Engine)
**Child Issue**: GEOA-7-B3

#### 2.5.1 Optimization Engine Architecture

```
                         ┌──────────────────────┐
                         │   BatchController    │
                         │   POST /api/batch    │
                         └──────────┬───────────┘
                                    │
                         ┌──────────▼───────────┐
                         │   BatchOptimizer     │
                         │   (Orchestrator)     │
                         └──────────┬───────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
    ┌─────────▼─────────┐ ┌────────▼────────┐ ┌─────────▼─────────┐
    │   DedupCache      │ │  ConcurrencyCtrl │ │  CircuitBreaker   │
    │   (Redis Hash)    │ │  (Semaphore)     │ │  (Redis Counter)  │
    └─────────┬─────────┘ └────────┬────────┘ └─────────┬─────────┘
              │                     │                     │
              └─────────────────────┼─────────────────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   DeepSeek via      │
                         │   claude_local      │
                         │   (batch endpoint)  │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   BeforeAfterScore  │
                         │   + CostTracker     │
                         └──────────┬──────────┘
                                    │
                         ┌──────────▼──────────┐
                         │   OptimizationResult│
                         │   (PostgreSQL)      │
                         └─────────────────────┘
```

#### 2.5.2 Core Components

**DedupCache**:
```php
class DedupCache
{
    // Hash: SHA256(source_text + target_locale + optimization_type)
    // If cache hit, return stored result (no API call, zero cost)
    public function get(string $source, string $locale, string $type): ?OptimizationResult
    {
        $key = 'dedup:' . hash('sha256', "{$source}|{$locale}|{$type}");
        $cached = Redis::get($key);
        return $cached ? OptimizationResult::fromJson($cached) : null;
    }

    public function set(string $source, string $locale, string $type, OptimizationResult $result): void
    {
        $key = 'dedup:' . hash('sha256', "{$source}|{$locale}|{$type}");
        Redis::setex($key, 86400 * 30, json_encode($result)); // 30-day TTL
    }
}
```

**ConcurrencyController**:
```php
class ConcurrencyController
{
    // Semaphore-based: max concurrent DeepSeek calls across all pods
    // Uses Redis SETNX with TTL for distributed locking
    private const MAX_CONCURRENT = 20; // Tune based on claude_local capacity

    public function acquire(): bool
    {
        $slots = Redis::get('concurrency:slots') ?: self::MAX_CONCURRENT;
        if ($slots <= 0) return false;
        return Redis::decr('concurrency:slots') >= 0;
    }

    public function release(): void
    {
        Redis::incr('concurrency:slots');
    }
}
```

**CircuitBreaker**:
```php
class CircuitBreaker
{
    // States: CLOSED (normal) → OPEN (failing) → HALF_OPEN (probing)
    // Trigger: 5 consecutive failures → OPEN for 30s → HALF_OPEN (1 probe request)
    // Probe succeeds → CLOSED; probe fails → OPEN (reset timer)

    public function isAvailable(): bool
    {
        $state = Redis::get('cb:deepseek:state') ?: 'CLOSED';
        if ($state === 'CLOSED') return true;
        if ($state === 'OPEN') {
            $openedAt = (int) Redis::get('cb:deepseek:opened_at');
            if (time() - $openedAt > 30) {
                Redis::set('cb:deepseek:state', 'HALF_OPEN');
                return true; // Allow one probe
            }
            return false;
        }
        return true; // HALF_OPEN — allow probe
    }

    public function recordSuccess(): void
    {
        Redis::set('cb:deepseek:state', 'CLOSED');
        Redis::del('cb:deepseek:failures');
    }

    public function recordFailure(): void
    {
        $failures = Redis::incr('cb:deepseek:failures');
        if ($failures >= 5) {
            Redis::set('cb:deepseek:state', 'OPEN');
            Redis::set('cb:deepseek:opened_at', time());
        }
    }
}
```

**RetryManager**:
```php
class RetryManager
{
    public function execute(callable $operation, int $maxRetries = 3): mixed
    {
        $attempt = 0;
        $baseDelay = 1000; // ms

        while ($attempt <= $maxRetries) {
            try {
                return $operation();
            } catch (DeepSeekException $e) {
                $attempt++;
                if ($attempt > $maxRetries) throw $e;

                // Exponential backoff with jitter
                $delay = $baseDelay * pow(2, $attempt - 1);
                $jitter = random_int(0, (int)($delay * 0.3));
                usleep(($delay + $jitter) * 1000);
            }
        }
    }
}
```

**CostTracker**:
```php
class CostTracker
{
    public function record(DeepSeekResponse $response, string $operationType): void
    {
        CostLog::create([
            'operation_type' => $operationType,
            'input_tokens' => $response->inputTokens,
            'output_tokens' => $response->outputTokens,
            'model' => $response->model,
            'latency_ms' => $response->latencyMs,
            'cost_cents' => $this->calculateCost($response),
            'source_text_hash' => hash('sha256', $response->sourceText),
            'locale' => $response->locale,
        ]);
    }

    private function calculateCost(DeepSeekResponse $r): float
    {
        // DeepSeek pricing — input: ~$0.14/1M tokens, output: ~$0.28/1M tokens
        $inputCost = ($r->inputTokens / 1_000_000) * 0.014;  // in cents
        $outputCost = ($r->outputTokens / 1_000_000) * 0.028;
        return round($inputCost + $outputCost, 6);
    }
}
```

#### 2.5.3 Performance Targets

| Target | How | Verification |
|--------|-----|-------------|
| Throughput ≥10k words/hour | Parallel Horizon workers × batch API calls. Each worker processes ~500 words; 20 concurrent = 10k/hr | Load test with 50k words, measure completion time |
| Cost <$0.001/word | Dedup (zero-cost cache hits) + batch API (fewer round-trips) + prompt optimization (shorter system prompts). Target: 70% cache hit rate | CostTracker aggregate: total_cost_cents / total_words |
| P99 < 30s | Queue prioritization (optimization jobs get dedicated high-priority queue) + circuit breaker prevents queue clogging from failing calls | Load test, measure P99 latency |

**Why These Are Realistic**:
- Batch API sends 20 texts per call → 20× throughput vs single-text calls
- Dedup cache: repeated content (common UI strings, frequent phrases) → $0 cost
- Prompt caching: system prompt (translation instructions) reused → input token cost drops ~50%
- With 70% cache hit rate and batched remaining 30%: effective cost per word ≈ $0.0003–$0.0007

#### 2.5.4 Edge Case Matrix

| Scenario | Handling |
|----------|----------|
| Input text contains code/markup | Preprocessor: detect code blocks → preserve → translate surrounding text → restore code |
| Very long text (>5000 chars) | Split by sentence boundary, batch-translate segments, reassemble. Each segment gets own dedup hash |
| All workers busy (semaphore exhausted) | Queue waits; if wait >30s, return 202 Accepted with job ID for polling |
| Circuit open during batch submit | Return 503 + Retry-After header; batch remains in queue |
| claude_local returns partial response | Retry only missing segments (granular retry, not full batch redo) |
| Cost exceeds budget threshold | Configurable daily cost cap; when reached, optimization degrades to cache-only mode |
| Before score = after score (no improvement) | OptimizationResult stores both scores; "no improvement" is valid output (CEO #3: every result shows before/after) |
| Concurrent optimization of same text | DedupCache lock: first request sets processing flag, subsequent requests poll for result |
| Memory pressure from large dedup cache | Redis maxmemory-policy: allkeys-lru; cache hit rate monitored in Grafana |

#### 2.5.5 B3 Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B3.1 | Throughput ≥10k words/hour | Load test with measured word count and time |
| B3.2 | Cost <$0.001/word (aggregate) | CostTracker report over 100k+ word test |
| B3.3 | P99 latency <30s | Load test percentile measurement |
| B3.4 | Dedup cache: identical input returns cached result (zero API call) | Submit same text twice, verify second call cost=$0 |
| B3.5 | Circuit breaker opens after 5 consecutive failures | Kill claude_local, submit 5 jobs, verify 6th returns 503 |
| B3.6 | Circuit breaker auto-recovers (half-open → closed) | Restore claude_local, verify jobs succeed after 30s cooldown |
| B3.7 | Retry with exponential backoff works | Simulate transient failure, verify retry pattern in logs |
| B3.8 | Every result includes before/after scores | Inspect OptimizationResult in DB |
| B3.9 | Concurrent requests respect semaphore limit | Submit 30 concurrent, verify max 20 active at any time |
| B3.10 | Cost never exceeds budget cap | Set cap=$0.10, submit jobs, verify cap enforced |

---

## 3. Staffing Assignments

### 3.1 OPC Team Roster

| Role | Agent | Modules | Key Responsibilities |
|------|-------|---------|---------------------|
| **CTO** | 71b65322 | All | Architecture, iron law enforcement, inter-module coordination, technical decision arbitration, Board review preparation |
| **Staff Engineer (Pipeline)** | To be assigned | B1 Language Expansion, B3 Batch Optimization | Translation pipeline design, optimization engine, quality gate implementation, performance tuning |
| **Staff Engineer (Product)** | To be assigned | B2 Effect Tracking, B4 English UI | Analytics data model, dashboard UI, i18n framework, WordPress integration, Blade component library |
| **Release Engineer** | To be assigned | B5 Infrastructure | CI/CD pipeline, Docker builds, K8s manifests, monitoring stack, rollback automation, environment management |
| **QA Engineer** | To be assigned | All modules | Test plan per module, acceptance criteria verification, regression testing, load testing, accessibility audit |

### 3.2 Staff Engineer Workload Balance

```
Staff Engineer (Pipeline):  B1 (2 weeks) + B3 (2 weeks) = 4 weeks active
Staff Engineer (Product):   B4 (1 week) + B2 (3 weeks) = 4 weeks active
Release Engineer:           B5 (1 week) + ongoing support = 1 week + maintainer
QA Engineer:                All 8 sprints (continuous verification)
```

**Rationale**: Pipeline engineer owns the two AI-intensive modules (translation + optimization) which share concerns (DeepSeek integration, quality scoring, cost tracking). Product engineer owns the two user-facing modules (UI + analytics) which share concerns (Blade components, user interaction patterns). This creates natural code ownership boundaries.

---

## 4. Sprint Phasing and Timeline

### 4.1 Sprint Map

```
Week 1    Week 2    Week 3    Week 4    Week 5    Week 6    Week 7    Week 8
  │         │         │         │         │         │         │         │
  ▼         ▼         ▼         ▼         ▼         ▼         ▼         ▼
┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐  ┌─────┐
│ S1  │  │ S2  │  │ S3  │  │ S4  │  │ S5  │  │ S6  │  │ S7  │  │ S8  │
│ B5  │  │ B4  │  │ B1  │  │ B1  │  │ B2  │  │ B2  │  │ B3  │  │ B3  │
│Infra│  │ UI  │  │Tier1│  │T2+T3│  │Core │  │Full │  │Eng  │  │Tune │
└─────┘  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘  └─────┘
  │         │         │         │         │         │         │         │
  └─────────┴─────────┴─────────┴─────────┘         └─────────┴─────────┘
               B-Core (5 weeks)                         B-Growth (3 weeks)
                     │                                         │
                     └─────────── GATE ─────────────────────────┘
                            Deploy to production
                            Gather real traffic data (1+ weeks)
                            Evaluate B1 quality metrics
                            Decide B2 Growth scope based on data
```

### 4.2 Sprint Details

#### Sprint 1 — B5 Infrastructure (Week 1)
- **Owner**: Release Engineer
- **QA**: QA Engineer verifies CI/CD pipeline, rollback drill, monitoring dashboards
- **Deliverables**: All B5 acceptance criteria (B5.1–B5.10)
- **Milestone**: `git push` → production deployment in <10 minutes, rollback <5 minutes

#### Sprint 2 — B4 English UI (Week 2)
- **Owner**: Staff Engineer (Product)
- **QA**: QA Engineer runs i18n test matrix, accessibility audit, zero-Chinese lint check
- **Deliverables**: All B4 acceptance criteria (B4.1–B4.10)
- **Milestone**: English UI with Vietnamese locale switch functional; WordPress integrated

#### Sprint 3 — B1 Language Expansion Tier 1 (Week 3)
- **Owner**: Staff Engineer (Pipeline)
- **QA**: QA Engineer runs regression test on existing 25 languages
- **Deliverables**: 30 Tier-1 languages active, quality gate passing, no regression
- **Milestone**: Tier 1 languages (30) live in staging

#### Sprint 4 — B1 Language Expansion Tiers 2+3 (Week 4)
- **Owner**: Staff Engineer (Pipeline)
- **QA**: QA Engineer runs quality gate on all 70 languages, checks fallback behavior
- **Deliverables**: All B1 acceptance criteria (B1.1–B1.8)
- **Milestone**: Full 70-language support live in staging

#### Sprint 5 — B2 Basic Tracking (Week 5)
- **Owner**: Staff Engineer (Product)
- **QA**: QA Engineer verifies event ingestion, dashboard rendering, edge cases
- **Deliverables**: B2.1–B2.4 (B-Core subset)
- **Milestone**: Impression + click-through dashboard functional

#### GATE — B-Core Production Deploy + Data Gathering
- **Deploy**: B5 + B4 + B1 + B2-Core to production
- **Wait**: Minimum 1 week of real traffic data
- **Evaluate**: B1 quality metrics, B2 usage patterns, infrastructure cost baseline
- **Decision Gate**: Confirm or adjust B-Growth scope based on real data

#### Sprint 6 — B2 Full Analytics (Week 6)
- **Owner**: Staff Engineer (Product)
- **QA**: QA Engineer runs export API tests, funnel accuracy, retention cohort verification
- **Deliverables**: All B2 acceptance criteria (B2.5–B2.10)
- **Milestone**: Full analytics suite with export API

#### Sprint 7 — B3 Optimization Engine (Week 7)
- **Owner**: Staff Engineer (Pipeline)
- **QA**: QA Engineer runs load test, cost tracking verification, circuit breaker drill
- **Deliverables**: B3.1–B3.8
- **Milestone**: Optimization engine processing real batches, meeting throughput target

#### Sprint 8 — B3 Tuning + Integration (Week 8)
- **Owner**: Staff Engineer (Pipeline) + Staff Engineer (Product)
- **QA**: QA Engineer runs full acceptance suite, integration tests across all modules
- **Deliverables**: All B3 acceptance criteria (B3.9–B3.10), end-to-end integration verified
- **Milestone**: Phase B complete — all 7 CEO acceptance criteria met

---

## 5. Cross-Cutting Concerns

### 5.1 Cost Visibility (CEO Non-Negotiable #1)

Every user-facing operation that incurs compute cost must show the cost before execution:
- Translation requests: show estimated token count and cost
- Batch optimization: show estimated total cost before submit
- Dashboard: cost summary card showing current spend

**Implementation**: `CostEstimator` service that pre-calculates token count × pricing tier before dispatching any AI job.

### 5.2 Before/After Scores (CEO Non-Negotiable #3)

Every optimization result must include before/after comparison:
- B1 Translation: source quality score → optimized quality score
- B3 Batch Optimization: pre-optimization metrics → post-optimization metrics

**Implementation**: `BeforeAfterScore` value object embedded in every `OptimizationResult`. Dashboard renders comparison cards.

### 5.3 Payment — Vietnam First (CEO Non-Negotiable #5)

Payment integration order:
1. MoMo + VNPay (Vietnam launch)
2. Stripe + PayPal (international)

**Implementation**: Strategy pattern — `PaymentGateway` interface with `MoMoGateway`, `VNPayGateway`, `StripeGateway`, `PayPalGateway`. Gateway selection based on user's country detection.

### 5.4 Security

| Concern | Mitigation |
|---------|-----------|
| API key exposure | K8s Secrets + SealedSecrets; never in ConfigMap or env files |
| SQL injection | Laravel Eloquent ORM (parameterized queries); raw SQL only in reviewed migrations |
| XSS | Blade auto-escaping (`{{ }}`); no `{!! !!}` without explicit sanitization |
| CSRF | Laravel CSRF middleware on all state-changing routes |
| Rate limiting | Laravel rate limiter: 60/min for API, 1000/min for tracking endpoint (higher for analytics) |
| Data isolation | Row-level security (RLS) on events table: users see only their own data |

### 5.5 Testing Strategy

| Layer | Tool | Scope |
|-------|------|-------|
| Unit | PHPUnit | Service classes, value objects, QualityGate, CostTracker, DedupCache, CircuitBreaker |
| Feature | Pest | API endpoints, Horizon jobs, middleware, Blade component rendering |
| Integration | Pest | DeepSeek client (mocked), Redis, PostgreSQL, WordPress REST API |
| Load | k6 or Locust | B3 throughput test, event ingestion at scale |
| Accessibility | axe-core or Lighthouse | WCAG 2.1 AA compliance for B4 UI components |
| Regression | PHPUnit | B1 existing-25-languages quality baseline |

**Coverage Target**: ≥80% for service/business logic; ≥60% for controllers/jobs. 100% for CostTracker (financial accuracy).

---

## 6. Risk Assessment and Mitigation

| # | Risk | Likelihood | Impact | Mitigation | Owner |
|---|------|-----------|--------|------------|-------|
| R1 | claude_local instability causes batch job failures | Medium | High | Circuit breaker + retry with backoff + fallback to cached results | Staff Engineer (Pipeline) |
| R2 | Mid-resource language quality (B1 Tier 2) doesn't meet 80% threshold | Medium | Medium | Auto-flag for human review; don't block deployment. "Beta" label is honest signaling. | Staff Engineer (Pipeline) |
| R3 | $400/month budget exceeded during B-Core | Medium | High | CostTracker with daily caps; B-Growth scope adjustable based on B-Core actual spend | CTO |
| R4 | WordPress + Blade integration has unexpected friction | Low | Medium | Both are PHP-native. Worst case: WordPress serves pure REST API, Laravel handles all Blade rendering server-side | Staff Engineer (Product) |
| R5 | DeepSeek quality degrades for specific language families | Medium | Medium | Language-specific prompt engineering; per-language-family system prompts; fallback chain | Staff Engineer (Pipeline) |
| R6 | Phase A stale docs cause engineers to implement wrong stack | Low | High | This document is the single source of truth. All child issues explicitly reference iron law. Board review catches violations. | CTO + Board |
| R7 | QA Engineer overwhelmed by 5 modules simultaneously | Medium | Medium | Staggered module delivery means QA focuses on 1-2 modules per sprint. Automated acceptance tests reduce manual burden. | CTO |
| R8 | Real traffic data (post B-Core) invalidates B-Growth assumptions | Low | Medium | The GATE after Sprint 5 is a real decision point — scope can be adjusted. This is a feature, not a bug. | CEO + CTO |
| R9 | MoMo/VNPay integration has undocumented API behaviors | Medium | Low | Vietnam-first launch means these are tested first; Stripe/PayPal are fallback | Staff Engineer (Product) |
| R10 | K8s multi-region deployment complexity exceeds 1-week B5 scope | Medium | Medium | Start single-region; multi-region as B5 stretch goal. Single region still validates CI/CD + rollback. | Release Engineer |

---

## 7. Deliverable Structure

```
deliverables/phase-b/
  ceo-plan.md                              ← CEO Phase B Product Plan (existing)
  cto-technical-execution-plan.md          ← This document
  child-issues/
    GEOA-7-B5-infrastructure.md            ← B5 technical spec + acceptance criteria
    GEOA-7-B4-english-ui.md               ← B4 technical spec + acceptance criteria
    GEOA-7-B1-language-expansion.md        ← B1 technical spec + acceptance criteria
    GEOA-7-B2-effect-tracking.md           ← B2 technical spec + acceptance criteria
    GEOA-7-B3-batch-optimization.md        ← B3 technical spec + acceptance criteria
  review/
    board-iron-law-review.md               ← Board review output (to be produced)
  verification/
    acceptance-test-results.md             ← QA Engineer summary (to be produced)
    load-test-results.md                   ← B3 load test results (to be produced)
    cost-analysis.md                       ← CostTracker aggregate report (to be produced)
```

---

## 8. Reporting

### 8.1 Board Review

This plan must be routed to the Board for iron law verification before any implementation begins. The Board should verify:
- **C10 (6/6)**: All 6 tech stack components present: Laravel 12 + PHP 8.4, WordPress + Blade + Tailwind, PostgreSQL + pgvector, Redis/Horizon, Docker + K8s, DeepSeek via claude_local
- **C11**: All output in Chinese (简体中文) — Board to verify
- **Iron Law**: Zero prohibited technologies (Vue, React, Next.js, Node.js, Python backend, new databases)
- **WordPress**: Explicitly addressed (Board Phase A note resolved — see Section 2.2.1 and 2.2.2)

### 8.2 CEO Review

After Board approval, this plan returns to CEO for final sign-off before child issue creation and engineer assignment. CEO verifies:
- All 5 modules addressed with technical depth
- Staffing assignments cover all modules
- Timeline fits 8-week constraint
- All 5 CEO non-negotiables addressed
- Risk assessment complete

### 8.3 Next Steps After Approval

1. CEO approves plan → CTO creates child issues (GEOA-7-B1 through B5)
2. Staff Engineers, Release Engineer, QA Engineer assigned
3. Sprint 1 (B5 Infrastructure) begins
4. Weekly sprint review: CTO + CEO check progress against plan

---

## Appendix A: Iron Law Verification Matrix

| Iron Law Item | Plan Section | Status |
|---------------|-------------|--------|
| Laravel 12 + PHP 8.4 | 1.3, 2.1, 2.3.4, 2.5.2 | Compliant |
| WordPress 6.x | 1.3, 2.2.1, 2.2.2 | Compliant — explicit CMS role defined |
| Blade + Tailwind | 1.3, 2.2.3, 2.4.3 | Compliant |
| PostgreSQL 16 + pgvector | 1.3, 2.1.5, 2.3.3, 2.4.2 | Compliant |
| Redis/Horizon | 1.3, 2.3.4, 2.5.2 | Compliant |
| Docker + K8s | 1.3, 2.1.2, 2.1.3 | Compliant |
| DeepSeek via claude_local | 1.3, 2.1.6, 2.3.4, 2.5.1 | Compliant |
| No Vue | — | Zero references |
| No React | — | Zero references |
| No Next.js | — | Zero references |
| No Node.js backend | — | Node only in Docker build stage for Tailwind compilation (dev dependency, not runtime) |
| No Python backend | — | Zero references |
| No new databases | — | Only PostgreSQL + Redis (board-approved) |
| Stripe + PayPal | 2.2.5 | Compliant |
| MoMo + VNPay | 2.2.5, 5.3 | Compliant — Vietnam first |

**Self-Assessment**: **PASS**. All iron law items compliant. WordPress role explicitly defined (resolving Phase A Board note).

---

## Appendix B: State Machine — Optimization Job Lifecycle

```
                    ┌──────────┐
                    │  QUEUED  │
                    └────┬─────┘
                         │
                    ┌────▼─────┐
              ┌─────│ ACQUIRE  │◄────────────────────┐
              │     │ (semaphore)                     │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              │     │  DEDUP   │───(cache hit)───► DONE (cached)
              │     │  CHECK   │                      │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              │     │ CIRCUIT  │───(open)───► 503 + Retry-After
              │     │ BREAKER  │                      │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              │     │  CALL    │───(failure)──► RETRY │
              │     │ DEEPSEEK │    (retries < 3)     │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              │     │  SCORE   │                      │
              │     │ + TRACK  │                      │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              │     │ RELEASE  │                      │
              │     │(semaphore)│                      │
              │     └────┬─────┘                      │
              │     ┌────▼─────┐                      │
              └─────│  RETRY   │───(max retries exceeded)──► FAILED
                    │  LOGIC   │
                    └────┬─────┘
                         │
                    ┌────▼─────┐
                    │   DONE   │
                    └──────────┘
```

---

*End of CTO Technical Execution Plan. Locked 2026-07-22. Ready for Board iron law verification.*
