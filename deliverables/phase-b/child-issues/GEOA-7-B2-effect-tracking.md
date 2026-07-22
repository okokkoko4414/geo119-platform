# GEOA-7-B2 — Effect Tracking

**Parent**: GEOA-7 (Phase B CTO Technical Execution Plan)
**Owner**: Staff Engineer (Product)
**Sprint**: Sprint 5 (Week 5 — B-Core) + Sprint 6 (Week 6 — B-Growth)
**Depends On**: B5 (Infrastructure — database, Redis), B4 (English UI — Blade components for dashboard)
**Blocks**: B3 (optimization engine informed by analytics data)

## Objective

Build an impression + click-through tracking dashboard (B-Core) and expand to full conversion funnel, retention cohort analysis, multi-dimension drill-down, and export API (B-Growth). This is the product decision engine — it answers "are users in new language markets actually using the product?"

## Technical Specification

See `deliverables/phase-b/cto-technical-execution-plan.md` Section 2.4 for full data ingestion architecture, database schema (partitioned events table, materialized aggregates), dashboard design, export API, and edge case matrix.

### Key Components to Build

**B-Core (Sprint 5):**

1. **Event Tracking Endpoint**
   - `POST /api/e/track` — accepts `{type, target, metadata}`, enriches with geo-IP, user-agent parse, session_id, locale
   - Returns `204 No Content` (fire-and-forget from browser perspective)
   - Server-side impression fallback: Blade directive counts page renders when JS is blocked

2. **Event Ingestion Pipeline**
   - Events written to Redis Stream for real-time subscribers
   - Events written to PostgreSQL `events` table (partitioned by month)
   - Hourly materialized view refresh for aggregate queries

3. **Real-Time Dashboard**
   - SSE endpoint: `GET /api/e/live` — streams Redis Stream events to browser
   - Dashboard route: `/dashboard/analytics`
   - Components: AnalyticsOverviewCard (impressions/clicks/CTR today), AnalyticsTimeSeries (30-day Chart.js line chart), LanguageBreakdownTable (by language with % change vs yesterday)

**B-Growth (Sprint 6):**

4. **Full Analytics**
   - Conversion funnel: impression → click → signup → payment
   - Retention cohorts: Day-1, Day-7, Day-30 by language
   - Multi-dimension drill-down: Language × Region × Device × Time (AJAX-updated chart + table)

5. **Export API**
   - `GET /api/analytics/export?from=&to=&dimensions[]=&metrics[]=&format=csv|json`
   - Streaming response for large exports (no memory exhaustion)

### Database Schema

- `events` table: partitioned by month on `created_at`
- `event_aggregates_hourly` materialized view: pre-computed aggregates by hour, event_type, locale, country, device_type
- Indexes on `(locale, created_at)`, `(event_type, created_at)`, `(user_id, created_at)`

## Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B2.1 | Event tracking endpoint accepts and stores events | `curl POST /api/e/track` → row in events table |
| B2.2 | Dashboard shows real-time impression count via SSE | Open dashboard, trigger events, see counter update without refresh |
| B2.3 | CTR computed correctly; handles impressions=0 gracefully (displays "—") | Test with 0 and non-zero impression counts |
| B2.4 | Language breakdown table sorts correctly and shows % change | Visual verification with seeded data |
| B2.5 | Export API returns valid CSV with correct aggregations | `curl` export, validate with csvkit or manual inspection |
| B2.6 | Multi-dimension drill-down filters work independently | UI test: change each dimension filter, verify chart + table update |
| B2.7 | Conversion funnel shows correct step counts and drop-off rates | Seed known event sequence, verify funnel visualization |
| B2.8 | Retention cohort table shows D1/D7/D30 correctly | Seed events spanning multiple dates, verify cohort numbers |
| B2.9 | Bot traffic filtered from dashboard (not from raw data) | Simulate bot-like pattern (100+ events/min), verify dashboard excludes |
| B2.10 | Export API handles large date ranges without timeout | Request 90-day export, verify streaming response completes |

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Zero impressions for a new language | CTR displayed as "—" not "0%" (no misleading zero) |
| Bot traffic spike | User-agent filtering; sessions with >100 events/minute flagged as bot |
| Session spans multiple locales | Treated as separate sessions per locale switch (session_id + locale composite) |
| Ad-blocker blocks tracking JavaScript | Server-side Blade directive counts page renders as impression fallback |
| Clock skew across K8s pods | All event timestamps use `NOW()` (DB server time), not application pod time |
| Export request for date range with no data | Returns CSV with headers only; HTTP 200 (not error) |
| Materialized view refresh contention | Refresh scheduled during low-traffic window; uses `CONCURRENTLY` to avoid locking |
| Very high event volume (100k+/minute) | Redis Stream acts as buffer; Horizon workers process in batches; events table partitioned |

## Definition of Done

**B-Core:**
- [ ] Event tracking endpoint live and accepting events
- [ ] Real-time dashboard with impressions, clicks, CTR
- [ ] Language breakdown table functional
- [ ] SSE-powered live counter updating
- [ ] B2.1–B2.4 verified by QA

**B-Growth:**
- [ ] Conversion funnel visualization
- [ ] Retention cohort analysis
- [ ] Multi-dimension drill-down (all 4 dimensions)
- [ ] Export API with CSV + JSON formats
- [ ] B2.5–B2.10 verified by QA
