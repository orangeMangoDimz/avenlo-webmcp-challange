#!/usr/bin/env bash

set -euo pipefail

GITLEAKS_VERSION="8.30.1"
GITLEAKS_CONFIG="scripts/ci/gitleaks.toml"
MAX_SCAN_COMMITS=20
ZERO_SHA="0000000000000000000000000000000000000000"

log_step() {
  echo "=== $1. $2 ==="
}

enter_repository_root() {
  log_step 1 "Enter Repository Root"

  cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
}

configure_gitleaks() {
  log_step 2 "Configure Gitleaks"

  gitleaks_cache_dir="${GITLEAKS_CACHE_DIR:-$PWD/.cache/secret-scan}"
  gitleaks_bin="$gitleaks_cache_dir/gitleaks"

  mkdir -p "$gitleaks_cache_dir"
}

install_gitleaks() {
  log_step 3 "Install Gitleaks"

  if [[ -x "$gitleaks_bin" ]]; then
    return
  fi

  local arch gitleaks_arch
  arch="$(uname -m)"
  case "$arch" in
    x86_64) gitleaks_arch="x64" ;;
    aarch64|arm64) gitleaks_arch="arm64" ;;
    *)
      printf 'unsupported arch: %s\n' "$arch" >&2
      exit 1
      ;;
  esac

  curl -sSfL "https://github.com/gitleaks/gitleaks/releases/download/v${GITLEAKS_VERSION}/gitleaks_${GITLEAKS_VERSION}_linux_${gitleaks_arch}.tar.gz" \
    | tar -xz -C "$gitleaks_cache_dir" gitleaks
  chmod +x "$gitleaks_bin"
}

select_scan_range() {
  log_step 4 "Select Scan Range"

  local scan_commit commit_count
  scan_commit="${CI_COMMIT_SHA:-HEAD}"

  if [[ -n "${CI_COMMIT_BEFORE_SHA:-}" && "${CI_COMMIT_BEFORE_SHA}" != "$ZERO_SHA" ]] \
    && git cat-file -e "${CI_COMMIT_BEFORE_SHA}^{commit}" 2>/dev/null; then
    commit_count="$(git rev-list --count "${CI_COMMIT_BEFORE_SHA}..${scan_commit}")"
    if [[ "$commit_count" -le "$MAX_SCAN_COMMITS" ]]; then
      gitleaks_log_opts="${CI_COMMIT_BEFORE_SHA}..${scan_commit}"
      echo "Scan range: $gitleaks_log_opts ($commit_count commits)"
      return
    fi
  fi

  gitleaks_log_opts="-n 1 ${scan_commit}"
  echo "Scan range: $gitleaks_log_opts"
}

detect_secrets() {
  log_step 5 "Detect Secrets"

  local output
  local -a args=(
    git
    --redact
    --no-banner
    --verbose
    --log-level warn
    --config "$GITLEAKS_CONFIG"
    --log-opts="$gitleaks_log_opts"
  )

  if ! output="$("$gitleaks_bin" "${args[@]}" 2>&1)"; then
    printf '%s\n' "$output"
    exit 1
  fi

  echo "Secrets: all clear"
}

main() {
  enter_repository_root
  configure_gitleaks
  install_gitleaks
  select_scan_range
  detect_secrets
}

main "$@"
