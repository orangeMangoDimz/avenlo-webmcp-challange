#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
ENV_FILE="$PROJECT_ROOT/.env"
COMPOSE_FILE="$PROJECT_ROOT/infra/docker/dev/docker-compose.yml"
WIPE=false
ASSUME_YES=false

for arg in "$@"; do
  case "$arg" in
    -v|--volumes) WIPE=true ;;
    -y|--yes) ASSUME_YES=true ;;
    -h|--help) echo "Usage: $0 [-v|--volumes] [-y|--yes]"; exit 0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

if [[ "$WIPE" == true && "$ASSUME_YES" != true ]]; then
  read -r -p "Delete all development volumes, including MySQL data? [y/N] " confirm
  [[ "$confirm" =~ ^[Yy]$ ]] || exit 0
fi

compose=(docker compose --env-file "$ENV_FILE" -f "$COMPOSE_FILE")
args=(down --remove-orphans)
[[ "$WIPE" == true ]] && args+=(-v)
"${compose[@]}" "${args[@]}"
