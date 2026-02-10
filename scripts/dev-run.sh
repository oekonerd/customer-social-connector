#!/usr/bin/env bash
set -euo pipefail

WP_DIR=".wp-dev"

if [ ! -d "$WP_DIR" ]; then
  echo "Missing $WP_DIR. Run ./scripts/dev-setup.sh first."
  exit 1
fi

wp server --path="$WP_DIR" --host=127.0.0.1 --port=8080
