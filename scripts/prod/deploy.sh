#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DEPLOY_DIR="${DEPLOY_DIR:-/opt/utrada-crm}"
COMPOSE_SOURCE="$PROJECT_ROOT/infra/docker/prod/docker-compose.yml"
GATEWAY_SOURCE="$PROJECT_ROOT/infra/docker/prod/nginx/gateway.conf.template"
TLS_SOURCE="$PROJECT_ROOT/scripts/prod/tls.sh"
RESTORE_SOURCE="$PROJECT_ROOT/scripts/prod/restore.sh"
BACKUP_SOURCE="$PROJECT_ROOT/scripts/prod/backup.sh"

required=(
  ADMIN_HOST API_HOST CERTBOT_EMAIL CLIENT_HOST DEPLOY_BACKEND_ENV_FILE
  DEPLOY_SERVER_HOST DEPLOY_SERVER_USER IMAGE_TAG REGISTRY_DEPLOY_PASSWORD
  REGISTRY_DEPLOY_USER REGISTRY_IMAGE SSH_PRIVATE_KEY TLS_CERT_NAME
)
for name in "${required[@]}"; do
  [[ -n "${!name:-}" ]] || { echo "$name is required." >&2; exit 1; }
done

for host in "$ADMIN_HOST" "$CLIENT_HOST" "$API_HOST"; do
  [[ "$host" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Invalid hostname: $host" >&2; exit 1; }
done
[[ "$TLS_CERT_NAME" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Invalid TLS_CERT_NAME." >&2; exit 1; }
[[ "$IMAGE_TAG" =~ ^[A-Za-z0-9._-]+$ ]] || { echo "Invalid IMAGE_TAG." >&2; exit 1; }
[[ "$REGISTRY_IMAGE" =~ ^[A-Za-z0-9._:/-]+$ ]] || { echo "Invalid REGISTRY_IMAGE." >&2; exit 1; }
[[ "$REGISTRY_DEPLOY_USER" =~ ^[A-Za-z0-9_.@+-]+$ ]] || { echo "Invalid registry user." >&2; exit 1; }
[[ "$DEPLOY_SERVER_USER" =~ ^[A-Za-z_][A-Za-z0-9_.-]*$ ]] || { echo "Invalid deploy user." >&2; exit 1; }
[[ "$DEPLOY_SERVER_HOST" =~ ^[A-Za-z0-9.-]+$ ]] || { echo "Invalid deploy host." >&2; exit 1; }
[[ "$DEPLOY_DIR" =~ ^/[A-Za-z0-9._/-]+$ ]] || { echo "Invalid DEPLOY_DIR." >&2; exit 1; }
[[ "$CERTBOT_EMAIL" =~ ^[^[:space:]@]+@[^[:space:]@]+$ ]] || { echo "Invalid CERTBOT_EMAIL." >&2; exit 1; }
[[ -f "$DEPLOY_BACKEND_ENV_FILE" ]] || { echo "Backend environment file not found." >&2; exit 1; }

COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-utrada-crm}"
CI_REGISTRY="${CI_REGISTRY:-registry.gitlab.com}"
[[ "$COMPOSE_PROJECT_NAME" =~ ^[a-z0-9][a-z0-9_-]+$ ]] || { echo "Invalid COMPOSE_PROJECT_NAME." >&2; exit 1; }
[[ "$CI_REGISTRY" =~ ^[A-Za-z0-9.:-]+$ ]] || { echo "Invalid CI_REGISTRY." >&2; exit 1; }

ADMIN_ORIGIN="${ADMIN_ORIGIN:-https://$ADMIN_HOST}"
CLIENT_ORIGIN="${CLIENT_ORIGIN:-https://$CLIENT_HOST}"
ADMIN_ALT_ORIGIN="${ADMIN_ALT_ORIGIN:-https://invalid-admin.example}"
CLIENT_ALT_ORIGIN="${CLIENT_ALT_ORIGIN:-https://invalid-client.example}"
for origin in "$ADMIN_ORIGIN" "$CLIENT_ORIGIN" "$ADMIN_ALT_ORIGIN" "$CLIENT_ALT_ORIGIN"; do
  [[ "$origin" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?$ ]] || { echo "Invalid origin: $origin" >&2; exit 1; }
done

stack_env="$(mktemp)"
cleanup() { rm -f "$stack_env"; }
trap cleanup EXIT

cat >"$stack_env" <<EOF
COMPOSE_PROJECT_NAME=$COMPOSE_PROJECT_NAME
REGISTRY_IMAGE=$REGISTRY_IMAGE
IMAGE_TAG=$IMAGE_TAG
BACKEND_ENV_FILE=./backend.env
ADMIN_HOST=$ADMIN_HOST
CLIENT_HOST=$CLIENT_HOST
API_HOST=$API_HOST
ADMIN_ORIGIN=$ADMIN_ORIGIN
CLIENT_ORIGIN=$CLIENT_ORIGIN
ADMIN_ALT_ORIGIN=$ADMIN_ALT_ORIGIN
CLIENT_ALT_ORIGIN=$CLIENT_ALT_ORIGIN
TLS_CERT_NAME=$TLS_CERT_NAME
CERTBOT_EMAIL=$CERTBOT_EMAIL
EOF

eval "$(ssh-agent -s)" >/dev/null
printf '%s\n' "$SSH_PRIVATE_KEY" | tr -d '\r' | ssh-add - >/dev/null
mkdir -p "$HOME/.ssh"
chmod 700 "$HOME/.ssh"
ssh-keyscan "$DEPLOY_SERVER_HOST" >>"$HOME/.ssh/known_hosts"
chmod 600 "$HOME/.ssh/known_hosts"

remote="$DEPLOY_SERVER_USER@$DEPLOY_SERVER_HOST"
run_id="${CI_PIPELINE_ID:-$$}"
[[ "$run_id" =~ ^[0-9]+$ ]] || { echo "Invalid pipeline ID." >&2; exit 1; }
remote_tmp="/tmp/utrada-$run_id"

ssh "$remote" "mkdir -p '$remote_tmp/nginx'"
scp "$COMPOSE_SOURCE" "$remote:$remote_tmp/docker-compose.yml"
scp "$GATEWAY_SOURCE" "$remote:$remote_tmp/nginx/gateway.conf.template"
scp "$TLS_SOURCE" "$remote:$remote_tmp/tls.sh"
scp "$RESTORE_SOURCE" "$remote:$remote_tmp/restore.sh"
scp "$BACKUP_SOURCE" "$remote:$remote_tmp/backup.sh"
scp "$stack_env" "$remote:$remote_tmp/stack.env"
scp "$DEPLOY_BACKEND_ENV_FILE" "$remote:$remote_tmp/backend.env"

ssh "$remote" "
  set -e
  sudo mkdir -p '$DEPLOY_DIR/nginx'
  sudo install -m 644 '$remote_tmp/docker-compose.yml' '$DEPLOY_DIR/docker-compose.yml'
  sudo install -m 644 '$remote_tmp/nginx/gateway.conf.template' '$DEPLOY_DIR/nginx/gateway.conf.template'
  sudo install -m 755 '$remote_tmp/tls.sh' '$DEPLOY_DIR/tls.sh'
  sudo install -m 755 '$remote_tmp/restore.sh' '$DEPLOY_DIR/restore.sh'
  sudo install -m 755 '$remote_tmp/backup.sh' '$DEPLOY_DIR/backup.sh'
  sudo install -m 600 '$remote_tmp/stack.env' '$DEPLOY_DIR/stack.env'
  sudo install -m 600 '$remote_tmp/backend.env' '$DEPLOY_DIR/backend.env'
  rm -rf '$remote_tmp'
"

printf '%s' "$REGISTRY_DEPLOY_PASSWORD" \
  | ssh "$remote" "sudo docker login '$CI_REGISTRY' --username '$REGISTRY_DEPLOY_USER' --password-stdin"

ssh "$remote" "
  set -e
  cd '$DEPLOY_DIR'
  compose='sudo docker compose --env-file stack.env --env-file backend.env -f docker-compose.yml'
  \$compose pull api worker admin client gateway certbot mysql
  \$compose up -d mysql worker api admin client
  if \$compose --profile tls run --rm certbot certificates 2>/dev/null | grep -Fq 'Certificate Name: $TLS_CERT_NAME'; then
    \$compose up -d gateway
    \$compose ps
  else
    echo 'TLS certificate is not initialized. Run: $DEPLOY_DIR/tls.sh issue' >&2
    exit 2
  fi
"
