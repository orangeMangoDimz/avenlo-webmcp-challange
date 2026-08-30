#!/bin/bash
set -euo pipefail

# Seed a .sql dump into the local MySQL Docker container.
#
#   ./scripts/dev/seed.sh --file back-end/seeder/init_20260624_100140.sql
#
# Imports as root (so views/triggers/procedures can be created) and, by default,
# strips DEFINER clauses from the dump so those objects are owned by the importing
# user instead of the original production accounts. Without this, the app's views
# (e.g. vAdminUsersFull) fail at query time with "definer does not exist".

# -- paths --
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/infra/docker/dev/docker-compose.yml"

compose() {
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

# -- defaults --
DB_SERVICE="mysql"
SQL_FILE=""
KEEP_DEFINER=false
ASSUME_YES=false

usage() {
  cat <<EOF
Usage: $(basename "$0") --file <path-to-sql> [options]

Seed a .sql dump into the MySQL Docker service ($DB_SERVICE).

Required:
  --file <path>     .sql file to import (absolute, or relative to repo root)

Options:
  --keep-definer    Do NOT strip DEFINER clauses. Default is to strip them so
                    views/triggers/procedures work without the original DB users.
  -y, --yes         Skip the confirmation prompt
  -h, --help        Show this help

Examples:
  $(basename "$0") --file back-end/seeder/init_20260624_100140.sql
  $(basename "$0") --file /abs/path/dump.sql -y
EOF
}

# -- parse args --
while [[ $# -gt 0 ]]; do
  case "$1" in
    --file)
      if [[ $# -lt 2 || -z "${2:-}" ]]; then
        echo "ERROR: --file requires an argument." >&2; echo; usage; exit 1
      fi
      SQL_FILE="$2"; shift 2 ;;
    --file=*)       SQL_FILE="${1#*=}"; shift ;;
    --keep-definer) KEEP_DEFINER=true; shift ;;
    -y|--yes)       ASSUME_YES=true; shift ;;
    -h|--help)      usage; exit 0 ;;
    *) echo "ERROR: Unknown argument: $1" >&2; echo; usage; exit 1 ;;
  esac
done

# -- validate --file --
if [[ -z "$SQL_FILE" ]]; then
  echo "ERROR: --file is required." >&2; echo; usage; exit 1
fi
[[ "$SQL_FILE" != /* ]] && SQL_FILE="$PROJECT_ROOT/$SQL_FILE"
if [[ ! -f "$SQL_FILE" ]]; then
  echo "ERROR: SQL file not found: $SQL_FILE" >&2; exit 1
fi
if [[ ! -r "$SQL_FILE" ]]; then
  echo "ERROR: SQL file not readable: $SQL_FILE" >&2; exit 1
fi

# -- read DB creds from .env (no sourcing — avoids executing file content) --
# Contract: keys are plain env-var identifiers ([A-Z_]); a value is taken verbatim
# except for a matched pair of surrounding quotes. Do NOT put an inline comment after
# an unquoted value in .env — it is returned as part of the value, so that a literal
# '#' inside a password is preserved.
read_env() {
  local key="$1" line val
  line="$(grep -E "^[[:space:]]*${key}=" "$ENV_FILE" 2>/dev/null | tail -n1)" || true
  [[ -z "$line" ]] && return 0
  val="${line#*=}"
  # strip only a matched pair of surrounding quotes
  if [[ "$val" == '"'*'"' ]]; then
    val="${val#\"}"; val="${val%\"}"
  elif [[ "$val" == "'"*"'" ]]; then
    val="${val#\'}"; val="${val%\'}"
  fi
  printf '%s' "$val"
}

if [[ ! -f "$ENV_FILE" ]]; then
  echo "ERROR: .env not found at $ENV_FILE. Run ./scripts/dev/start.sh first." >&2
  exit 1
fi
DB_NAME="$(read_env DB_NAME)"
DB_ROOT_PASSWORD="$(read_env DB_ROOT_PASSWORD)"
if [[ -z "$DB_NAME" ]]; then
  echo "ERROR: DB_NAME is missing in $ENV_FILE" >&2; exit 1
fi
if [[ -z "$DB_ROOT_PASSWORD" ]]; then
  echo "ERROR: DB_ROOT_PASSWORD is missing in $ENV_FILE" >&2; exit 1
fi

# -- docker checks --
if ! docker info > /dev/null 2>&1; then
  echo "ERROR: Docker is not running. Start Docker and try again." >&2; exit 1
fi
if ! compose ps --services --status running | grep -qx "$DB_SERVICE"; then
  echo "ERROR: Service '$DB_SERVICE' is not running. Start it with ./scripts/dev/start.sh" >&2
  exit 1
fi

# -- helper: run a query as root, no column headers --
sql_root() {
  compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
    mysql -uroot -N -e "$1"
}

CURRENT_TABLES="$(sql_root "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null || echo '?')"

# -- confirm --
echo "==> utrada-crm dev — seed database"
echo ""
echo "  File:       $SQL_FILE"
echo "  Size:       $(du -h "$SQL_FILE" | cut -f1)"
echo "  Service:    $DB_SERVICE"
echo "  Database:   $DB_NAME (currently $CURRENT_TABLES tables)"
echo "  DEFINER:    $([[ "$KEEP_DEFINER" == true ]] && echo 'kept' || echo 'stripped (recommended for local dev)')"
echo ""
if [[ "$ASSUME_YES" != true ]]; then
  read -r -p "  Proceed? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }
fi
echo ""

# -- ensure database exists --
echo "==> Ensuring database '$DB_NAME' exists..."
sql_root "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Dumps with non-deterministic stored functions (e.g. ones using RAND()) fail to
# import under MySQL 8's binary logging with ERROR 1418 unless creators are trusted.
# Server-wide setting (the import runs on a separate connection); harmless on a dev
# container and resets on restart.
echo "==> Allowing routine creation under binary logging..."
sql_root "SET GLOBAL log_bin_trust_function_creators = 1;"

# -- import --
echo "==> Importing $(basename "$SQL_FILE")... (this can take a while for large dumps)"
ERR_LOG="$(mktemp)"
trap 'rm -f "$ERR_LOG"' EXIT

import_status=0
if [[ "$KEEP_DEFINER" == true ]]; then
  compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
    mysql -uroot "$DB_NAME" < "$SQL_FILE" 2> "$ERR_LOG" || import_status=$?
else
  # Strip `DEFINER=`user`@`host`` so objects are created as the importing user (root).
  sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' "$SQL_FILE" \
    | compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
        mysql -uroot "$DB_NAME" 2> "$ERR_LOG" || import_status=$?
fi

if [[ $import_status -ne 0 ]]; then
  echo "ERROR: import failed (exit $import_status)." >&2
  [[ -s "$ERR_LOG" ]] && { echo "---- mysql stderr ----" >&2; cat "$ERR_LOG" >&2; }
  exit 1
fi
if [[ -s "$ERR_LOG" ]]; then
  echo "  Note: mysql emitted warnings during import:"
  sed 's/^/    /' "$ERR_LOG"
fi

# -- verify --
echo ""
echo "==> Verifying..."
BASE_TABLES="$(sql_root "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE';")"
VIEWS="$(sql_root "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='$DB_NAME';")"
echo "  Tables: $BASE_TABLES   Views: $VIEWS"

# Querying a view actually exercises its DEFINER — a view with a dead definer
# still appears in the table count but errors here. This is the real check.
SAMPLE_VIEW="$(sql_root "SELECT table_name FROM information_schema.views WHERE table_schema='$DB_NAME' ORDER BY table_name LIMIT 1;")"
if [[ -n "$SAMPLE_VIEW" ]]; then
  view_err="$(compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
    mysql -uroot "$DB_NAME" -e "SELECT 1 FROM \`$SAMPLE_VIEW\` LIMIT 1;" 2>&1 >/dev/null)" || true
  if [[ -n "$view_err" ]]; then
    echo "  ⚠ view check FAILED on '$SAMPLE_VIEW':"
    echo "$view_err" | sed 's/^/      /'
    echo "    If this mentions a missing definer, re-run WITHOUT --keep-definer."
    exit 1
  fi
  echo "  ✓ view check passed ('$SAMPLE_VIEW' is queryable)"
fi

echo ""
echo "==> Done. '$DB_NAME' seeded."
