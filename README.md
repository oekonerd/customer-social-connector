# Customer Social Connector

WordPress plugin for on-prem customer deployments with server-side scheduling and REST ingestion. No central posting server.

## Features

- REST API namespace: `/wp-json/csc/v1`
- Auth for protected endpoints via WordPress Application Passwords (HTTP Basic Auth)
- Scheduled queue worker over WP-Cron (`csc_cron_tick` every minute)
- DB-backed post queue and token storage
- Meta publish layer currently implemented as stub

## Repository Structure

- `plugin/` WordPress plugin code
- `scripts/dev-setup.sh` local setup without Docker
- `scripts/dev-run.sh` local `wp server` launcher

## Local Dev Setup (No Docker)

Requirements:

- `php`
- `wp` (WP-CLI)
- `mysql` or `mariadb` CLI
- local MySQL/MariaDB server running and reachable

Run:

```bash
./scripts/dev-setup.sh
./scripts/dev-run.sh
```

Default local dev credentials are intentionally limited to local usage in these scripts (`admin/admin12345!`, `wp/wp`).

## Production Installation (Customer Server, No Docker)

1. Download ZIP from GitHub Releases or clone this repository.
2. Copy folder `customer-social-connector` to:
   `/wp-content/plugins/customer-social-connector`
3. Activate plugin in WP Admin.

## Application Password Setup

1. Open user profile in WP Admin.
2. Go to **Application Passwords**.
3. Create a new application password.
4. Use `username:application_password` with HTTP Basic Auth.

## REST Test Examples

```bash
curl -u "admin:APPLICATION_PASSWORD" https://kunden-domain.tld/wp-json/csc/v1/health

curl -u "admin:APPLICATION_PASSWORD" -X POST https://kunden-domain.tld/wp-json/csc/v1/posts \
  -H "Content-Type: application/json" \
  -d '{"platform":"fb","payload":{"message":"Test"},"publish_at":"2026-02-10T12:00:00Z"}'
```

## WP-Cron Reliability Note

WP-Cron depends on traffic. For reliable execution, configure a real server cron.

Linux example:

```bash
*/1 * * * * php /pfad/zu/wp/wp-cron.php > /dev/null 2>&1
```

Optional (often better):

```bash
wp cron event run --due-now --path=/pfad/zu/wp
```

## Worker Behavior

- Picks up due posts (`scheduled`, `publish_at <= now UTC`) in batches of 10
- Moves to `processing`
- Calls `CSC_Meta::publish(...)`
- On success: `sent`
- On error: `failed`, increments `attempts`, stores `last_error`
- Failed posts can be reset to `scheduled` using retry endpoint
