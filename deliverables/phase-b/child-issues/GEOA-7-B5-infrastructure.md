# GEOA-7-B5 — Infrastructure

**Parent**: GEOA-7 (Phase B CTO Technical Execution Plan)
**Owner**: Release Engineer
**Sprint**: Sprint 1 (Week 1)
**Depends On**: None (first module)
**Blocks**: B4, B1, B2, B3 (all modules deploy on this infrastructure)

## Objective

Establish the CI/CD pipeline, Docker containerization, Kubernetes deployment, monitoring stack, database setup, and claude_local integration. From code push to production deployment, fully automated, with rollback under 5 minutes.

## Technical Specification

See `deliverables/phase-b/cto-technical-execution-plan.md` Section 2.1 for full architecture, Docker configuration, K8s manifests, monitoring stack, database setup, and claude_local integration details.

### Key Components to Build

1. **GitHub Actions CI/CD Pipeline**
   - Lint (PHP_CodeSniffer + Laravel Pint) → Static Analysis (PHPStan max) → Unit Tests (PHPUnit) + Feature Tests (Pest) → Docker Build (multi-stage, tagged :sha + :latest) → Push to Registry → K8s Apply → Health Check (3 retries, 5s interval) → Rollback on failure

2. **Docker Configuration**
   - Multi-stage Dockerfile: Composer deps → Node/Tailwind build → PHP 8.4 FPM + Nginx production image
   - docker-compose.yaml for local development

3. **Kubernetes Manifests** (per environment: dev, staging, production)
   - Deployment (Laravel 3 replicas, WordPress 2, Horizon 2), Service, Ingress (TLS), ConfigMap, Secrets (SealedSecrets), PVC, HPA, PDB

4. **Monitoring Stack**
   - Laravel Telescope (dev/staging), Prometheus exporters, Grafana dashboards (app health, queue depth, API latency, error rate, DB connections, Redis hit rate), PagerDuty alerts

5. **Database Setup**
   - PostgreSQL 16 + pgvector + pg_trgm + uuid-ossp extensions
   - PgBouncer connection pooling sidecar
   - Automated backups (hourly pg_dump + WAL archiving for PITR)

6. **claude_local Integration**
   - HTTP Client wrapper, cost tracking, circuit breaker (5 failures → 30s open), rate limiting (token bucket 100 req/min/pod)

## Acceptance Criteria

| # | Criterion | Verification |
|---|-----------|-------------|
| B5.1 | `git push main` triggers full CI/CD pipeline | Push test commit, observe pipeline |
| B5.2 | Failed health check triggers rollback < 5min | Deploy intentionally broken image, time rollback |
| B5.3 | All K8s manifests apply cleanly | `kubectl apply --dry-run=server` |
| B5.4 | Prometheus metrics endpoint responds | `curl /metrics` returns valid Prometheus format |
| B5.5 | Grafana dashboard shows app health | Screenshot of dashboard |
| B5.6 | PostgreSQL pgvector extension active | `SELECT * FROM pg_extension WHERE extname='vector'` |
| B5.7 | Redis + Horizon queue accepts and processes jobs | Dispatch test job, verify completion |
| B5.8 | claude_local endpoint reachable, returns valid response | Integration test with known prompt |
| B5.9 | Rollback completes in < 5min (timed drill) | Timed measurement, recorded |
| B5.10 | No secrets in ConfigMap | `kubectl describe configmap` audit |

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Docker registry unreachable during build | Pipeline fails with clear error; retry on next push |
| K8s cluster unreachable during deploy | Pipeline fails; manual `kubectl apply` documented as fallback |
| Health check passes but app is degraded | Separate readiness probe checks DB + Redis connectivity |
| Database migration fails during deploy | Rollback triggered; migration runs in transaction where possible |
| All pods crash simultaneously | PDB ensures at least 1 pod stays up; HPA scales replacement |
| Secrets rotation (API key change) | SealedSecrets updated; `kubectl rollout restart` triggers graceful restart |

## Definition of Done

- [ ] CI/CD pipeline runs end-to-end on push to main
- [ ] Rollback drill completed and timed at < 5 minutes
- [ ] All K8s manifests deployed to at least staging
- [ ] Grafana dashboard accessible and populated with data
- [ ] Database provisioned with all extensions, backups configured
- [ ] claude_local integration test passes
- [ ] All 10 acceptance criteria verified by QA Engineer
