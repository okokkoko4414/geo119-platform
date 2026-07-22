# GEO119 Database Migration Runbook

## Pre-Migration Checklist

- [ ] Verify backup is recent (< 1 hour old): `ls -la /backups/hourly/`
- [ ] Confirm target environment: `echo $APP_ENV`
- [ ] Check DB connection pool capacity: `kubectl port-forward pgbouncer-0 6432:6432 -n geo119-${APP_ENV}`
- [ ] Notify team in #geo119-deploys Slack channel
- [ ] For production: schedule maintenance window (Tue/Thu 03:00-05:00 UTC)

## Migration Procedure

### Per-Module Migration

Each module has its own migration directory:

| Module | Migration Dir | Run Order |
|--------|-------------|-----------|
| B5 Infrastructure | database/migrations/001-004 | 1 |
| B4 English UI | database/migrations/010-019 | 2 |
| B1 Language Expansion | database/migrations/020-029 | 3 |
| B2 Effect Tracking | database/migrations/030-039 | 4 |
| B3 Batch Optimization | database/migrations/040-049 | 5 |

### Running Migrations

```bash
# Always run in a transaction where possible
kubectl exec -it deploy/laravel -n geo119-${APP_ENV} -- \
  php artisan migrate --force --step

# Verify migration status
kubectl exec -it deploy/laravel -n geo119-${APP_ENV} -- \
  php artisan migrate:status
```

### Rollback Procedure (< 5 minutes)

```bash
# Rollback last batch
kubectl exec -it deploy/laravel -n geo119-${APP_ENV} -- \
  php artisan migrate:rollback --step=1 --force

# Verify
kubectl exec -it deploy/laravel -n geo119-${APP_ENV} -- \
  php artisan migrate:status
```

## Post-Migration Verification

- [ ] All migrations show "Ran": `php artisan migrate:status`
- [ ] Health check passes: `curl https://${APP_URL}/health`
- [ ] pgvector extension active: `SELECT * FROM pg_extension WHERE extname='vector'`
- [ ] Queue worker processes running: `php artisan horizon:status`

## Emergency Contacts

| Role | Contact |
|------|---------|
| Release Engineer | On-call rotation |
| DBA | On-call rotation |
