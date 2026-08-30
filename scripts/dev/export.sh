#!/bin/bash
set -euo pipefail

# Export the local MySQL Docker container's database to a .sql file.
#
#   ./scripts/dev/export.sh
#   ./scripts/dev/export.sh --output back-end/database/my_snapshot.sql

# -- paths --
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/infra/docker/dev/docker-compose.yml"
EXPORT_DIR="$SCRIPT_DIR/exports"

# -- defaults --
DB_SERVICE="mysql"
OUTPUT_FILE=""

compose() {
  docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE" "$@"
}

usage() {
  cat <<EOF
Usage: $(basename "$0") [options]

Export the '$DB_SERVICE' service's database to a .sql file.

Options:
  -o, --output <path>   Destination file (default: $EXPORT_DIR/export_<timestamp>.sql)
  -h, --help            Show this help
EOF
}

# -- parse args --
while [[ $# -gt 0 ]]; do
  case "$1" in
    -o|--output)
      if [[ $# -lt 2 || -z "${2:-}" ]]; then
        echo "ERROR: --output requires an argument." >&2; echo; usage; exit 1
      fi
      OUTPUT_FILE="$2"; shift 2 ;;
    --output=*)  OUTPUT_FILE="${1#*=}"; shift ;;
    -h|--help)   usage; exit 0 ;;
    *) echo "ERROR: Unknown argument: $1" >&2; echo; usage; exit 1 ;;
  esac
done

if [[ -z "$OUTPUT_FILE" ]]; then
  OUTPUT_FILE="$EXPORT_DIR/export_$(date +%Y%m%d_%H%M%S).sql"
elif [[ "$OUTPUT_FILE" != /* ]]; then
  OUTPUT_FILE="$PROJECT_ROOT/$OUTPUT_FILE"
fi

if [[ -e "$OUTPUT_FILE" ]]; then
  echo "ERROR: output file already exists: $OUTPUT_FILE" >&2
  exit 1
fi

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

mkdir -p "$(dirname "$OUTPUT_FILE")"

echo "==> utrada-crm dev — export database"
echo ""
echo "  Service:    $DB_SERVICE"
echo "  Database:   $DB_NAME"
echo "  Output:     $OUTPUT_FILE"
echo ""

TMP_FILE="$(mktemp)"
ERR_LOG="$(mktemp)"
trap 'rm -f "$TMP_FILE" "$ERR_LOG"' EXIT

echo "==> Dumping (this can take a while for large databases)..."
dump_status=0
compose exec -T -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$DB_SERVICE" \
  mysqldump -uroot --single-transaction --routines --events "$DB_NAME" \
  > "$TMP_FILE" 2> "$ERR_LOG" || dump_status=$?

if [[ $dump_status -ne 0 ]]; then
  echo "ERROR: mysqldump failed (exit $dump_status)." >&2
  [[ -s "$ERR_LOG" ]] && { echo "---- mysqldump stderr ----" >&2; cat "$ERR_LOG" >&2; }
  exit 1
fi

if [[ -s "$ERR_LOG" ]]; then
  echo "  Note: mysqldump emitted warnings:"
  sed 's/^/    /' "$ERR_LOG"
fi

# -- verify: non-empty and ends with mysqldump's own completion footer --
# A dump that was cut short (container killed, disk full, network blip) still
# produces a file — checking for the footer catches a truncated dump that a bare
# exit-code check would miss.
if [[ ! -s "$TMP_FILE" ]]; then
  echo "ERROR: dump file is empty." >&2
  exit 1
fi

if ! tail -n 5 "$TMP_FILE" | grep -q "Dump completed"; then
  echo "ERROR: dump looks incomplete (missing 'Dump completed' footer)." >&2
  exit 1
fi

mv "$TMP_FILE" "$OUTPUT_FILE"

echo ""
echo "==> Done."
echo "  File: $OUTPUT_FILE"
echo "  Size: $(du -h "$OUTPUT_FILE" | cut -f1)"
