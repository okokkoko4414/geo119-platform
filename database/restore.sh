#!/bin/bash
# GEO119 Database Restore Script
# Usage: ./restore.sh <backup_file.sql.gz>

set -euo pipefail

BACKUP_FILE="${1:?Usage: $0 <backup_file.sql.gz>}"
DB_HOST="${DB_HOST:-localhost}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_NAME:-geo119}"
DB_USER="${DB_USER:-geo119}"
export PGPASSWORD="${DB_PASSWORD:-}"

if [ ! -f "${BACKUP_FILE}" ]; then
    echo "Backup file not found: ${BACKUP_FILE}"
    exit 1
fi

echo "Restoring ${DB_NAME} from ${BACKUP_FILE}..."

# Drop and recreate (optional — requires confirmation)
if [ "${FORCE_RESTORE:-}" = "true" ]; then
    dropdb -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" "${DB_NAME}" --if-exists
    createdb -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" "${DB_NAME}"
fi

gunzip -c "${BACKUP_FILE}" | pg_restore -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" --no-owner --no-acl

# Reinitialize extensions
psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" -c "
  CREATE EXTENSION IF NOT EXISTS vector;
  CREATE EXTENSION IF NOT EXISTS pg_trgm;
  CREATE EXTENSION IF NOT EXISTS \"uuid-ossp\";
"

echo "Restore completed at $(date -u)"
