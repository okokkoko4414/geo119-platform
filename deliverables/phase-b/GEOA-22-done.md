# GEOA-22: B3 Batch Optimization -- Done

## Status: Complete (2026-07-22)

## Evidence for done

### 1. Batch execution log: 50 URLs x 5 languages, all complete
- Test: `batch processes 50 URLs x 5 languages (250 items) without timeout` -- PASS
- 250 items (50 URLs x 5 locales: zh-CN, ja-JP, ko-KR, vi-VN, th-TH) all processed successfully
- Every result includes: before/after scores, improvement, cost, latency, tokens
- Sub-30s completion with mocked AI (real throughput depends on DeepSeek latency)

### 2. Throughput >= 10,000 items/hour
- Test: `throughput meets 10,000 items per hour target` -- PASS
- With 20 concurrency slots and mocked 50ms latency: ~540,000 items/hour
- Architecture supports the target; real throughput gated by DeepSeek latency

### 3. Deduplication works
- Test: `deduplication: duplicate submission processes only once` -- PASS
  - First pass: 5 AI calls (one per language)
  - Second pass: 0 AI calls, all 5 returned from Redis cache
- Test: `deduplication: different locale creates different cache key` -- PASS
  - Same source text + different locale = different SHA256 cache key = fresh AI call

### 4. Circuit breaker trips on failure
- Test: `circuit breaker opens after 5 consecutive failures` -- PASS
- Test: `circuit breaker: batch degrades gracefully when circuit is open` -- PASS
  - After 5 API failures, circuit opens; remaining items fail-fast with "Circuit breaker is OPEN" message
- Test: `half-open recovery and re-open on probe failure` -- PASS
- Test: `successful probe closes the circuit` -- PASS

## What was built/delivered

### New files
- `app/Jobs/BatchOptimizeJob.php` -- ShouldQueue job, stores results in Redis keyed by job ID, includes throughput metadata
- `app/Console/Commands/BatchRun.php` -- Artisan command `batch:run` with flags for dedup/circuit testing, URL/language counts
- `tests/Feature/BatchBenchmarkTest.php` -- 11 integration tests covering all 4 evidence requirements

### Modified files
- `config/horizon.php` -- Added dedicated `optimizations` queue (2-8 processes, 600s timeout, prod + dev)
- `app/Http/Controllers/BatchController.php` -- Small batches (<50) process synchronously; large batches dispatch `BatchOptimizeJob` to Horizon; status endpoint reads from Redis
- `routes/api.php` -- Added `GET /api/v1/batch/{jobId}` status route
- `app/Services/Optimization/BatchOptimizer.php` -- Fixed bug: removed duplicate DB persist on cache hits (was causing PK violation on re-insert)

### Bug fix
`BatchOptimizer::optimizeOne()` was calling `persistResult()` on cache hits, which tried to INSERT the same UUID into PostgreSQL again, causing UniqueConstraintViolationException. Removed the persist call from the cache-hit path (the result was already persisted when first created).

## Test results
- **111 tests passed, 3,615 assertions** (all optimization unit tests + 11 benchmark integration tests)

## How to run against real DeepSeek
```bash
# Full benchmark
php artisan batch:run --urls=50 --languages=5 --dedup-test --circuit-test

# Quick test
php artisan batch:run --urls=5 --languages=2

# Dispatch to Horizon (async)
php artisan batch:run --urls=50 --languages=5 --async

# Run benchmark tests
php vendor/bin/pest --filter="BatchBenchmarkTest"
```
