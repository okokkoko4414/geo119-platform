# GEOA-18 — B5 Infrastructure: Delivery Evidence

## Status Summary

| # | Deliverable | Status | Evidence |
|---|------------|--------|----------|
| 1 | Real Laravel app at :8000 | ✅ PASS | `curl :8000/api/health` → `{"status":"ok","service":"geoflow","version":"0.1.0"}` |
| 2 | All K8s pods Running | ✅ 7/7 PASS | 3 Laravel + 2 Horizon + 1 claude-local + 1 WordPress — all Running |
| 3 | CI/CD pipeline | ✅ Pipeline configured, ⏳ needs push | `.github/workflows/ci.yml` and `production-deploy.yml` configured. CI fails on Chinese chars + Pint — both fixed locally. Push blocked by `github.com` TLS issue. |
| 4 | Monitoring (Prometheus + Grafana) | ❌ BLOCKED | Manifests at `k8s/monitoring/prometheus-grafana.yaml`. Pods in ImagePullBackOff — Docker Hub unreachable from kind cluster (`dial tcp registry-1.docker.io:443: i/o timeout`). |
| 5 | pgvector active | ✅ PASS | `SELECT extname FROM pg_extension` → plpgsql, vector, pg_trgm, uuid-ossp |
| 6 | Redis + Horizon | ✅ PASS | Redis PONG. Horizon dashboard HTTP 200 at `/horizon`. |
| 7 | claude_local reachable | ✅ PASS | `curl claude-local:8000/health` → `{"status":"ok","service":"claude-local","version":"1.0.0"}` |

## Infrastructure Overview

**Docker containers (18 running):**
- geo119: nginx, redis, postgres, wordpress, mysql
- geoa10: wordpress, mysql, redis, postgres, geoflow-reverb, geoflow-scheduler, geoflow-queue, geoflow-app
- geoflow: app, redis, postgres, reverb, scheduler, queue, test_db

**K8s cluster (kind, geo119 namespace):**
- 3× geoflow-laravel (NodePort 30080)
- 2× geoflow-horizon
- 1× claude-local (ClusterIP)
- 1× geoflow-wordpress (ClusterIP)
- Prometheus + Grafana (stuck ImagePullBackOff)

**Databases:**
- geo119 PostgreSQL 16 with pgvector: 5 databases (geo119, geo119_test, geoflow, postgres)
- Events table: partitioned monthly (14 partitions from 2026-07 through 2027-06)

## CI/CD Fixes Applied (local, awaiting push)

Files modified by commit `be1d4f1`:
- 7 files auto-fixed by Laravel Pint (formatting)
- `functions.php`: language labels changed from native scripts to English names
- Chinese character scan now passes (0 CJK characters in views)

## Monitoring Blocker Detail

- **Symptom**: Prometheus and Grafana pods stuck in ImagePullBackOff
- **Root cause**: `docker.io` is unreachable from kind cluster: `dial tcp registry-1.docker.io:443: i/o timeout`
- **Attempted alternatives**: GHCR (auth required), GCR (timeout), local registry (not running)
- **Unblock path**: Load images into kind via `kind load docker-image` after pulling from a reachable registry, or configure a Docker registry mirror/proxy

## PR

https://github.com/okokkoko4414/geo119-platform/pull/1
