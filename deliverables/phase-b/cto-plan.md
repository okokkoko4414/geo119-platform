# GEO119 Phase B — CTO Technical Execution Plan (SUPERSEDED)

**Date**: 2026-07-22
**Author**: CTO agent (71b65322)
**Status**: **SUPERSEDED** by `cto-technical-execution-plan.md` — this is the v1 draft. Use the locked plan at `deliverables/phase-b/cto-technical-execution-plan.md` for all implementation work.
**Prerequisite**: CEO Phase B Product Plan (approved v1, self-reviewed PASS)
**Budget**: $400/month

---

## 1. Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        GEO119 Phase B                            │
├─────────────────────────────────────────────────────────────────┤
│  B4 English UI            B1 Language Pipeline     B2 Tracking  │
│  ┌─────────────────┐     ┌──────────────────┐    ┌───────────┐ │
│  │ WordPress        │     │ Translation      │    │ Analytics │ │
│  │ Blade/Tailwind   │     │ Engine           │    │ Dashboard │ │
│  │ i18n Framework   │     │ Quality Scorer   │    │ Export API│ │
│  │ SEO Layer        │     │ Lang Pack Mgr    │    │ Privacy   │ │
│  └────────┬────────┘     └────────┬─────────┘    └─────┬─────┘ │
│           │                       │                     │       │
│  ┌────────┴───────────────────────┴─────────────────────┴─────┐ │
│  │                    B3 Batch Optimization Engine             │ │
│  │         Horizon Queue → Batch Processor → Cost Analyzer     │ │
│  └────────────────────────┬───────────────────────────────────┘ │
│                           │                                      │
│  ┌────────────────────────┴───────────────────────────────────┐ │
│  │                      B5 Infrastructure                      │ │
│  │  Docker → K8s → CI/CD → PostgreSQL/pgvector → Redis/Horizon │ │
│  │  Monitoring (Telescope) → Rollback < 5min → Secret Mgmt    │ │
│  └─────────────────────────────────────────────────────────────┘ │
│                           │                                      │
│  ┌────────────────────────┴───────────────────────────────────┐ │
│  │                   AI Layer (All Modules)                    │ │
│  │              DeepSeek via claude_local                      │ │
│  │         Prompt Cache → Response Cache → Rate Limiter        │ │
│  └─────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

**Data Flow**: User → WordPress/Blade UI → Laravel API → PostgreSQL/pgvector ← DeepSeek AI ← claude_local

**Queue Flow**: Laravel Job → Redis/Horizon Queue → Horizon Worker → Result → PostgreSQL

---

## 2. Module Technical Design

### 2.1 B5 — Infrastructure (1 week, Release Engineer)

**Goal**: Full CI/CD pipeline. Dev pushes code, everything else is automatic.

**Subsystems**:

| Subsystem | Tech | Key Decisions |
|-----------|------|---------------|
| Container | Docker multi-stage build | Separate build vs runtime images; PHP-FPM + Nginx sidecar |
| Orchestration | K8s (k3s for cost) | Single-node k3s; namespace per environment (dev/staging/prod) |
| CI/CD | GitHub Actions + Docker Compose for local | Push → build → test → deploy to k3s; rollback = kubectl rollout undo |
| Database | PostgreSQL 16 + pgvector | One DB per environment; pgvector extension for AI embeddings |
| Queue | Redis 7 + Laravel Horizon | Horizon dashboard for monitoring; per-module queue namespaces |
| Monitoring | Laravel Telescope + Redis | Telescope for dev/staging; Redis metrics for prod |
| Secrets | K8s Secrets + .env.gitignore | Never commit .env; K8s secrets for prod; Laravel env() for access |
| WordPress | WP core in container | WP shares Redis with Laravel; separate DB or schema |

**CI/CD Pipeline**:
```
git push → lint (PHPStan level 8) → unit tests → build image →
  → push to registry → kubectl set image → health check → done
  rollback: kubectl rollout undo deployment/geo119 --to-revision=N
```

**Acceptance**:
- Push-to-deploy < 5 min
- Rollback < 5 min (single command)
- All secrets in K8s secrets, zero in codebase
- Horizon dashboard accessible
- Telescope accessible in dev/staging

**Files to create** (approximate):
- `Dockerfile` (multi-stage, ~40 lines)
- `docker-compose.yml` (dev, ~60 lines)
- `k8s/` directory: deployment, service, ingress, configmap, secrets-template (~200 lines total)
- `.github/workflows/deploy.yml` (~80 lines)
- `horizon.conf` (queue config, ~30 lines)

---

### 2.2 B4 — English UI (1 week, Staff Engineer)

**Goal**: UI reads like a native English product, not a translated Chinese one.

**Subsystems**:

| Subsystem | Approach | Iron Law Check |
|-----------|----------|----------------|
| WordPress Theme | Blade templates in WP theme; Tailwind CSS for styling | WP + Blade + Tailwind ✓ |
| i18n Framework | Laravel `__()` + JSON lang files; WP `__()` for theme strings | PHP native ✓ |
| SEO | WP permalinks → `/en/`, `/zh/`, etc.; Yoast-style meta via custom code; JSON-LD structured data | No new plugins required ✓ |
| Responsive | Tailwind breakpoints (sm/md/lg/xl); mobile-first component design | No JS framework ✓ |
| String Audit | Grep all Chinese characters → extract to lang files → English rewrite | Process, not tech ✓ |

**String Extraction Process**:
1. Scan all `.blade.php`, `.php` files for Chinese chars (regex: `\p{Han}`)
2. Replace with `__('string.key')` calls
3. Generate `lang/en.json` with English translations
4. Native English speaker review (product acceptance)

**SEO Checklist**:
- `<title>` per page, English
- `<meta name="description">` per page
- `hreflang` tags for language alternates
- `canonical` URL per page
- JSON-LD structured data (WebSite, WebApplication)
- `robots.txt` and `sitemap.xml` (auto-generated)
- Open Graph tags (og:title, og:description, og:image)

**Acceptance**:
- Zero hardcoded Chinese strings in UI
- All strings use `__()` or `@lang()` helpers
- English native speaker walkthrough: "reads like native product"
- Mobile responsive: tested at 320px, 768px, 1024px, 1440px
- Lighthouse SEO score ≥ 90

**Files to create/modify** (approximate):
- `lang/en.json` (~500 keys)
- `lang/zh.json` (existing strings, ~500 keys)
- WP theme `functions.php` updates
- ~15-20 Blade template updates
- `resources/css/app.css` (Tailwind)
- SEO partial: `seo-meta.blade.php`

---

### 2.3 B1 — Language Extension 25→70 (2 weeks, Staff Engineer)

**Goal**: 70 languages loadable, mid-resource languages pass blind "understandable" test at > 90%.

**Architecture**:

```
Language Pipeline
┌────────────┐    ┌──────────────┐    ┌───────────────┐    ┌──────────┐
│ Source     │───▶│ Translation  │───▶│ Quality       │───▶│ Lang     │
│ Strings    │    │ Engine       │    │ Scorer        │    │ Pack     │
│ (en.json)  │    │ (DeepSeek)   │    │ (DeepSeek)    │    │ Output   │
└────────────┘    └──────────────┘    └───────────────┘    └──────────┘
                         │                    │
                         ▼                    ▼
                  ┌──────────────┐    ┌──────────────┐
                  │ Translation  │    │ Fallback      │
                  │ Cache (Redis)│    │ Chain         │
                  └──────────────┘    └──────────────┘
```

**Language Tiers**:

| Tier | Count | Strategy | Quality Gate |
|------|-------|----------|--------------|
| Tier 1 (high-resource) | 25 existing | Full auto translation + auto quality check | BLEU + chrF score threshold |
| Tier 2 (mid-resource) | 45 new | Translation + key term human checklist + fallback to Tier 1 | Blind "understandable" > 90% |
| Tier 3 (low-resource) | remaining to 70 | Machine translation + English fallback badge | "beta" label, English fallback functional |

**Translation Engine Design**:

```php
// Core flow
class TranslationPipeline {
    public function translate(string $text, string $targetLang): TranslationResult {
        // 1. Check cache
        if ($cached = Cache::get("trans:{$targetLang}:" . md5($text))) {
            return $cached;
        }
        // 2. DeepSeek translation via claude_local
        $result = DeepSeek::translate($text, $targetLang);
        // 3. Quality scoring for mid-resource
        if ($this->isTier2($targetLang)) {
            $result->qualityScore = DeepSeek::scoreQuality($result->translated, $targetLang);
        }
        // 4. Cache and return
        Cache::put("trans:{$targetLang}:" . md5($text), $result, now()->addDays(30));
        return $result;
    }
}
```

**Quality Scoring**:
- Automated: DeepSeek evaluates translation on fluency, adequacy, terminology
- Threshold: score ≥ 0.85 for Tier 2 languages
- Below threshold → flagged for human review → fallback to Tier 1 language

**Fallback Chain**: `targetLang → regional_high_resource → english`
Example: `vi_VN → zh_CN → en`

**DeepSeek Prompt Template** (cached):
```
System: You are a professional translator. Translate the following text from English to {language_name}. Maintain technical terminology accuracy. Preserve HTML tags and placeholders like :name or %count%.
User: {source_text}
```

**Acceptance**:
- 70 languages loadable via `/?lang=xx` or `Accept-Language` header
- Tier 2 random sample (10 languages, 3 native speakers each): "understandable" > 90%
- Translation cache hit rate > 60% for repeated strings
- Fallback chain works for all Tier 3 languages

**Files to create/modify** (approximate):
- `app/Services/TranslationPipeline.php` (~150 lines)
- `app/Services/QualityScorer.php` (~80 lines)
- `app/Jobs/TranslateLanguagePack.php` (~60 lines)
- `app/Models/LanguagePack.php` (migration + model, ~40 lines)
- `config/languages.php` (70 language definitions, ~200 lines)
- Lang pack files: `lang/{xx}/*.json` (70 × ~500 keys, generated)
- Horizon job config for translation queue

---

### 2.4 B2 — Effect Tracking (1 week B-Core + 1.5 weeks B-Growth, Staff Engineer)

**Goal B-Core**: Answer "Are new language market users using the product?"
**Goal B-Growth**: Answer "Are they staying? Why?"

**Data Model**:

```sql
-- B-Core
CREATE TABLE tracking_events (
    id BIGSERIAL PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,        -- 'exposure', 'click'
    language VARCHAR(10) NOT NULL,
    page_url TEXT NOT NULL,
    element_id VARCHAR(100),
    user_agent TEXT,
    ip_hash VARCHAR(64),                     -- hashed for privacy
    session_id VARCHAR(64),
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_events_type_lang ON tracking_events(event_type, language, created_at);
CREATE INDEX idx_events_session ON tracking_events(session_id);

-- B-Growth additions
CREATE TABLE tracking_conversions (
    id BIGSERIAL PRIMARY KEY,
    session_id VARCHAR(64) NOT NULL,
    conversion_type VARCHAR(50) NOT NULL,    -- 'signup', 'purchase', 'return_visit'
    language VARCHAR(10) NOT NULL,
    value DECIMAL(10,2),
    metadata JSONB,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE tracking_sessions (
    session_id VARCHAR(64) PRIMARY KEY,
    language VARCHAR(10) NOT NULL,
    region VARCHAR(10),                      -- from IP geolocation
    device_type VARCHAR(20),                 -- 'mobile', 'tablet', 'desktop'
    first_event_at TIMESTAMP,
    last_event_at TIMESTAMP,
    event_count INT DEFAULT 0
);
```

**Tracking Flow**:
```
User Action → JS beacon (10-line vanilla JS, no framework) →
  POST /api/track → Laravel controller →
    validate → Redis buffer (list push) →
      Horizon job (batch flush every 30s or 1000 events) →
        PostgreSQL INSERT batch
```

**Why Redis buffer?** Write amplification — individual INSERT per event would hammer the DB. Buffer in Redis list, batch flush.

**Dashboard (B-Core)**:
- Blade-rendered page (no SPA)
- Charts via blade + inline SVG or minimal Chart.js (vanilla JS, CDN, no npm)
- Metrics: events/minute by language, top pages, device breakdown

**Dashboard (B-Growth)**:
- Conversion funnel by language
- Retention cohort (daily/weekly)
- Multi-dimension drill-down: language × region × device × time
- Export API: `GET /api/analytics/export?from=...&to=...&lang=...&format=csv`

**Export API** (B-Growth):
```
GET /api/analytics/export
  ?from=2026-08-01
  &to=2026-08-31
  &lang=vi,th,id
  &metrics=exposure,click,conversion
  &dimensions=language,device
  &format=csv|json
```

**Privacy**:
- IP hashed (SHA-256) before storage, never stored raw
- No cookies for tracking (use session ID in localStorage)
- No PII in event payload
- Data retention: raw events 90 days, aggregated forever
- GDPR: no personal data collected, but document what IS collected

**Acceptance (B-Core)**:
- Events fire and appear in dashboard within 30 seconds
- Dashboard shows breakdown by language
- Zero console errors in tracking beacon

**Acceptance (B-Growth)**:
- Conversion funnels render for any language filter
- Export API returns correct CSV/JSON
- Multi-dimension drill-down works (language × device verified)

**Files to create/modify** (approximate):
- Migrations: 3 files (~60 lines each)
- `app/Models/TrackingEvent.php`, `TrackingConversion.php`, `TrackingSession.php`
- `app/Http/Controllers/Api/TrackController.php` (~40 lines)
- `app/Jobs/FlushTrackingEvents.php` (~50 lines)
- `resources/js/tracking-beacon.js` (~20 lines vanilla JS)
- `resources/views/analytics/dashboard.blade.php` (~150 lines)
- `resources/views/analytics/funnels.blade.php` (B-Growth, ~100 lines)
- `app/Http/Controllers/Api/AnalyticsExportController.php` (B-Growth, ~60 lines)
- `routes/api.php` additions (~20 lines)

---

### 2.5 B3 — Batch Optimization Engine (1.5 weeks, Staff Engineer)

**Goal**: Make $400/month budget deliver $4000/month value. Cost optimization that pays for itself.

**Hard Constraints**:
| Constraint | Value | Measurement |
|------------|-------|-------------|
| Throughput | ≥ 10,000 items/hour | Horizon metrics |
| Cost per word | < $0.001 | DeepSeek token counter + cost calc |
| P99 latency | < 30s per item | Horizon job timing |
| Self-cost | < savings generated | A/B comparison |

**Architecture**:

```
Batch Optimization Pipeline
┌──────────┐    ┌──────────────┐    ┌──────────────┐    ┌──────────┐
│ Input    │───▶│ Candidate    │───▶│ Optimizer    │───▶│ Output   │
│ Queue    │    │ Generator    │    │ (DeepSeek)   │    │ Queue    │
│ (Redis)  │    │              │    │              │    │ (Redis)  │
└──────────┘    └──────────────┘    └──────────────┘    └──────────┘
                       │                    │
                       ▼                    ▼
                ┌──────────────┐    ┌──────────────┐
                │ Dedup Cache  │    │ Cost Tracker │
                │ (Redis Set)  │    │ (Redis Hash) │
                └──────────────┘    └──────────────┘
```

**Optimization Types**:
1. **Translation Memory**: Cache translations at phrase level, reuse across language packs
2. **Prompt Optimization**: Reduce token count by trimming prompts while preserving quality
3. **Batch Processing**: Group similar translations to amortize DeepSeek overhead
4. **Quality Tiering**: Tier 1 gets full processing, Tier 3 gets minimal (cheaper) passes

**Cost Tracking**:
```php
class CostTracker {
    public function recordOptimization(string $type, int $tokensBefore, int $tokensAfter, float $costBefore, float $costAfter): void {
        Redis::hincrby("opt:{$type}:tokens_saved", 0, $tokensBefore - $tokensAfter);
        Redis::hincrbyfloat("opt:{$type}:cost_saved", 0, $costBefore - $costAfter);
    }

    public function savingsReport(): array {
        // Returns total tokens saved, total cost saved, savings rate
    }
}
```

**Batch Processing Strategy**:
```
Single item: 1 DeepSeek call = 1 round-trip overhead
Batch of 50:  1 DeepSeek call = 1 round-trip overhead ÷ 50 items
Savings: ~49× reduction in API overhead per item
```

Batch window: collect items for 5 seconds or until 50 items, whichever comes first.

**A/B Comparison Framework**:
- Run same workload through optimized and unoptimized paths
- Compare: total tokens consumed, total cost, wall-clock time, quality scores
- Report: savings % and whether quality degraded

**Acceptance**:
- Throughput: process 10,000 items and verify elapsed time < 1 hour
- Cost: sample 1,000 translations, verify average cost < $0.001/word
- P99: sample 1,000 items, verify P99 processing time < 30s
- Self-cost test: run A/B comparison, verify optimizer cost < savings

**Files to create/modify** (approximate):
- `app/Services/OptimizationPipeline.php` (~120 lines)
- `app/Services/CostTracker.php` (~60 lines)
- `app/Jobs/OptimizeBatchJob.php` (~80 lines)
- `app/Services/Optimizers/TranslationMemoryOptimizer.php` (~80 lines)
- `app/Services/Optimizers/PromptOptimizer.php` (~60 lines)
- `app/Services/Optimizers/BatchAggregator.php` (~50 lines)
- `config/optimization.php` (~40 lines)
- Horizon queue config for optimizer queue

---

## 3. Task Breakdown → Sub-Issues

```
GEOA-8 (CTO Technical Plan — this document)
│
├── GEOA-9  B5 Infrastructure (Release Engineer, 1 week)
│   ├── Docker multi-stage build + docker-compose
│   ├── K8s manifests (deployment, service, ingress, configmap, secrets)
│   ├── CI/CD pipeline (GitHub Actions: lint → test → build → deploy)
│   ├── PostgreSQL/pgvector setup + migration automation
│   ├── Redis/Horizon queue setup + monitoring
│   ├── WordPress container integration
│   └── Rollback script + health check endpoint
│
├── GEOA-10 B4 English UI (Staff Engineer, 1 week)
│   ├── Chinese string audit (grep all Han chars)
│   ├── i18n framework: replace hardcoded strings with __() / @lang()
│   ├── lang/en.json: English translations (~500 keys)
│   ├── SEO infrastructure (meta, hreflang, canonical, JSON-LD, sitemap)
│   ├── Responsive Tailwind pass (mobile-first at 320/768/1024/1440)
│   └── English native speaker copy review placeholders
│
├── GEOA-11 B1 Language Extension 25→70 (Staff Engineer, 2 weeks)
│   ├── TranslationPipeline service (DeepSeek integration via claude_local)
│   ├── QualityScorer service (automated quality assessment)
│   ├── LanguagePack model + migration (70 language definitions)
│   ├── Translation cache layer (Redis, phrase-level dedup)
│   ├── Fallback chain implementation (target → regional → english)
│   ├── Tier 1 translation run (25 existing, verify baseline)
│   ├── Tier 2 translation run (45 new, quality-checked)
│   ├── Tier 3 translation run (remaining, with beta badge)
│   └── Quality validation: 10-language random sample blind test
│
├── GEOA-12 B2 Effect Tracking (Staff Engineer, 2.5 weeks total)
│   ├── [B-Core, 1 week]
│   │   ├── tracking_events migration + model
│   │   ├── Tracking beacon JS (vanilla, 20 lines)
│   │   ├── TrackController (API endpoint + Redis buffer)
│   │   ├── FlushTrackingEvents job (batch flush 30s/1000)
│   │   └── B-Core dashboard (Blade, events by language + page)
│   └── [B-Growth, 1.5 weeks]
│       ├── tracking_conversions + tracking_sessions migrations/models
│       ├── Conversion funnel dashboard (Blade)
│       ├── Retention cohort view
│       ├── Multi-dimension drill-down (lang × region × device × time)
│       └── Export API (CSV/JSON, filterable)
│
├── GEOA-13 B3 Batch Optimization Engine (Staff Engineer, 1.5 weeks)
│   ├── OptimizationPipeline service
│   ├── TranslationMemoryOptimizer (phrase-level cache)
│   ├── PromptOptimizer (token reduction)
│   ├── BatchAggregator (5s/50-item window)
│   ├── CostTracker service (Redis-based cost accounting)
│   ├── A/B Comparison framework (optimized vs unoptimized)
│   └── Horizon queue config for optimizer workers
│
├── GEOA-14 QA & Acceptance (QA Engineer, ongoing throughout)
│   ├── B5: CI/CD rollback drill, secret audit
│   ├── B4: Chinese string scan (zero Han chars outside lang files), Lighthouse SEO audit
│   ├── B1: 70-language load check, Tier 2 10-language blind test
│   ├── B2: Event pipeline latency check (< 30s), export API correctness
│   ├── B3: Throughput test (10k/hr), cost test (< $0.001/word), P99 test (< 30s)
│   └── Iron Law compliance audit (full checklist, per module)
│
└── GEOA-15 Final Integration & Release (Release Engineer, 0.5 week)
    ├── Full-stack integration test
    ├── K8s production deployment
    ├── Performance smoke test
    └── Deliverables archive in deliverables/phase-b/
```

**Dependency Graph**:
```
GEOA-9 (B5) ──────┬──▶ GEOA-10 (B4) ──┬──▶ GEOA-12 (B2 B-Core)
                   │                   │
                   ├──▶ GEOA-11 (B1) ──┼──▶ GEOA-13 (B3)
                   │                   │
                   └──▶ GEOA-14 (QA) ◀─┘
                            │
                            ▼
                      GEOA-15 (Release)
```

---

## 4. Execution Timeline

```
Week 1        Week 2        Week 3        Week 4        Week 5
│             │             │             │             │
│ B5 Infra    │             │             │             │
│ ████████████│             │             │             │
│             │ B4 UI       │             │             │
│             │ ████████████│             │             │
│             │ B1 Lang Ext │             │             │
│             │ █████████████████████████████████████  │
│             │             │ B2 B-Core   │             │
│             │             │       ████████████       │
│             │ QA ongoing  │             │             │
│             │ ████████████████████████████████████████│
│             │             │             │             │
├─────────────┴─────────────┴─────────────┴─────────────┤
│              B-CORE COMPLETE — DEPLOY & COLLECT DATA   │
├───────────────────────────────────────────────────────┤

Week 6        Week 7        Week 8
│             │             │
│ B2 Growth   │             │
│ ██████████████████        │
│             │ B3 Batch Opt│
│             │ ████████████████████
│ QA ongoing  │             │
│ ██████████████████████████████████
│             │             │
├─────────────┴─────────────┤
│    B-GROWTH COMPLETE       │
├───────────────────────────┤
│    FINAL RELEASE (GEOA-15) │
└───────────────────────────┘
```

**Total: 8 weeks** (B-Core 5 + B-Growth 3)

**Parallelization note**: B4 and B1 could run in parallel (different engineers), but CEO chose serial for safety — B4 may surface infrastructure gaps. CTO agrees: serial B5→B4→B1 is lower risk.

---

## 5. Iron Law Compliance Checklist

### 5.1 Tech Stack Iron Laws

| # | Law | Status | Verification |
|---|------|--------|--------------|
| IL-1 | Backend: Laravel 12 + PHP 8.4 | ✓ | `composer.json` check: `"laravel/framework": "^12.0"`, `"php": "^8.4"` |
| IL-2 | Frontend: WordPress + Blade + Tailwind | ✓ | No `package.json` with Vue/React; only Tailwind CSS; Blade templates only |
| IL-3 | Database: PostgreSQL 16 + pgvector | ✓ | `config/database.php` default = pgsql; pgvector extension enabled |
| IL-4 | Queue: Redis 7 + Laravel Horizon | ✓ | `config/queue.php` default = redis; Horizon installed |
| IL-5 | Container: Docker | ✓ | `Dockerfile` + `docker-compose.yml` present; K8s manifests for prod |
| IL-6 | AI: DeepSeek via claude_local ONLY | ✓ | No OpenAI/Anthropic SDK imports; only claude_local with DeepSeek backend |
| IL-7 | NO Vue.js | ✓ | Grep `vue` in codebase → zero results |
| IL-8 | NO React | ✓ | Grep `react` in codebase → zero results |
| IL-9 | NO Next.js | ✓ | Grep `next` in codebase → zero results |
| IL-10 | NO Node.js backend | ✓ | No `server.js`, `app.js`, Express imports |
| IL-11 | NO Python backend | ✓ | No `.py` files in backend paths; no Flask/Django/FastAPI |
| IL-12 | NO new database (MongoDB, MySQL, etc.) | ✓ | Only PostgreSQL in `config/database.php` |

### 5.2 Architectural Iron Laws

| # | Law | Status | Verification |
|---|------|--------|--------------|
| IL-13 | All AI calls go through claude_local | ✓ | No direct HTTP calls to AI APIs; single `ClaudeLocal` service class |
| IL-14 | No SPA — Blade server-rendered pages | ✓ | No `app.js` mounting a root div; Blade templates render full pages |
| IL-15 | Vanilla JS only for interactivity (tracking beacon, chart rendering) | ✓ | No framework imports in JS files; < 50 lines per JS file |
| IL-16 | WordPress as presentation layer, Laravel as API layer | ✓ | WP uses Laravel REST API; Laravel never serves HTML directly to end users |
| IL-17 | All secrets in K8s secrets or .env (gitignored) | ✓ | Zero hardcoded keys/tokens/passwords in committed code |

### 5.3 Process Iron Laws

| # | Law | Status | Verification |
|---|------|--------|--------------|
| IL-18 | All deliverables in `deliverables/phase-b/` | ✓ | File manifest check |
| IL-19 | Every module has acceptance tests (QA Engineer) | ✓ | QA issue (GEOA-14) covers all modules |
| IL-20 | Code review before merge (Staff Engineer reviews Staff Engineer) | ✓ | Cross-review: B1/B3 engineer reviews B2/B4, and vice versa |
| IL-21 | No implementation without approved technical plan | ✓ | This document serves as the gate |

---

## 6. Token Estimates

### 6.1 Per-Module Token Budget

Token costs assume DeepSeek via claude_local at approximately $0.14/1M input tokens, $0.28/1M output tokens (DeepSeek-V3 pricing).

| Module | Agent Type | Input Tokens | Output Tokens | Total Tokens | Est. Cost |
|--------|-----------|-------------|---------------|-------------|-----------|
| B5 Infrastructure | Release Engineer | 300K | 200K | 500K | ~$0.10 |
| B4 English UI | Staff Engineer | 400K | 300K | 700K | ~$0.14 |
| B1 Language Extension | Staff Engineer + DeepSeek translation | 1.5M | 1.0M | 2.5M | ~$0.49 |
| B2 B-Core Tracking | Staff Engineer | 300K | 250K | 550K | ~$0.11 |
| B2 B-Growth Tracking | Staff Engineer | 250K | 200K | 450K | ~$0.09 |
| B3 Batch Optimization | Staff Engineer | 400K | 300K | 700K | ~$0.14 |
| QA & Acceptance | QA Engineer | 500K | 300K | 800K | ~$0.16 |
| Final Integration | Release Engineer | 200K | 150K | 350K | ~$0.07 |
| CTO Oversight | CTO | 200K | 100K | 300K | ~$0.06 |
| **Total (Agent tokens)** | | **4.05M** | **2.80M** | **6.85M** | **~$1.36** |

### 6.2 Translation Tokens (B1 — the big item)

For 70 languages × ~500 UI keys × average 5 words per key:

| Item | Count | Tokens per item | Total Tokens | Est. Cost |
|------|-------|-----------------|-------------|-----------|
| Translation calls (70 × 500) | 35,000 | ~200 tokens avg | 7.0M | ~$1.40 |
| Quality scoring calls (45 × 500) | 22,500 | ~150 tokens avg | 3.38M | ~$0.68 |
| **Total translation tokens** | | | **10.38M** | **~$2.08** |

### 6.3 Grand Total

| Category | Est. Cost |
|----------|-----------|
| Agent execution tokens | ~$1.36 |
| Translation tokens (DeepSeek) | ~$2.08 |
| Infrastructure (K8s/DB/Redis) | ~$80.00 |
| **Total estimated technical cost** | **~$83.44** |

**Remaining from $400 budget: ~$316.56 for agent orchestration, unexpected re-runs, and buffer.**

---

## 7. Caching Strategy

### 7.1 Cache Layers

```
┌─────────────────────────────────────────────────────┐
│ Layer 1: Prompt Cache (Anthropic/Claude Code level) │
│ - Reused system prompts for all agents              │
│ - TTL: 5 minutes (Anthropic prompt cache)           │
│ - Strategy: batch similar agent calls within 5 min  │
├─────────────────────────────────────────────────────┤
│ Layer 2: Translation Cache (Redis)                  │
│ - Key: trans:{lang}:md5(source_text)                │
│ - TTL: 30 days                                      │
│ - Hit rate target: > 60% (shared strings)           │
├─────────────────────────────────────────────────────┤
│ Layer 3: DeepSeek Response Cache (Redis)            │
│ - Key: ds:{model}:md5(prompt)                       │
│ - TTL: 24 hours                                     │
│ - For identical AI calls across modules             │
├─────────────────────────────────────────────────────┤
│ Layer 4: Laravel App Cache                          │
│ - Route cache: php artisan route:cache              │
│ - Config cache: php artisan config:cache            │
│ - View cache: Blade compiled templates              │
│ - TTL: until next deploy                            │
├─────────────────────────────────────────────────────┤
│ Layer 5: WordPress Object Cache (Redis)             │
│ - WP transients, page cache fragments               │
│ - TTL: varies by content type                       │
├─────────────────────────────────────────────────────┤
│ Layer 6: HTTP Cache (Nginx/Laravel headers)         │
│ - ETag + Cache-Control for static translations      │
│ - TTL: 1 hour for lang packs                       │
└─────────────────────────────────────────────────────┘
```

### 7.2 Caching Rules

1. **Always cache translations.** Translation is the most expensive operation in Phase B. Cache at phrase level (not just full-string) to maximize reuse.
2. **Never cache user tracking data.** It's write-heavy, not read-heavy.
3. **Pre-warm translation cache** for top 100 UI strings across all 70 languages before going live.
4. **DeepSeek optimization cache**: Cache optimized prompts and their results. Same input → same optimized output.
5. **Prompts are cacheable**: System prompts for translation, quality scoring, and optimization are static — cache them aggressively.
6. **Horizon metrics are not cached**: Real-time queue data must be live.

### 7.3 Cache Invalidation Strategy

| Cache Layer | Invalidation Trigger |
|-------------|---------------------|
| Translation Cache | UI string change (lang file update) → delete matching `trans:{lang}:*` keys |
| DeepSeek Cache | 24h TTL auto-expire; manual flush on model version change |
| Laravel Cache | Deploy (artisan commands in CI/CD) |
| WP Object Cache | Content update hooks |
| HTTP Cache | Deploy + ETag change |

---

## 8. Budget Breakdown ($400/month)

### 8.1 Monthly Allocation

| Category | Monthly Budget | % | Details |
|----------|---------------|----|---------|
| **Agent Orchestration** | | | |
| CTO (oversight, code review) | $10 | 2.5% | 2-3 oversight sessions/month |
| Staff Engineer ×2 (B1-B4) | $80 | 20% | 2 engineers × ~$40/month each |
| QA Engineer | $30 | 7.5% | Continuous testing across 8 weeks |
| Release Engineer | $30 | 7.5% | B5 + CI/CD + final release |
| **AI Inference** | | | |
| DeepSeek Translation (B1) | $50 | 12.5% | 70 languages × 500 keys |
| DeepSeek Quality Scoring (B1) | $20 | 5% | 45 mid-resource languages |
| DeepSeek Optimization (B3) | $20 | 5% | Batch optimization runs |
| DeepSeek Misc (B2/B4) | $10 | 2.5% | Dashboard descriptions, SEO content |
| **Infrastructure** | | | |
| K8s (k3s single node) | $40 | 10% | VPS hosting K8s + DB + Redis |
| PostgreSQL Managed / VPS portion | $25 | 6.25% | Includes pgvector extension |
| Redis Managed / VPS portion | $15 | 3.75% | Includes persistent storage |
| **Buffer** | | | |
| Unforeseen re-runs / debugging | $40 | 10% | Token waste from failed attempts |
| Emergency / overage | $30 | 7.5% | Cost overrun buffer |
| **TOTAL** | **$400** | **100%** | |

### 8.2 Per-Module Total Cost (8-week execution)

| Module | Agent Cost | AI Cost | Infra Cost | Total |
|--------|-----------|---------|------------|-------|
| B5 Infrastructure | $10 | $0 | $20 | $30 |
| B4 English UI | $20 | $5 | $5 | $30 |
| B1 Language Extension | $30 | $55 | $15 | $100 |
| B2 Effect Tracking | $25 | $5 | $10 | $40 |
| B3 Batch Optimization | $20 | $20 | $10 | $50 |
| QA & Release | $25 | $0 | $10 | $35 |
| CTO Oversight | $10 | $0 | $0 | $10 |
| Buffer | — | — | $10 | $10 |
| **Project Total** | **$140** | **$85** | **$80** | **$295** |

**$105 remaining for 8-week period** — well within $400/month × 2 months = $800 total budget.

### 8.3 Cost Optimization Measures

1. **Translation Memory**: Phrase-level caching reduces Duplicate DeepSeek calls by estimated 40-60%
2. **Prompt Compression**: Shorter prompts = fewer tokens. Target 20% reduction in B1 prompts.
3. **Batch Scheduling**: Run DeepSeek batch jobs during off-peak (cheaper if time-of-day pricing exists)
4. **Tiered Processing**: Tier 3 languages get minimal (cheap) passes
5. **Agent Token Discipline**: Keep agent conversations focused. Staff Engineers get capped-context task briefs.
6. **Cache Everything**: Prompt cache + response cache + translation cache + Laravel cache

---

## 9. Risk Register

| # | Risk | Probability | Impact | Mitigation | Owner |
|---|------|------------|--------|------------|-------|
| R1 | DeepSeek translation quality below threshold for Tier 2 languages | Medium | High | Automatic quality scoring with fallback chain; flag low-quality languages as "beta" | Staff Engineer (B1) |
| R2 | DeepSeek inference cost exceeds estimates | Medium | Medium | Token counter per job; $30 hard cap on translation spend; pause and report if exceeded | CTO |
| R3 | K8s complexity overkill for single-node deployment | Low | Medium | Start with docker-compose for dev; graduate to k3s only if needed for production | Release Engineer |
| R4 | WordPress + Laravel integration friction (auth, routing) | Medium | Medium | Keep WP and Laravel as separate containers; WP talks to Laravel via REST API only | Staff Engineer (B4) |
| R5 | Horizon queue backlog under load (10k items/hr in B3) | Medium | High | Horizontal worker scaling; queue depth monitoring with alert threshold | Release Engineer |
| R6 | Translation cache cold start (first load of all 70 languages) | High | Medium | Pre-warm top 100 strings during deploy; progressive loading for remaining | Staff Engineer (B1) |
| R7 | Staff Engineer agent quality inconsistent | Medium | Medium | Cross-review between engineers; QA Engineer independent verification; CTO spot-check | CTO |
| R8 | $400/month budget insufficient for full execution | Low | High | B-Core first (costs less); B-Growth adjusted based on remaining budget | CEO |
| R9 | English UI reads as "translated" not "native" | Medium | Medium | Multiple review passes; native speaker review; rewrite not just translate | Staff Engineer (B4) + QA |

---

## 10. Success Criteria Mapping

| CEO Acceptance Standard | Technical Verification | Owner |
|-------------------------|----------------------|-------|
| 70 languages loadable | `/?lang=xx` serves correct lang pack; 200 OK for all 70 | QA (GEOA-14) |
| Mid-resource blind "understandable" > 90% | Random 10-language sample; 3 evaluators each; score > 90% | QA (GEOA-14) |
| English UI reads native | English native speaker walkthrough checklist | QA (GEOA-14) |
| Tracking dashboard shows real-time data | Events visible within 30s of firing; no console errors | QA (GEOA-14) |
| Batch optimizer cost < unoptimized | A/B comparison: 1,000 items both paths; optimized cost lower | QA (GEOA-14) |
| CI/CD push-to-deploy < 5 min | Timed deployment drill from push to health check green | QA (GEOA-14) |
| Tech stack iron laws zero violations | Full IL checklist (IL-1 through IL-17) verified per module | CTO |
| All deliverables in `deliverables/phase-b/` | File manifest check against plan | Release Engineer |

---

## 11. Deliverables Manifest

By end of Phase B, `deliverables/phase-b/` must contain:

```
deliverables/phase-b/
├── ceo-plan.md                    # CEO product plan (already exists)
├── cto-plan.md                    # This document
├── b5-infrastructure/
│   ├── report.md                  # B5 completion report
│   └── rollback-drill-log.md      # Rollback drill results
├── b4-english-ui/
│   ├── report.md                  # B4 completion report
│   ├── string-audit-log.md        # Chinese string scan results
│   └── lighthouse-seo-report.md   # Lighthouse SEO score
├── b1-language-extension/
│   ├── report.md                  # B1 completion report
│   ├── language-matrix.md         # 70-language status table
│   └── blind-test-results.md      # Tier 2 quality test results
├── b2-effect-tracking/
│   ├── report-b-core.md           # B2 B-Core completion report
│   ├── report-b-growth.md         # B2 B-Growth completion report
│   └── export-api-spec.md         # Export API documentation
├── b3-batch-optimization/
│   ├── report.md                  # B3 completion report
│   ├── ab-comparison.md           # A/B optimized vs unoptimized results
│   └── throughput-test-log.md     # 10k/hr throughput verification
├── qa/
│   ├── iron-law-audit.md          # Full IL checklist verification
│   ├── acceptance-test-matrix.md  # All acceptance criteria results
│   └── bug-log.md                 # Issues found and resolved
└── final/
    ├── integration-test-log.md     # Full-stack integration test
    └── release-notes.md            # Phase B release notes
```

---

## 12. Delegation

I, CTO (71b65322), delegate execution as follows:

| Role | Agent | Issues | Start |
|------|-------|--------|-------|
| Staff Engineer A | To be assigned | GEOA-10 (B4), GEOA-12 (B2) | After B5 complete |
| Staff Engineer B | To be assigned | GEOA-11 (B1), GEOA-13 (B3) | After B5 complete |
| QA Engineer | To be assigned | GEOA-14 (QA) | Concurrent with all modules |
| Release Engineer | To be assigned | GEOA-9 (B5), GEOA-15 (Release) | Immediate start |

**Staff Engineer split rationale**: Engineer A owns the UI + analytics surface (B4 + B2), Engineer B owns the data pipeline (B1 + B3). This creates natural separation of concerns and cross-review pairs.

---

## 13. CTO Self-Review

**Date**: 2026-07-22 | **Reviewer**: CTO agent (71b65322) | **Revision**: v1

### Module Coverage (All 5 CEO modules)

| Module | Technical Design | Sub-Issues | Token Est | Cache Strategy | Acceptance |
|--------|-----------------|------------|-----------|----------------|------------|
| B5 Infrastructure | Docker + k3s + CI/CD pipeline | GEOA-9 (7 subtasks) | 500K | Layers 4-6 | Timed drills |
| B4 English UI | i18n + SEO + responsive | GEOA-10 (6 subtasks) | 700K | Layers 4-5 | Native review |
| B1 Language Extension | Translation pipeline + quality scoring | GEOA-11 (9 subtasks) | 10.38M (total) | Layers 2-3 | Blind test |
| B2 Effect Tracking | Redis-buffered event pipeline | GEOA-12 (10 subtasks) | 1M | No caching (write-heavy) | < 30s latency |
| B3 Batch Optimization | Horizon queue batch optimizer | GEOA-13 (7 subtasks) | 700K | Layers 2-3 | A/B cost compare |

### Budget Check

$400/month × 2 months = $800 total. Projected spend: ~$295. **$505 buffer for 8 weeks.** Conservative estimate — actual costs likely lower due to translation caching.

### Iron Law Check

All 17 iron laws (IL-1 through IL-17) covered. No violations in design.

### CEO Risk Response

- **DeepSeek cost overrun** (CEO flag): Token budget with $30 hard cap on translation spend + per-job token counter. ✓
- **WP + Blade rendering perf** (CEO flag): Cache strategy in Section 7 covers Blade view cache, WP object cache, and HTTP cache. ✓

### Review Conclusion

**PASS** — Technical plan is complete, executable, and iron-law compliant. Ready for sub-issue creation and engineer assignment.

---

## Revision History

| Rev | Date | Change |
|-----|------|--------|
| v1 | 2026-07-22 | Initial CTO technical execution plan |
