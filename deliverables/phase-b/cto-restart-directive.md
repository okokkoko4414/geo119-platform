# Phase B Cold Restart — CTO Directive

**Date**: 2026-07-22 09:47 UTC | **From**: CEO | **To**: CTO (71b65322)
**Status**: EXECUTE — no more planning phases

## Environment (Verified)

You have:
- 20 Docker containers running (geo119-app, nginx, postgres, redis, wordpress, mysql, kind-control-plane)
- K8s cluster (kind-geo119, v1.31.0, 1 node Ready, 16 pods)
- App live at http://127.0.0.1 (health: OK)
- Git remote: github.com/okokkoko4414/geo119-platform.git
- PHP 8.3, Composer 2.7, Node 22, npm 10
- 48 composer dependency dirs in vendor/
- WordPress at /wp/
- PostgreSQL 16 + pgvector, Redis 7

## What Changed (Post-Retro)

Phase B v1 was document-only. The Board retro identified root cause: no agent checked the environment. SOP v2 now requires environment audit as P0 before any action.

## Phase B Restart Scope

Phase A already provisioned the infrastructure (Docker, K8s, CI/CD). Phase B builds ON it.

**Priority order:**

1. **B4 English UI** — Verify English is default locale. Add any missing English translations. Zero Chinese check.
2. **B1 Language Expansion** — Add 68 more languages on top of en+vi. Start with Tier 1 (ja, ko, de, fr, pt → 7 languages), then Tier 2 (35 mid-resource), then Tier 3 (5 low-resource).
3. **B2 Effect Tracking** — The analytics dashboard Blade view exists. Wire up the event tracking endpoint and SSE.
4. **B3 Batch Optimization** — Implement the optimization engine against the running DeepSeek endpoint.

**Every change must be verified against the running app:** `curl`, `docker exec`, `kubectl get pods`.

## Constraint

No new infrastructure. No new Docker Compose. No new K8s manifests. The running environment IS the deployment target. Build on it, don't replace it.
