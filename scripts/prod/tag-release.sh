#!/usr/bin/env bash

set -euo pipefail

log_step() {
  echo "=== $1. $2 ==="
}

changelog_tag_from_subject() {
  local subject="$1"

  if [[ "$subject" =~ update[[:space:]]changelog[[:space:]]for[[:space:]](v[0-9]+\.[0-9]+\.[0-9]+) ]]; then
    printf '%s\n' "${BASH_REMATCH[1]}"
  fi
}

is_version_greater_or_equal() {
  local IFS=.
  local -a left right
  read -r -a left <<< "$1"
  read -r -a right <<< "$2"
  local i

  for i in 0 1 2; do
    if (( ${left[i]:-0} > ${right[i]:-0} )); then
      return 0
    fi
    if (( ${left[i]:-0} < ${right[i]:-0} )); then
      return 1
    fi
  done

  return 0
}

reuse_existing_release() {
  local existing_tag="$1"
  local existing_commit="$2"

  printf 'Release %s already exists for %s.\n' "$existing_tag" "$release_sha"
  existing_release=true
  release_tag="$existing_tag"
  release_commit="$existing_commit"
}

cleanup_temporary_files() {
  rm -f "${changelog_file:-}"
}

configure_release() {
  log_step 1 "Configure Release"

  main_branch="${CI_DEFAULT_BRANCH:-main}"
  release_sha="${CI_COMMIT_SHA:?CI_COMMIT_SHA is required}"
  project_url="${CI_PROJECT_URL:?CI_PROJECT_URL is required}"
  release_env_file="release.env"

  if [[ "${CI_COMMIT_BRANCH:-}" != "$main_branch" ]]; then
    printf 'Release job only runs on %s.\n' "$main_branch" >&2
    exit 1
  fi
}

prepare_git_repository() {
  log_step 2 "Prepare Git Repository"

  git config user.name "GitLab Release Bot"
  git config user.email "gitlab-release-bot@${CI_SERVER_HOST:-localhost}"
  git remote add gitlab "${CI_REPOSITORY_URL:?CI_REPOSITORY_URL is required}"
  git fetch --quiet gitlab "refs/heads/$main_branch:refs/remotes/gitlab/$main_branch" --tags
  git checkout --detach "$release_sha"
}

check_existing_release() {
  log_step 3 "Check Existing Release"

  existing_release=false

  mapfile -t containing_tags < <(
    git tag --contains "$release_sha" --list 'v*' --sort=version:refname \
      | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' || true
  )

  for candidate_tag in "${containing_tags[@]}"; do
    candidate_commit="$(git rev-list -n 1 "$candidate_tag")"
    candidate_parent="$(git rev-parse "$candidate_commit^" 2>/dev/null || true)"

    if [[ "$candidate_parent" == "$release_sha" ]]; then
      reuse_existing_release "$candidate_tag" "$candidate_commit"
      return
    fi
  done

  while IFS=$'\t' read -r pending_commit pending_subject; do
    [[ -z "$pending_commit" ]] && continue

    pending_tag="$(changelog_tag_from_subject "$pending_subject")"
    pending_parent="$(git rev-parse "$pending_commit^" 2>/dev/null || true)"

    if [[ -n "$pending_tag" && "$pending_parent" == "$release_sha" ]]; then
      reuse_existing_release "$pending_tag" "$pending_commit"
      return
    fi
  done < <(
    git log --format='%H%x09%s' --grep='chore(release): update changelog for v' "gitlab/$main_branch"
  )
}

select_commit_range() {
  log_step 4 "Select Commit Range"

  mapfile -t merged_tags < <(
    git tag --merged "$release_sha" --list 'v*' --sort=-version:refname \
      | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' || true
  )

  base_tag="${merged_tags[0]:-}"
  tag_version="${base_tag#v}"
  changelog_commit=""
  changelog_version=""
  changelog_line="$(
    git log -1 --format='%H%x09%s' --grep='chore(release): update changelog for v' \
      "$release_sha" "gitlab/$main_branch" || true
  )"

  if [[ -n "$changelog_line" ]]; then
    IFS=$'\t' read -r changelog_commit changelog_subject <<< "$changelog_line"
    changelog_tag="$(changelog_tag_from_subject "$changelog_subject")"
    changelog_version="${changelog_tag#v}"
  fi

  if [[ -z "$tag_version" && -z "$changelog_version" ]]; then
    base_version='0.0.0'
    commit_range="$release_sha"
  elif [[ -n "$changelog_version" ]] && { [[ -z "$tag_version" ]] || is_version_greater_or_equal "$changelog_version" "$tag_version"; }; then
    base_version="$changelog_version"
    commit_range="$changelog_commit..$release_sha"
  else
    base_version="$tag_version"
    commit_range="$base_tag..$release_sha"
  fi
}

calculate_release_version() {
  log_step 5 "Calculate Release Version"

  IFS='.' read -r major minor patch <<< "$base_version"

  if git log --no-merges --format='%B' "$commit_range" \
    | grep -Eq '^[[:alpha:]][[:alnum:]_-]*(\([^)]*\))?!:|^BREAKING[ -]CHANGE:'; then
    major=$((major + 1))
    minor=0
    patch=0
  elif git log --no-merges --format='%s' "$commit_range" \
    | grep -Eq '^feat(\([^)]*\))?:'; then
    minor=$((minor + 1))
    patch=0
  else
    patch=$((patch + 1))
  fi

  release_tag="v${major}.${minor}.${patch}"
  release_version="${release_tag#v}"

  if git rev-parse --verify --quiet "refs/tags/$release_tag" >/dev/null; then
    printf 'Tag %s already exists but does not match this release.\n' "$release_tag" >&2
    exit 1
  fi
}

prepare_temporary_files() {
  log_step 6 "Prepare Temporary Files"

  notes_file="release-notes.md"
  changelog_file="$(mktemp)"
  trap cleanup_temporary_files EXIT
}

generate_release_notes() {
  log_step 7 "Generate Release Notes"

  local -A category_titles=(
    [Added]='Added'
    [Fixed]='Fixed'
    [Changed]='Changed'
    [Performance]='Performance'
    [Other]='Other'
  )
  local -A category_entries=(
    [Added]=''
    [Fixed]=''
    [Changed]=''
    [Performance]=''
    [Other]=''
  )
  local -a category_order=(Added Fixed Changed Performance Other)
  local commit subject category entries
  local has_entries=false

  while IFS=$'\t' read -r commit subject; do
    [[ -z "$commit" ]] && continue

    case "$subject" in
      feat!*|feat\(*|feat:*) category='Added' ;;
      fix!*|fix\(*|fix:*) category='Fixed' ;;
      perf!*|perf\(*|perf:*) category='Performance' ;;
      refactor!*|refactor\(*|refactor:*|style!*|style\(*|style:*) category='Changed' ;;
      *) category='Other' ;;
    esac

    category_entries["$category"]+="- $subject ([${commit:0:7}]($project_url/-/commit/$commit))"$'\n'
  done < <(git log --no-merges --format='%H%x09%s' "$commit_range")

  {
    printf '## [%s] - %s\n\n' "$release_version" "$(date -u +%F)"

    for category in "${category_order[@]}"; do
      entries="${category_entries[$category]}"
      [[ -z "$entries" ]] && continue

      has_entries=true
      printf '### %s\n\n%s\n' "${category_titles[$category]}" "$entries"
    done

    if [[ "$has_entries" == false ]]; then
      printf '### Other\n\n- Release changes are included in this tag.\n\n'
    fi
  } > "$notes_file"
}

update_changelog() {
  log_step 8 "Update Changelog"

  awk -v notes_file="$notes_file" '
    /^## \[/ && !inserted {
      while ((getline line < notes_file) > 0) print line
      close(notes_file)
      print ""
      inserted = 1
    }
    { print }
    END {
      if (!inserted) {
        print ""
        while ((getline line < notes_file) > 0) print line
        close(notes_file)
      }
    }
  ' CHANGELOG.md > "$changelog_file"

  mv "$changelog_file" CHANGELOG.md
}

publish_release() {
  log_step 9 "Publish Release"

  git add CHANGELOG.md
  git commit -m "chore(release): update changelog for $release_tag [skip ci]"
  git push gitlab "HEAD:refs/heads/$main_branch"
}

load_existing_release_notes() {
  log_step 4 "Load Existing Release Notes"

  local version="${release_tag#v}"
  local changelog_source
  changelog_source="$(mktemp)"

  if git cat-file -e "${release_commit}:CHANGELOG.md" 2>/dev/null; then
    git show "${release_commit}:CHANGELOG.md" > "$changelog_source"
  elif git cat-file -e "${release_tag}:CHANGELOG.md" 2>/dev/null; then
    git show "${release_tag}:CHANGELOG.md" > "$changelog_source"
  elif [[ -f CHANGELOG.md ]]; then
    cp CHANGELOG.md "$changelog_source"
  else
    printf 'Release %s\n' "$release_tag" > "$notes_file"
    rm -f "$changelog_source"
    return
  fi

  awk -v version="$version" '
    $0 ~ "^## \\[" version "\\]" {
      capturing = 1
      print
      next
    }
    capturing && /^## \[/ { exit }
    capturing { print }
  ' "$changelog_source" > "$notes_file"

  rm -f "$changelog_source"

  if [[ ! -s "$notes_file" ]]; then
    printf 'Release %s\n' "$release_tag" > "$notes_file"
  fi
}

write_release_environment() {
  log_step 10 "Write Release Environment"

  if [[ -z "${release_commit:-}" ]]; then
    release_commit="$(git rev-parse HEAD)"
  fi

  printf 'RELEASE_TAG=%s\nRELEASE_COMMIT=%s\n' "$release_tag" "$release_commit" > "$release_env_file"
}

main() {
  configure_release
  prepare_git_repository
  check_existing_release

  if [[ "$existing_release" == true ]]; then
    prepare_temporary_files
    load_existing_release_notes
  else
    select_commit_range
    calculate_release_version
    prepare_temporary_files
    generate_release_notes
    update_changelog
    publish_release
  fi

  write_release_environment
}

main "$@"
