#!/usr/bin/env bash
# Minimal seed for the Checkout Script Monitor plugin-dev environment.
#
# Gives you just enough store to exercise the plugin: two products, the
# WooCommerce pages, and the built-in Cash-on-Delivery gateway (no payment
# plugin needed). Deliberately light — richer catalogs live in the CyberShield
# Woo lab (cybershieldstudio/woo-lab). Idempotent: safe to run repeatedly.
#
# Usage:
#   npx @wordpress/env start
#   ./bin/seed.sh
set -euo pipefail
cd "$(dirname "$0")/.."   # plugin root (where .wp-env.json lives)

echo "Seeding plugin-dev store (minimal)..."

npx @wordpress/env run cli bash -c '
set -e
wp rewrite structure "/%postname%/" >/dev/null
wp wc --user=admin tool run install_pages >/dev/null 2>&1 || true
wp wc payment_gateway update cod --enabled=true --user=admin >/dev/null

existing=$(wp wc product list --user=admin --field=sku --per_page=100 2>/dev/null || true)
add() { # name price sku
  case " $existing " in *" $3 "*) return;; esac
  wp wc product create --name="$1" --type=simple --regular_price="$2" --sku="$3" \
    --status=publish --manage_stock=false --user=admin --porcelain >/dev/null
  echo "  + $1"
}
add "Sample Product A" 19 CSG-DEV-001
add "Sample Product B" 29 CSG-DEV-002
'

echo "Done. Store: http://localhost:8888  ·  Admin: /wp-admin (admin / password)"
