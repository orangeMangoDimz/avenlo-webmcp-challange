# Docker production operations

## Services

Only `gateway` publishes host ports. `api`, `worker`, `admin`, `client`, and
`mysql` communicate over Compose networks. MySQL data, backend-generated files,
and certificates use separate named volumes.

The server needs Docker Engine with Compose, ports 80/443, and DNS for the admin,
client, and API domains. Application dependencies are contained in registry
images.

## Required GitLab variables

Configure these as protected variables. Secrets and environment files must also
be masked where GitLab supports masking.

- `SSH_PRIVATE_KEY`, `SERVER_HOST`, `SERVER_USER`
- `SERVER_HOST_STAGING`, `SERVER_USER_STAGING`
- `REGISTRY_DEPLOY_USER`, `REGISTRY_DEPLOY_PASSWORD`
- `PRODUCTION_BACKEND_ENV_FILE`, `STAGING_BACKEND_ENV_FILE`
- `ADMIN_ENV_PRODUCTION`, `CLIENT_ENV_PRODUCTION`
- `ADMIN_ENV_STAGING`, `CLIENT_ENV_STAGING`
- `CERTBOT_EMAIL`

Backend files use the keys documented in `.env.example`. Frontend files must
define `VITE_API_BASE_URL`; the optional `VITE_CLIENT_API_BASE_URL` and
`VITE_CLIENT_BASE_URL` values are passed into their respective image builds.

## First production cutover

1. Deploy and exercise the same images on staging.
2. Run the production pipeline. The first deploy installs the internal stack and
   stops before the gateway if no certificate exists.
3. Enter maintenance mode on the old deployment. Stop its Nginx, PHP-FPM, and
   Swoole services so no new writes can occur.
4. Create a final logical backup with `scripts/prod/backup.sh` and verify the
   reported dump path is non-empty.
5. Copy the dump to the new server stack and restore it:

   ```bash
   sudo STACK_DIR=/opt/utrada-crm \
     /opt/utrada-crm/restore.sh --file /path/to/final.sql
   ```

   The restore rejects truncated dumps, recreates the database, strips legacy
   definers, imports objects as the configured application user, and reports
   table/view/routine/trigger/event counts.

6. Copy persistent backend files while the old application remains stopped:

   ```bash
   cd /opt/utrada-crm
   compose="sudo docker compose --env-file stack.env --env-file backend.env -f docker-compose.yml"
   sudo tar -C /var/www/utrada/back-end -cf - uploads storage logs \
     | $compose exec -T --user root api tar -C /var/www/html -xf -
   $compose exec -T --user root api chown -R www-data:www-data \
     /var/www/html/uploads /var/www/html/storage /var/www/html/logs
   ```

7. Stop the old host Nginx, confirm ports 80/443 are free, and issue the SAN
   certificate:

   ```bash
   sudo STACK_DIR=/opt/utrada-crm /opt/utrada-crm/tls.sh issue
   ```

8. Verify both SPAs, API authentication, callbacks, uploads/downloads, database
   object counts, and at least one real Swoole task before ending maintenance.
9. Keep the old application and database container stopped but intact until the
   retention window expires.

## Routine operations

```bash
cd /opt/utrada-crm
sudo docker compose --env-file stack.env --env-file backend.env -f docker-compose.yml ps
sudo docker compose --env-file stack.env --env-file backend.env -f docker-compose.yml logs -f
sudo STACK_DIR=/opt/utrada-crm /opt/utrada-crm/tls.sh renew
sudo STACK_DIR=/opt/utrada-crm /opt/utrada-crm/backup.sh
```

GitLab's scheduled `renew_tls` job runs the renewal command. Set
`TLS_RENEW_ENVIRONMENT=production` on the schedule.

## Rollback

During the cutover validation window, stop the new stack before reopening the
old one:

```bash
cd /opt/utrada-crm
sudo docker compose --env-file stack.env --env-file backend.env -f docker-compose.yml down
```

Then restart the untouched legacy MySQL container and the old PHP-FPM, Swoole,
and Nginx services. Do not allow writes to both databases: once normal traffic
has written to the new database, rollback requires a new data reconciliation
decision rather than this simple restart procedure.
