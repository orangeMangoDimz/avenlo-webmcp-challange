#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="${STACK_DIR:-/opt/utrada-crm}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/utrada-crm}"
STACK_ENV="$STACK_DIR/stack.env"
BACKEND_ENV="$STACK_DIR/backend.env"
COMPOSE_FILE="$STACK_DIR/docker-compose.yml"

read_env() {
  local key="$1" line
  line="$(grep -E "^[[:space:]]*${key}=" "$BACKEND_ENV" | tail -n1)"
  printf '%s' "${line#*=}"
}

compose() {
  docker compose --env-file "$STACK_ENV" --env-file "$BACKEND_ENV" -f "$COMPOSE_FILE" "$@"
}

if [[ -f "$STACK_ENV" && -f "$BACKEND_ENV" && -f "$COMPOSE_FILE" ]]; then
  DB_NAME="$(read_env DB_NAME)"
  DB_ROOT_PASSWORD="$(read_env DB_ROOT_PASSWORD)"
  [[ -n "$DB_NAME" && -n "$DB_ROOT_PASSWORD" ]] || { echo "Database settings are incomplete." >&2; exit 1; }
else
  legacy_container="${LEGACY_DB_CONTAINER:-mysql-5.7}"
  DB_NAME="${BACKUP_DB_NAME:-ut-crm-db}"
  docker inspect "$legacy_container" >/dev/null
fi

mkdir -p "$BACKUP_DIR"
timestamp="$(date +%Y%m%d_%H%M%S)"
backup_file="$BACKUP_DIR/${timestamp}_${DB_NAME}.sql"
temporary_file="${backup_file}.tmp"
trap 'rm -f "$temporary_file"' EXIT

if [[ -f "$STACK_ENV" && -f "$BACKEND_ENV" && -f "$COMPOSE_FILE" ]]; then
  compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" mysql \
    mysqldump -uroot --single-transaction --routines --events --triggers "$DB_NAME" \
    >"$temporary_file"
else
  docker exec -e BACKUP_DATABASE="$DB_NAME" "$legacy_container" sh -c \
    'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --routines --events --triggers "$BACKUP_DATABASE"' \
    >"$temporary_file"
fi

[[ -s "$temporary_file" ]] || { echo "Database dump is empty." >&2; exit 1; }
tail -n 5 "$temporary_file" | grep -q 'Dump completed' \
  || { echo "Database dump is incomplete." >&2; exit 1; }

mv "$temporary_file" "$backup_file"
chmod 600 "$backup_file"
trap - EXIT
echo "$backup_file"
