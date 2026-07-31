#!/usr/bin/env bash
# Build a distributable plugin zip for Checkout Script Monitor.
#
# Produces dist/<slug>.zip with the plugin folder as the archive's top-level
# directory (what WordPress expects at Plugins -> Add New -> Upload Plugin),
# excluding dev-only files. Slug is taken from the repo folder name, so this
# keeps working if the folder is ever renamed.
#
# Usage (from anywhere):
#   ./bin/build-zip.sh
set -euo pipefail
cd "$(dirname "$0")/.."          # plugin repo root

SLUG="$(basename "$(pwd)")"
OUT_DIR="$(pwd)/dist"
OUT="$OUT_DIR/$SLUG.zip"
mkdir -p "$OUT_DIR"
rm -f "$OUT"

# Zip the whole plugin folder from its parent so the archive's top level is the
# slug. Exclude VCS, dev tooling, and the build output itself.
( cd .. && zip -r -q "$OUT" "$SLUG" \
    -x "$SLUG/.git/*" "$SLUG/.github/*" "$SLUG/node_modules/*" "$SLUG/vendor/*" \
       "$SLUG/bin/*" "$SLUG/tests/*" "$SLUG/dist/*" "$SLUG/.wordpress-org/*" "$SLUG/.gstack/*" \
       "$SLUG/.wp-env.json" "$SLUG/.wp-env.override.json" \
       "$SLUG/.gitignore" "$SLUG/.distignore" "$SLUG/.DS_Store" "$SLUG/*.zip" )

echo "Built: dist/$SLUG.zip"
echo "Upload it: wp-admin -> Plugins -> Add New -> Upload Plugin -> choose dist/$SLUG.zip -> Activate"
