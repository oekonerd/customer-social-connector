#!/usr/bin/env bash
set -euo pipefail

WP_DIR=".wp-dev"
WP_URL="http://127.0.0.1:8080"
DB_NAME="wp_csc_dev"
DB_USER="wp"
DB_PASS="wp"
DB_HOST="127.0.0.1"
ADMIN_USER="admin"
ADMIN_PASS="admin12345!"
ADMIN_EMAIL="admin@example.local"

require_cmd() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Missing dependency: $1"
    exit 1
  fi
}

require_cmd php
require_cmd wp

MYSQL_BIN=""
if command -v mysql >/dev/null 2>&1; then
  MYSQL_BIN="mysql"
elif command -v mariadb >/dev/null 2>&1; then
  MYSQL_BIN="mariadb"
else
  echo "Missing dependency: mysql or mariadb client"
  exit 1
fi

if [ ! -d "$WP_DIR" ]; then
  mkdir -p "$WP_DIR"
fi

if [ ! -f "$WP_DIR/wp-load.php" ]; then
  wp core download --path="$WP_DIR"
fi

echo "Ensuring database exists: $DB_NAME"
"$MYSQL_BIN" -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"

if [ ! -f "$WP_DIR/wp-config.php" ]; then
  wp config create \
    --path="$WP_DIR" \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASS" \
    --dbhost="$DB_HOST" \
    --skip-check
fi

if ! wp core is-installed --path="$WP_DIR" >/dev/null 2>&1; then
  wp core install \
    --path="$WP_DIR" \
    --url="$WP_URL" \
    --title="CSC Dev" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL"
fi

PLUGIN_TARGET="$WP_DIR/wp-content/plugins/customer-social-connector"
rm -rf "$PLUGIN_TARGET"
ln -s "$(pwd)/plugin" "$PLUGIN_TARGET"

wp plugin activate customer-social-connector --path="$WP_DIR"

echo "Setup complete. Start dev server with:"
echo "  ./scripts/dev-run.sh"
echo "WP Admin: $WP_URL/wp-admin (admin / $ADMIN_PASS)"
