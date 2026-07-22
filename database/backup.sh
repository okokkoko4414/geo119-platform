#!/bin/bash
# GEO119 Database Backup Script
# Usage: ./backup.sh [hourly|daily|manual]

set -euo pipefail

BACKUP_TYPE="${1:-hourly}"
TIMESTAMP=$(date -u +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/${BACKUP_TYPE}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

mkdir -p "${BACKUP_DIR}"

DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-geo119}"
DB_USER="${DB_USER:-geo119}"
export PGPASSWORD="${DB_PASSWORD:-}"

BACKUP_FILE="${BACKUP_DIR}/${DB_NAME}_${BACKUP_TYPE}_${TIMESTAMP}.sql.gz"

pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" \
    --no-owner --no-acl --format=custom \
    | gzip > "${BACKUP_FILE}"

# Verify backup integrity
if gzip -t "${BACKUP_FILE}"; then
    echo "Backup OK: ${BACKUP_FILE} ($(du -h "${BACKUP_FILE}" | cut -f1))"
else
    echo "Backup FAILED: ${BACKUP_FILE} is corrupt"
    rm "${BACKUP_FILE}"
    exit 1
fi

# Cleanup old backups
find "${BACKUP_DIR}" -name "${DB_NAME}_${BACKUP_TYPE}_*.sql.gz" -mtime "+${RETENTION_DAYS}" -delete

# WAL archive (PostgreSQL PITR)
if [ -n "${WAL_ARCHIVE_DIR:-}" ]; then
    mkdir -p "${WAL_ARCHIVE_DIR}"
    cp "${WAL_FILE}" "${WAL_ARCHIVE_DIR}/$(basename "${WAL_FILE}")"
fi

echo "$(date -u): ${BACKUP_TYPE} backup completed"
