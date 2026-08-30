#!/usr/bin/env bash

set -euo pipefail

HTTP_OK=200
HTTP_CREATED=201
HTTP_NOT_FOUND=404
HTTP_CONFLICT=409
NOTES_FILE="release-notes.md"

log_step() {
  echo "=== $1. $2 ==="
}

validate_configuration() {
  log_step 1 "Validate Configuration"

  : "${CI_API_V4_URL:?CI_API_V4_URL is required}"
  : "${CI_PROJECT_ID:?CI_PROJECT_ID is required}"
  : "${RELEASE_TAG:?release job did not provide RELEASE_TAG}"
}

select_auth_header() {
  log_step 2 "Select Auth Header"

  if [[ -n "${GITLAB_TOKEN:-}" ]]; then
    auth_header="PRIVATE-TOKEN: ${GITLAB_TOKEN}"
    return
  fi

  : "${CI_JOB_TOKEN:?CI_JOB_TOKEN is required}"
  auth_header="JOB-TOKEN: ${CI_JOB_TOKEN}"
}

build_api_urls() {
  log_step 3 "Build API URLs"

  encoded_tag="$(jq -nr --arg tag "$RELEASE_TAG" '$tag | @uri')"
  releases_url="${CI_API_V4_URL}/projects/${CI_PROJECT_ID}/releases"
  release_url="${releases_url}/${encoded_tag}"
}

check_existing_gitlab_release() {
  log_step 4 "Check Existing GitLab Release"

  local body_file http_status
  body_file="$(mktemp)"
  http_status="$(
    curl -sS -o "$body_file" -w '%{http_code}' \
      --header "$auth_header" \
      "$release_url"
  )"

  case "$http_status" in
    "$HTTP_OK")
      printf 'GitLab release %s already exists.\n' "$RELEASE_TAG"
      rm -f "$body_file"
      exit 0
      ;;
    "$HTTP_NOT_FOUND")
      rm -f "$body_file"
      ;;
    *)
      printf 'Failed to check GitLab release %s (HTTP %s).\n' "$RELEASE_TAG" "$http_status" >&2
      cat "$body_file" >&2
      rm -f "$body_file"
      exit 1
      ;;
  esac
}

build_release_payload() {
  log_step 5 "Build Release Payload"

  payload_file="$(mktemp)"

  if [[ -s "$NOTES_FILE" ]]; then
    jq -n \
      --arg name "Release ${RELEASE_TAG}" \
      --arg tag_name "$RELEASE_TAG" \
      --rawfile description "$NOTES_FILE" \
      '{name: $name, tag_name: $tag_name, description: $description}' \
      > "$payload_file"
    return
  fi

  jq -n \
    --arg name "Release ${RELEASE_TAG}" \
    --arg tag_name "$RELEASE_TAG" \
    --arg description "Release ${RELEASE_TAG}" \
    '{name: $name, tag_name: $tag_name, description: $description}' \
    > "$payload_file"
}

create_gitlab_release() {
  log_step 6 "Create GitLab Release"

  local body_file http_status
  body_file="$(mktemp)"
  http_status="$(
    curl -sS -o "$body_file" -w '%{http_code}' \
      --request POST \
      --header "$auth_header" \
      --header "Content-Type: application/json" \
      --data @"$payload_file" \
      "$releases_url"
  )"

  rm -f "$payload_file"

  case "$http_status" in
    "$HTTP_CREATED"|"$HTTP_CONFLICT")
      printf 'GitLab release %s published.\n' "$RELEASE_TAG"
      rm -f "$body_file"
      ;;
    *)
      printf 'Failed to create GitLab release %s (HTTP %s).\n' "$RELEASE_TAG" "$http_status" >&2
      cat "$body_file" >&2
      rm -f "$body_file"
      exit 1
      ;;
  esac
}

main() {
  validate_configuration
  select_auth_header
  build_api_urls
  check_existing_gitlab_release
  build_release_payload
  create_gitlab_release
}

main "$@"
