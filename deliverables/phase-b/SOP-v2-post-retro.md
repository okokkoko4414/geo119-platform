# GEO119 SOP — Phase Execution Standard (Post-Retro v2)

**Date**: 2026-07-22 | **Author**: CEO | **Supersedes**: All prior planning templates

## Root Cause

Phase B (GEOA-5) produced 1,200+ lines of design documents and zero deployed code because no agent at any delegation level ran `docker ps` or `kubectl get nodes` before planning or executing. Both Board and CEO claimed "done" on document-only output.

## P0 — Environment Audit (Hard Gate)

**Every heartbeat on every issue MUST begin with this audit.** If the audit is not in the first tool call batch, the heartbeat is invalid.

```bash
docker ps --format "table {{.Names}}\t{{.Status}}"
kubectl get nodes
kubectl get pods -A | wc -l
curl -s http://127.0.0.1/health
git log --oneline -3
```

Current environment (as of 2026-07-22 09:47 UTC):
- 20 Docker containers (geo119-app, geo119-nginx, geo119-postgres, geo119-redis, geo119-wordpress, geo119-mysql, kind-control-plane, etc.)
- 1 K8s node (kind-geo119, v1.31.0, Ready, 16 pods)
- App health: OK at http://127.0.0.1/health
- Git remote: https://github.com/okokkoko4414/geo119-platform.git
- PHP 8.3, Composer 2.7, Node 22, npm 10

## P1 — Verification Before Completion

Before claiming any issue "done":
1. If it involves code: the code must exist in the repo (not just design docs)
2. If it involves deployment: `kubectl get pods` must show the new pods
3. If it involves the app: `curl` must show the change
4. Acceptance criteria must be checked against the running environment, not the spec document

**"status=done" is not completion. Running evidence is.**

## P2 — Issue Descriptions Must Include Environment Context

Every child issue created must state:
- "You have Docker running with X containers. Your app is at Y. Your K8s cluster has Z pods."
- Not: "Docker should be configured to..."
- Not: "The CI/CD pipeline should..."

## Revised Delegation Pattern

```
CEO (audit → delegate)
  → CTO (audit → verify env → write tech plan informed by running state)
    → Staff Engineer (audit → write code → docker build → kubectl apply → verify)
    → QA Engineer (audit → curl endpoints → check DB → validate against live app)
    → Release Engineer (audit → docker build → kubectl apply → rollout status → health check)
```

## Phase B Cold Restart

### What stays
- CEO product plan (v2)
- CTO technical architecture decisions (the architecture is sound)
- Iron law verification (already Board-approved)

### What changes
- No more planning-only phases. Plans inform action, not replace it.
- Every delegated task must produce running evidence.
- B5 Infrastructure is already provisioned (Phase A). Phase B execution builds ON it, not REPLACES it.

### Phase B Restart Order
1. B4 English UI — modify running app's Blade views, verify with `curl`
2. B1 Language Expansion — add language packs to running Laravel app
3. B2 Effect Tracking — add analytics endpoints to running app
4. B3 Batch Optimization — add optimization engine to running app

**All work targets the running geo119-app container.** No new infrastructure needed.
