#!/usr/bin/env bash

set -euo pipefail

log_step() {
  echo "=== $1. $2 ==="
}

enter_repository_root() {
  log_step 1 "Enter Repository Root"

  cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
}

lint_php_files() {
  log_step 2 "Lint PHP Files"

  local php_status=0
  local file
  local output

  while IFS= read -r -d '' file; do
    if ! output="$(php -l "$file" 2>&1)"; then
      printf '%s\n' "$output"
      php_status=1
    fi
  done < <(
    find back-end \
      \( -path '*/vendor' -o -path '*/runtime' -o -path '*/cache' -o -path '*/logs' -o -path '*/storage' -o -path '*/uploads' \) -prune \
      -o -name '*.php' -print0
  )

  if [[ "$php_status" -ne 0 ]]; then
    exit 1
  fi

  echo "PHP syntax: all clear"
}

lint_ci_shell_scripts() {
  log_step 3 "Lint CI Shell Scripts"

  find scripts/ci -name '*.sh' -print0 | xargs -0 -r shellcheck --severity=warning
}

main() {
  enter_repository_root
  lint_php_files
  lint_ci_shell_scripts
}

main "$@"
