#!/usr/bin/env bash

set -euo pipefail

PRETTIER_VERSION="3.9.6"
PRETTIER_IGNORE_PATH="scripts/ci/prettierignore"

log_step() {
  echo "=== $1. $2 ==="
}

enter_repository_root() {
  log_step 1 "Enter Repository Root"

  cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
}

collect_prettier_paths() {
  log_step 2 "Collect Prettier Paths"

  prettier_paths=()

  local dir
  for dir in admin_frontend client_frontend scripts infra; do
    if [[ -d "$dir" ]]; then
      prettier_paths+=("$dir")
    fi
  done

  if [[ -d back-end ]]; then
    while IFS= read -r -d '' dir; do
      prettier_paths+=("$dir")
    done < <(
      find back-end -mindepth 1 -maxdepth 1 -type d \
        ! -name vendor \
        ! -name runtime \
        ! -name cache \
        ! -name logs \
        ! -name storage \
        ! -name uploads \
        -print0
    )
    while IFS= read -r -d '' file; do
      prettier_paths+=("$file")
    done < <(find back-end -maxdepth 1 -type f -print0)
  fi

  local file
  shopt -s nullglob
  for file in .gitlab-ci.yml .pre-commit-config.yaml *.md *.yml *.yaml *.json; do
    if [[ -e "$file" ]]; then
      prettier_paths+=("$file")
    fi
  done
}

run_prettier_check() {
  log_step 3 "Run Prettier Check"

  local output
  local summary

  if ! output="$(
    npx --yes "prettier@${PRETTIER_VERSION}" --check \
      --ignore-path "$PRETTIER_IGNORE_PATH" \
      --ignore-unknown \
      --no-error-on-unmatched-pattern \
      "${prettier_paths[@]}" 2>&1
  )"; then
    summary="$(printf '%s\n' "$output" | grep -E 'Code style issues found in [0-9]+ files' || true)"
    if [[ -n "$summary" ]]; then
      printf '%s\n' "$summary"
      echo "Format: warning only"
      return
    fi

    printf '%s\n' "$output"
    exit 1
  fi

  echo "Format: all clear"
}

main() {
  enter_repository_root
  collect_prettier_paths
  run_prettier_check
}

main "$@"
