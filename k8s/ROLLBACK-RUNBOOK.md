# GEO119 Kubernetes Rollback Runbook

**SLA**: Detect failure → previous revision serving traffic in **< 5 minutes**

## Rollback Decision Matrix

| Scenario | Action | SLA |
|----------|--------|-----|
| Health check fails (3 retries) | **Auto-rollback** via CI/CD | < 5 min |
| Error rate > 1% | Manual rollback after ACK | < 5 min |
| Queue depth > 1000 for > 5 min | Manual rollback after ACK | < 10 min |
| DB migration fails | Auto-rollback + migrate:rollback | < 5 min |
| All pods crash | PDB keeps 1 pod; HPA rescales | Auto |

## Automated Rollback (CI/CD)

The CI/CD pipeline (`deploy.yaml`) automatically:
1. Deploys new revision
2. Waits for `kubectl rollout status` (timeout 5m)
3. Runs health check (3 retries, 5s interval)
4. On failure: `kubectl rollout undo` + PagerDuty alert

## Manual Rollback Procedure

### 1. Identify failing revision

```bash
kubectl rollout history deployment/laravel -n geo119-${APP_ENV}
kubectl describe deployment/laravel -n geo119-${APP_ENV} | grep -A5 "Conditions"
```

### 2. Rollback deployment

```bash
# Rollback to previous revision
kubectl rollout undo deployment/laravel -n geo119-${APP_ENV}

# Or rollback to specific revision
kubectl rollout undo deployment/laravel -n geo119-${APP_ENV} --to-revision=3

# Rollback Horizon workers
kubectl rollout undo deployment/horizon -n geo119-${APP_ENV}
```

### 3. Verify rollback

```bash
# Check rollout status
kubectl rollout status deployment/laravel -n geo119-${APP_ENV} --timeout=5m

# Health check
curl -s https://${APP_URL}/health | jq .status

# Check pod status
kubectl get pods -n geo119-${APP_ENV} -l component=laravel

# Check logs for errors
kubectl logs -n geo119-${APP_ENV} -l component=laravel --tail=50
```

### 4. Notify

```bash
# Post to Slack
# "Rollback completed: <env> rolled back from revision X to Y. Reason: <reason>. Duration: <N>s"
```

## Rollback Drill

Run quarterly or before any major release:

```bash
# 1. Record start time
START=$(date +%s)

# 2. Deploy intentionally broken image
kubectl set image deployment/laravel laravel=ghcr.io/geo119/geo119:broken -n geo119-staging

# 3. Wait for health check failure detection (should be < 60s)
sleep 60

# 4. Execute rollback
kubectl rollout undo deployment/laravel -n geo119-staging

# 5. Verify recovery
kubectl rollout status deployment/laravel -n geo119-staging --timeout=5m
curl -s https://staging.geo119.com/health

# 6. Record end time
END=$(date +%s)
echo "Rollback completed in $((END - START)) seconds"
```

## Post-Rollback

- [ ] Root cause analysis started
- [ ] Fix committed to new branch
- [ ] CI passes on fix branch
- [ ] Re-deploy tested in staging
