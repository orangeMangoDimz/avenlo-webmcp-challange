#!/usr/bin/env bash

set -euo pipefail

log_step() {
  echo "=== $1. $2 ==="
}

validate_sync_configuration() {
  log_step 1 "Validate Sync Configuration"

  : "${GITHUB_TOKEN:?GITHUB_TOKEN must be configured as a protected CI/CD variable}"
  : "${RELEASE_TAG:?release job did not provide RELEASE_TAG}"
}

select_sync_branches() {
  log_step 2 "Select Sync Branches"

  sync_branch="${SYNC_BRANCH:-${CI_DEFAULT_BRANCH:-main}}"
  github_sync_branch="${GITHUB_SYNC_BRANCH:-$sync_branch}"
}

configure_git_remotes() {
  log_step 3 "Configure Git Remotes"

  git remote add gitlab "$CI_REPOSITORY_URL"
  git fetch --quiet gitlab "refs/heads/$sync_branch:refs/remotes/gitlab/$sync_branch" --tags
  git remote add github "https://x-access-token:${GITHUB_TOKEN}@github.com/${GITHUB_REPOSITORY}.git"
}

push_github_branch() {
  log_step 4 "Push GitHub Branch"

  git push github "refs/remotes/gitlab/$sync_branch:refs/heads/$github_sync_branch"
}

push_github_release_tag() {
  log_step 5 "Push GitHub Release Tag"

  git push github "refs/tags/$RELEASE_TAG:refs/tags/$RELEASE_TAG"
}

main() {
  validate_sync_configuration
  select_sync_branches
  configure_git_remotes
  push_github_branch
  push_github_release_tag
}

main "$@"
