# GEOA-7-B3 — Batch Optimization Engine

**Parent**: GEOA-7 (Phase B CTO Technical Execution Plan)
**Owner**: Staff Engineer (Pipeline)
**Sprint**: Sprint 7 (Week 7) + Sprint 8 (Week 8)
**Depends On**: B1 (Language Expansion — shares AI pipeline patterns), B2 (Effect Tracking — real data informs optimization priorities)
**Blocks**: None (final module)

## Objective

Build a batch optimization engine that processes translation optimization requests at ≥10k words/hour, cost <$0.001/word, P99 latency <30s, with concurrency control, deduplication, retry logic, and circuit breaking. Every result includes before/after scores. The engine's own running cost must be less than the cost savings it generates.

## Technical Specification

See `deliverables/phase-b/cto-technical-execution-plan.md` Section 2.5 for full optimization engine architecture, component designs (DedupCache, ConcurrencyController, CircuitBreaker, RetryManager, CostTracker), performance target analysis, edge case matrix, and job lifecycle state machine.

### Key Components to Build

1. **BatchOptimizer (Orchestrator)**
   - `POST /api/batch` — accepts array of `{source_text, target_locale, optimization_type}`
   - Returns `{job_id, estimated_cost, estimated_duration}` (202 Accepted for large batches)
   - Coordinates: DedupCache → ConcurrencyController → CircuitBreaker → DeepSeek → Score + Track

2. **DedupCache** (Redis)
   - Hash: `SHA256(source_text + target_locale + optimization_type)`
   - 30-day TTL; zero-cost cache hits
   - Target: 70% cache hit rate on repeated content
   - Lock pattern: first request sets "processing" flag, concurrent requests poll for result

3. **ConcurrencyController** (Redis Semaphore)
   - Max 20 concurrent DeepSeek calls across all pods
   - Acquire: decrement counter; Release: increment counter
   - Queue wait >30s → return 202 with job ID for polling

4. **CircuitBreaker** (Redis-backed state machine)
   - States: CLOSED → OPEN (5 consecutive failures) → HALF_OPEN (30s cooldown, 1 probe) → CLOSED or OPEN
   - OPEN state: return 503 + Retry-After header immediately (fail fast)

5. **RetryManager**
   - Exponential backoff: 1s → 2s → 4s (base) with 30% random jitter
   - Max 3 retries; only retries on transient failures (timeout, 5xx)
   - Granular retry: batch of 20 texts → only retry failed segments, not entire batch

6. **CostTracker**
   - Per-request: input_tokens, output_tokens, model, latency_ms, calculated cost
   - DeepSeek pricing: ~$0.14/1M input tokens, ~$0.28/1M output tokens
   - Daily budget cap: configurable, enforced before API call
   - Aggregate cost/word computation for throughput validation

7. **BeforeAfterScore**
   - Pre-optimization metrics stored alongside post-optimization results
   - Every `OptimizationResult` includes both scores (CEO non-negotiable #3)

### Performance Targets

| Target | How | Verification |
|--------|-----|-------------|
| ≥10k words/hour | 20 concurrent workers × 500 words each × batch API (20 texts/call) | Load test: 50k words, measure completion time |
| <$0.001/word | 70% dedup cache hit rate + batched API calls reduce per-word overhead | CostTracker aggregate over 100k+ word test |
| P99 <30s | Dedicated high-priority Horizon queue; circuit breaker prevents queue clogging | Load test percentile measurement |

## Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B3.1 | Throughput ≥10k words/hour under load | Load test with 50k words, record completion time |
| B3.2 | Aggregate cost <$0.001/word (over 100k+ word test) | CostTracker report |
| B3.3 | P99 latency <30s under concurrent load | Load test with percentile breakdown |
| B3.4 | Dedup cache: identical input returns cached result with zero additional API cost | Submit same text twice, verify second call cost=$0 in CostTracker |
| B3.5 | Circuit breaker opens after 5 consecutive failures | Kill claude_local, submit 5 jobs, verify 6th returns HTTP 503 |
| B3.6 | Circuit breaker auto-recovers (half-open probe succeeds → closed) | Restore claude_local, wait for cooldown, verify jobs succeed |
| B3.7 | Retry with exponential backoff: 1s → 2s → 4s pattern in logs | Simulate transient failure, verify retry timing ±30% jitter |
| B3.8 | Every OptimizationResult includes before/after scores | SQL query on optimization_results, verify both score columns non-null |
| B3.9 | Concurrent requests respect semaphore limit (max 20 active) | Submit 30 concurrent, verify max 20 active at any instant |
| B3.10 | Daily cost cap enforced — no requests exceed configured budget | Set cap=$0.10, submit jobs exceeding cap, verify rejected with clear message |

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Input text contains code blocks or markup | Preprocessor: detect code → preserve → translate surrounding text → restore. Each segment hashed separately for dedup |
| Very long text (>5000 chars) | Split at sentence boundaries, batch-translate segments, reassemble preserving order |
| All worker slots busy (semaphore exhausted) | Queue wait; if >30s, return 202 Accepted with job ID for polling |
| Circuit open during batch submission | Return 503 + Retry-After header; batch stays queued, will retry |
| claude_local returns partial response | Granular retry: only re-request missing segments, not full batch |
| Cost exceeds daily budget cap | Degrade to cache-only mode for remainder of budget period; clear error to user |
| Before score equals after score (no improvement) | Valid output — stored with both scores equal and `improvement: 0` (CEO #3: still shows before/after) |
| Concurrent optimization of identical text | DedupCache lock: first request sets `processing` flag; subsequent requests poll until result cached |
| Memory pressure from large dedup cache | Redis `maxmemory-policy: allkeys-lru`; cache hit rate monitored in Grafana; alert on drop below 50% |
| Race condition: semaphore counter drift | Counter initialized to MAX_CONCURRENT on each pod start; Redis `DECR` is atomic; periodic reconciliation job |

## Definition of Done

- [ ] BatchOptimizer orchestrator functional with all 5 sub-components integrated
- [ ] Throughput load test passed (≥10k words/hour)
- [ ] Cost analysis passed (<$0.001/word aggregate)
- [ ] P99 latency load test passed (<30s)
- [ ] Circuit breaker drill passed (open → half-open → closed cycle)
- [ ] Dedup cache verified (zero-cost repeat requests)
- [ ] CostTracker daily cap enforcement verified
- [ ] Every result includes before/after scores in database
- [ ] All 10 acceptance criteria verified by QA Engineer
- [ ] CostTracker aggregate report shows engine cost < cost savings (the real product success metric — CEO B3 product judgment)
