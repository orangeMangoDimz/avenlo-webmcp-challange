#!/usr/bin/env bash
set -euo pipefail

STACK_DIR="${STACK_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"
STACK_ENV="$STACK_DIR/stack.env"
BACKEND_ENV="$STACK_DIR/backend.env"
COMPOSE_FILE="$STACK_DIR/docker-compose.yml"

read_env() {
  local key="$1" line
  line="$(grep -E "^[[:space:]]*${key}=" "$STACK_ENV" | tail -n1)"
  printf '%s' "${line#*=}"
}

[[ -f "$STACK_ENV" && -f "$BACKEND_ENV" && -f "$COMPOSE_FILE" ]] \
  || { echo "Production stack files are missing in $STACK_DIR." >&2; exit 1; }

compose() {
  sudo docker compose --env-file "$STACK_ENV" --env-file "$BACKEND_ENV" -f "$COMPOSE_FILE" "$@"
}

action="${1:-}"
case "$action" in
  issue)
    admin_host="$(read_env ADMIN_HOST)"
    client_host="$(read_env CLIENT_HOST)"
    api_host="$(read_env API_HOST)"
    cert_name="$(read_env TLS_CERT_NAME)"
    email="$(read_env CERTBOT_EMAIL)"

    domains=()
    for candidate in "$admin_host" "$client_host" "$api_host"; do
      duplicate=false
      for existing in "${domains[@]:-}"; do
        [[ "$existing" == "$candidate" ]] && duplicate=true
      done
      [[ "$duplicate" == false ]] && domains+=("$candidate")
    done

    domain_args=()
    for domain in "${domains[@]}"; do
      domain_args+=(-d "$domain")
    done

    compose stop gateway >/dev/null 2>&1 || true
    compose --profile tls run --rm --service-ports certbot certonly \
      --standalone \
      --non-interactive \
      --agree-tos \
      --email "$email" \
      --cert-name "$cert_name" \
      "${domain_args[@]}"
    compose up -d gateway
    ;;
  renew)
    compose --profile tls run --rm certbot renew \
      --webroot \
      --webroot-path /var/www/certbot \
      --quiet
    compose exec -T gateway nginx -s reload
    ;;
  *)
    echo "Usage: $0 issue|renew" >&2
    exit 1
    ;;
esac
