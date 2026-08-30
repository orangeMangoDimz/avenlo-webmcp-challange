#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/infra/docker/dev/docker-compose.yml"
WITH_TOOLS=false

for arg in "$@"; do
  case "$arg" in
    --tools) WITH_TOOLS=true ;;
    -y|--yes) ;;
    -h|--help) echo "Usage: $0 [--tools]"; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

compose=(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE")
[[ "$WITH_TOOLS" == true ]] && compose+=(--profile tools)

cd "$PROJECT_ROOT"
"${compose[@]}" down --remove-orphans
"${compose[@]}" build --no-cache
"${compose[@]}" up -d
"${compose[@]}" ps
