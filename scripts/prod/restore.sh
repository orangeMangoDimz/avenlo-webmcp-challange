#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="${STACK_DIR:-/opt/utrada-crm}"
STACK_ENV="$STACK_DIR/stack.env"
BACKEND_ENV="$STACK_DIR/backend.env"
COMPOSE_FILE="$STACK_DIR/docker-compose.yml"
SQL_FILE=""
ASSUME_YES=false

usage() {
  echo "Usage: $0 --file <database.sql> [--yes]"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --file) SQL_FILE="${2:-}"; shift 2 ;;
    --file=*) SQL_FILE="${1#*=}"; shift ;;
    -y|--yes) ASSUME_YES=true; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
  esac
done

[[ -n "$SQL_FILE" && -r "$SQL_FILE" ]] || { echo "A readable --file is required." >&2; exit 1; }
tail -n 5 "$SQL_FILE" | grep -q 'Dump completed' \
  || { echo "The dump is incomplete or was not produced by mysqldump." >&2; exit 1; }
[[ -f "$STACK_ENV" && -f "$BACKEND_ENV" && -f "$COMPOSE_FILE" ]] \
  || { echo "Production stack files are missing in $STACK_DIR." >&2; exit 1; }

read_env() {
  local key="$1" line
  line="$(grep -E "^[[:space:]]*${key}=" "$BACKEND_ENV" | tail -n1)"
  printf '%s' "${line#*=}"
}

DB_NAME="$(read_env DB_NAME)"
DB_USER="$(read_env DB_USER)"
DB_PASS="$(read_env DB_PASS)"
DB_ROOT_PASSWORD="$(read_env DB_ROOT_PASSWORD)"

[[ "$DB_NAME" =~ ^[A-Za-z0-9_-]+$ ]] || { echo "Invalid DB_NAME." >&2; exit 1; }
[[ "$DB_USER" =~ ^[A-Za-z0-9_.-]+$ ]] || { echo "Invalid DB_USER." >&2; exit 1; }
[[ -n "$DB_PASS" && -n "$DB_ROOT_PASSWORD" ]] || { echo "Database credentials are incomplete." >&2; exit 1; }

compose() {
  sudo docker compose --env-file "$STACK_ENV" --env-file "$BACKEND_ENV" -f "$COMPOSE_FILE" "$@"
}

current_tables="$(compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" mysql \
  mysql -uroot -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';")"

echo "Database: $DB_NAME ($current_tables existing objects)"
echo "Dump:     $SQL_FILE"
echo "WARNING: the target database will be dropped and recreated."
if [[ "$ASSUME_YES" != true ]]; then
  read -r -p "Continue? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || exit 0
fi

compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" mysql mysql -uroot -e \
  "DROP DATABASE IF EXISTS \`$DB_NAME\`; CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SET GLOBAL log_bin_trust_function_creators = 1;"

sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' "$SQL_FILE" \
  | compose exec -T -e MYSQL_PWD="$DB_PASS" mysql mysql -u"$DB_USER" "$DB_NAME"

compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" mysql mysql -uroot -N -e \
  "SELECT CONCAT('tables=', COUNT(*)) FROM information_schema.tables WHERE table_schema='$DB_NAME';
   SELECT CONCAT('views=', COUNT(*)) FROM information_schema.views WHERE table_schema='$DB_NAME';
   SELECT CONCAT('routines=', COUNT(*)) FROM information_schema.routines WHERE routine_schema='$DB_NAME';
   SELECT CONCAT('triggers=', COUNT(*)) FROM information_schema.triggers WHERE trigger_schema='$DB_NAME';
   SELECT CONCAT('events=', COUNT(*)) FROM information_schema.events WHERE event_schema='$DB_NAME';"
