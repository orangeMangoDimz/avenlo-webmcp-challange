#!/bin/bash
set -euo pipefail

# Execute an arbitrary .sql file against the local MySQL Docker container.
#
#   ./scripts/dev/execute.sh back-end/database/migrations/some_change.sql
#   ./scripts/dev/execute.sh ../../backup_20260718_111124.sql
#
# Runs as MySQL root and strips DEFINER= clauses so dump restores work without SUPER.

# -- paths --
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/infra/docker/dev/docker-compose.yml"

DB_SERVICE="mysql"

compose() {
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

# -- read DB creds from .env (no sourcing — avoids executing file content) --
# Contract: keys are plain env-var identifiers ([A-Z_]); a value is taken verbatim
# except for a matched pair of surrounding quotes.
read_env() {
  local key="$1" line val
  line="$(grep -E "^[[:space:]]*${key}=" "$ENV_FILE" 2>/dev/null | tail -n1)" || true
  [[ -z "$line" ]] && return 0
  val="${line#*=}"
  if [[ "$val" == '"'*'"' ]]; then
    val="${val#\"}"; val="${val%\"}"
  elif [[ "$val" == "'"*"'" ]]; then
    val="${val#\'}"; val="${val%\'}"
  fi
  printf '%s' "$val"
}

SQL_FILE="${1:-}"
KEEP_DEFINER=false

for arg in "${@:2}"; do
  case "$arg" in
    --keep-definer) KEEP_DEFINER=true ;;
    -h|--help)
      echo "Usage: $0 <path-to-sql-file> [--keep-definer]"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg"
      exit 1
      ;;
  esac
done

if [[ -z "$SQL_FILE" ]]; then
  echo "Usage: ./scripts/dev/execute.sh <path-to-sql-file> [--keep-definer]"
  exit 1
fi

[[ "$SQL_FILE" != /* ]] && SQL_FILE="$(pwd)/$SQL_FILE"

if [[ ! -f "$SQL_FILE" ]]; then
  echo "File not found: $SQL_FILE"
  exit 1
fi

# -- validate .env --
if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env not found at $ENV_FILE. Run ./scripts/dev/start.sh first." >&2
  exit 1
fi

DB_NAME="$(read_env DB_NAME)"
DB_ROOT_PASSWORD="$(read_env DB_ROOT_PASSWORD)"

[[ -z "$DB_NAME" ]] && { echo "ERROR: DB_NAME is missing in $ENV_FILE" >&2; exit 1; }
[[ -z "$DB_ROOT_PASSWORD" ]] && { echo "ERROR: DB_ROOT_PASSWORD is missing in $ENV_FILE" >&2; exit 1; }

# -- docker checks --
if ! docker info > /dev/null 2>&1; then
  echo "ERROR: Docker is not running. Start Docker and try again." >&2
  exit 1
fi

if ! compose ps --services --status running | grep -qx "$DB_SERVICE"; then
  echo "ERROR: Service '$DB_SERVICE' is not running. Start it with ./scripts/dev/start.sh" >&2
  exit 1
fi

echo "Executing $SQL_FILE as root (DEFINER: $([[ "$KEEP_DEFINER" == true ]] && echo kept || echo stripped))..."

# Non-deterministic routines need this under binary logging (dev-only, resets on restart).
compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
  mysql -uroot -e "SET GLOBAL log_bin_trust_function_creators = 1;"

ERR_LOG="$(mktemp)"
trap 'rm -f "$ERR_LOG"' EXIT
import_status=0

if [[ "$KEEP_DEFINER" == true ]]; then
  compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
    mysql -uroot "$DB_NAME" < "$SQL_FILE" 2> "$ERR_LOG" || import_status=$?
else
  sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' "$SQL_FILE" \
    | compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
        mysql -uroot "$DB_NAME" 2> "$ERR_LOG" || import_status=$?
fi

if [[ $import_status -ne 0 ]]; then
  echo "Execution FAILED (exit $import_status)" >&2
  [[ -s "$ERR_LOG" ]] && { echo "---- mysql stderr ----" >&2; cat "$ERR_LOG" >&2; }
  exit 1
fi

if [[ -s "$ERR_LOG" ]]; then
  echo "Note: mysql emitted warnings:"
  sed 's/^/  /' "$ERR_LOG"
fi

echo "Execution successful"
