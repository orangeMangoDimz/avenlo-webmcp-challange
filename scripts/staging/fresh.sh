#!/bin/bash
set -euo pipefail

CONTAINER=""
DB_USER=""
DB_PASS=""
DB_NAME=""
SQL_FILE=""
KEEP_DEFINER=false
ASSUME_YES=false

usage() {
  cat <<EOF
Usage: $(basename "$0") --container <name> --user <user> --password <pass> --database <name> --file <sql> [options]

Drop and recreate a MySQL database inside a Docker container, then import a .sql dump
(tables, data, routines, events). Designed for dumps from scripts/prod/backup.sh.

Required:
  --container <name>    Docker container name or ID
  --user <user>         MySQL user
  --password <pass>     MySQL password
  --database <name>     Target database name (drop + create + import into this)
  --file <path>         Path to .sql dump (absolute, or relative to cwd)

Options:
  --keep-definer        Keep DEFINER clauses from the dump (default: strip them)
  -y, --yes             Skip confirmation prompt
  -h, --help            Show this help

Example:
  $(basename "$0") \\
    --container utrada_mysql \\
    --user root \\
    --password 'secret' \\
    --database ut-crm-db \\
    --file ./backup_20260806_120000.sql
EOF
}

require_value() {
  local flag="$1" value="${2:-}"
  if [[ -z "$value" ]]; then
    echo "ERROR: $flag requires a non-empty value." >&2
    echo
    usage
    exit 1
  fi
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --container)
      require_value "--container" "${2:-}"
      CONTAINER="$2"; shift 2 ;;
    --container=*)
      CONTAINER="${1#*=}"
      require_value "--container" "$CONTAINER"
      shift ;;
    --user)
      require_value "--user" "${2:-}"
      DB_USER="$2"; shift 2 ;;
    --user=*)
      DB_USER="${1#*=}"
      require_value "--user" "$DB_USER"
      shift ;;
    --password)
      require_value "--password" "${2:-}"
      DB_PASS="$2"; shift 2 ;;
    --password=*)
      DB_PASS="${1#*=}"
      require_value "--password" "$DB_PASS"
      shift ;;
    --database)
      require_value "--database" "${2:-}"
      DB_NAME="$2"; shift 2 ;;
    --database=*)
      DB_NAME="${1#*=}"
      require_value "--database" "$DB_NAME"
      shift ;;
    --file)
      require_value "--file" "${2:-}"
      SQL_FILE="$2"; shift 2 ;;
    --file=*)
      SQL_FILE="${1#*=}"
      require_value "--file" "$SQL_FILE"
      shift ;;
    --keep-definer) KEEP_DEFINER=true; shift ;;
    -y|--yes)       ASSUME_YES=true; shift ;;
    -h|--help)      usage; exit 0 ;;
    *)
      echo "ERROR: Unknown argument: $1" >&2
      echo
      usage
      exit 1 ;;
  esac
done

missing=()
[[ -z "$CONTAINER" ]] && missing+=("--container")
[[ -z "$DB_USER" ]] && missing+=("--user")
[[ -z "$DB_PASS" ]] && missing+=("--password")
[[ -z "$DB_NAME" ]] && missing+=("--database")
[[ -z "$SQL_FILE" ]] && missing+=("--file")
if [[ ${#missing[@]} -gt 0 ]]; then
  echo "ERROR: Missing required argument(s): ${missing[*]}" >&2
  echo
  usage
  exit 1
fi

if [[ "$SQL_FILE" != /* ]]; then
  SQL_FILE="$(pwd)/$SQL_FILE"
fi
if [[ ! -f "$SQL_FILE" ]]; then
  echo "ERROR: SQL file not found: $SQL_FILE" >&2
  exit 1
fi
if [[ ! -r "$SQL_FILE" ]]; then
  echo "ERROR: SQL file not readable: $SQL_FILE" >&2
  exit 1
fi

if ! docker info > /dev/null 2>&1; then
  echo "ERROR: Docker is not running. Start Docker and try again." >&2
  exit 1
fi
if [[ "$(docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null || echo false)" != "true" ]]; then
  echo "ERROR: Container '$CONTAINER' is not running." >&2
  exit 1
fi

sql_exec() {
  docker exec -i -e MYSQL_PWD="$DB_PASS" "$CONTAINER" \
    mysql -u"$DB_USER" -N -e "$1"
}

CURRENT_TABLES="$(sql_exec "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME';" 2>/dev/null || echo '?')"

echo "==> staging — fresh database setup"
echo ""
echo "  Container:  $CONTAINER"
echo "  User:       $DB_USER"
echo "  Database:   $DB_NAME (currently $CURRENT_TABLES tables)"
echo "  File:       $SQL_FILE"
echo "  Size:       $(du -h "$SQL_FILE" | cut -f1)"
echo "  DEFINER:    $([[ "$KEEP_DEFINER" == true ]] && echo 'kept' || echo 'stripped')"
echo ""
echo "  WARNING: This will DROP database '$DB_NAME' and recreate it from the dump."
echo ""
if [[ "$ASSUME_YES" != true ]]; then
  read -r -p "  Proceed? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || { echo "Aborted."; exit 0; }
fi
echo ""

echo "==> Dropping database '$DB_NAME' if it exists..."
sql_exec "DROP DATABASE IF EXISTS \`$DB_NAME\`;"

echo "==> Creating database '$DB_NAME'..."
sql_exec "CREATE DATABASE \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "==> Allowing routine creation under binary logging..."
sql_exec "SET GLOBAL log_bin_trust_function_creators = 1;" || true

echo "==> Importing $(basename "$SQL_FILE")..."
ERR_LOG="$(mktemp)"
trap 'rm -f "$ERR_LOG"' EXIT

import_status=0
if [[ "$KEEP_DEFINER" == true ]]; then
  docker exec -i -e MYSQL_PWD="$DB_PASS" "$CONTAINER" \
    mysql -u"$DB_USER" "$DB_NAME" < "$SQL_FILE" 2> "$ERR_LOG" || import_status=$?
else
  sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' "$SQL_FILE" \
    | docker exec -i -e MYSQL_PWD="$DB_PASS" "$CONTAINER" \
        mysql -u"$DB_USER" "$DB_NAME" 2> "$ERR_LOG" || import_status=$?
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

echo ""
echo "==> Verifying..."
BASE_TABLES="$(sql_exec "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE';")"
VIEWS="$(sql_exec "SELECT COUNT(*) FROM information_schema.views WHERE table_schema='$DB_NAME';")"
ROUTINES="$(sql_exec "SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$DB_NAME';")"
echo "  Tables: $BASE_TABLES   Views: $VIEWS   Routines: $ROUTINES"

SAMPLE_VIEW="$(sql_exec "SELECT table_name FROM information_schema.views WHERE table_schema='$DB_NAME' ORDER BY table_name LIMIT 1;")"
if [[ -n "$SAMPLE_VIEW" ]]; then
  view_err="$(docker exec -i -e MYSQL_PWD="$DB_PASS" "$CONTAINER" \
    mysql -u"$DB_USER" "$DB_NAME" -e "SELECT 1 FROM \`$SAMPLE_VIEW\` LIMIT 1;" 2>&1 >/dev/null)" || true
  if [[ -n "$view_err" ]]; then
    echo "  view check FAILED on '$SAMPLE_VIEW':"
    echo "$view_err" | sed 's/^/      /'
    echo "    If this mentions a missing definer, re-run without --keep-definer."
    exit 1
  fi
  echo "  view check passed ('$SAMPLE_VIEW')"
fi

echo ""
echo "==> Done. '$DB_NAME' is fresh from $(basename "$SQL_FILE")."
