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
    -h|--help)
      echo "Usage: $0 [--tools]"
      echo "  --tools  Also start phpMyAdmin"
      exit 0
      ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$PROJECT_ROOT/.env.example" "$ENV_FILE"
  echo "Created $ENV_FILE from .env.example. Fill in its values, then run this command again."
  exit 1
fi

docker info >/dev/null 2>&1 || { echo "Docker is not running." >&2; exit 1; }

compose=(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE")
[[ "$WITH_TOOLS" == true ]] && compose+=(--profile tools)

cd "$PROJECT_ROOT"
"${compose[@]}" up --build -d --remove-orphans
"${compose[@]}" ps

echo
echo "API:    http://$("${compose[@]}" port api 8000)"
echo "Admin:  http://$("${compose[@]}" port admin 3000)"
echo "Client: http://$("${compose[@]}" port client 3001)"
[[ "$WITH_TOOLS" == true ]] && echo "DB UI:  http://$("${compose[@]}" port phpmyadmin 80)"
