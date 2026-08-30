#!/usr/bin/env bash

set -euo pipefail

log_step() {
  echo "=== $1. $2 ==="
}

configure_tag() {
  log_step 1 "Configure Tag"

  main_branch="${CI_DEFAULT_BRANCH:-main}"
  release_tag="${RELEASE_TAG:?release job did not provide RELEASE_TAG}"
  release_commit="${RELEASE_COMMIT:?release job did not provide RELEASE_COMMIT}"
}

prepare_git_repository() {
  log_step 2 "Prepare Git Repository"

  git config user.name "GitLab Release Bot"
  git config user.email "gitlab-release-bot@${CI_SERVER_HOST:-localhost}"
  git remote add gitlab "${CI_REPOSITORY_URL:?CI_REPOSITORY_URL is required}"
  git fetch --quiet gitlab "refs/heads/$main_branch:refs/remotes/gitlab/$main_branch" --tags
  git fetch --quiet gitlab "$release_commit" || true
}

verify_release_commit() {
  log_step 3 "Verify Release Commit"

  if ! git cat-file -e "$release_commit^{commit}"; then
    printf 'Release commit %s was not found.\n' "$release_commit" >&2
    exit 1
  fi
}

publish_tag() {
  log_step 4 "Publish Tag"

  if git rev-parse --verify --quiet "refs/tags/$release_tag" >/dev/null; then
    existing_commit="$(git rev-list -n 1 "$release_tag")"
    if [[ "$existing_commit" == "$release_commit" ]]; then
      printf 'Tag %s already points at %s.\n' "$release_tag" "$release_commit"
      return
    fi

    printf 'Tag %s already exists at %s, expected %s.\n' "$release_tag" "$existing_commit" "$release_commit" >&2
    exit 1
  fi

  git tag -a "$release_tag" -m "Release $release_tag" "$release_commit"
  git push gitlab "refs/tags/$release_tag"
}

main() {
  configure_tag
  prepare_git_repository
  verify_release_commit
  publish_tag
}

main "$@"
