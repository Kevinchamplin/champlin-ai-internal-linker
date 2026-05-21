#!/usr/bin/env bash
# Build the WP.org-ready variant of Champlin AI Internal Linker.
#
# - Strips the GitHub-only auto-update library (PUC)
# - Removes hidden files + dev configs
# - Excludes node_modules, tests, build sources
# - Ensures the WP.org slug is used for Text Domain consistency
# - Runs `npm run build` and `composer install --no-dev` for production assets
# - Produces dist/<SLUG>.zip ready for SVN check-in or manual upload

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD_DIR="$(mktemp -d)"

# ============ EDIT THESE ============
SLUG="champlin-ai-internal-linker"
NAME="Champlin AI Internal Linker"
GITHUB_FILE_NAME="champlin-ai-internal-linker"
GITHUB_DISPLAY_NAME="Champlin AI Internal Linker"
GITHUB_SLUG="champlin-internal-linker"   # Text Domain stays the same — already non-trademark-prefixed
# ====================================

OUT_DIR="$REPO_ROOT/dist"
mkdir -p "$OUT_DIR"
rm -f "$OUT_DIR/$SLUG.zip"

echo "==> Installing production composer dependencies"
(cd "$REPO_ROOT" && composer install --no-dev --optimize-autoloader --quiet)

echo "==> Building JS + CSS bundles"
(cd "$REPO_ROOT" && npm run build --silent)

echo "==> Staging source in $BUILD_DIR/$SLUG"
mkdir -p "$BUILD_DIR/$SLUG"
rsync -a \
  --exclude '.git' \
  --exclude '.gitignore' \
  --exclude '.DS_Store' \
  --exclude '.editorconfig' \
  --exclude '.github' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpcs.cache' \
  --exclude '.plugin-check-errors.tsv' \
  --exclude '.plugin-check-output.txt' \
  --exclude 'node_modules/' \
  --exclude 'scripts/' \
  --exclude 'dist/' \
  --exclude 'tests/' \
  --exclude 'vendor/plugin-update-checker/' \
  --exclude 'README.md' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'SECURITY.md' \
  --exclude 'phpcs.xml' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'tailwind.config.js' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'composer.lock' \
  --exclude 'assets/editor/' \
  --exclude 'assets/admin/' \
  "$REPO_ROOT/" "$BUILD_DIR/$SLUG/"

echo "==> Stripping PUC integration from main plugin file"
python3 - <<PYEOF
import re
p = "$BUILD_DIR/$SLUG/$GITHUB_FILE_NAME.php"
src = open(p).read()
pattern = re.compile(
    r"/\*\*\s*\n \* Auto-update from GitHub releases.*?\n\}\s*\n",
    re.DOTALL
)
new = pattern.sub('', src, count=1)
open(p, "w").write(new)
print("  PUC block removed")
PYEOF

echo "==> Verifying no PUC references remain"
if grep -rl "plugin-update-checker\|PucFactory\|YahnisElsts" "$BUILD_DIR/$SLUG" 2>/dev/null; then
    echo "FAIL: PUC references still present in WP.org build"
    exit 1
fi
echo "  Clean"

echo "==> PHP syntax check"
ERR=0
while IFS= read -r f; do
  if ! php -l "$f" > /dev/null 2>&1; then
    php -l "$f"
    ERR=1
  fi
done < <(find "$BUILD_DIR/$SLUG" -name "*.php")
if [ $ERR -ne 0 ]; then
    echo "FAIL: PHP syntax errors"
    exit 1
fi
echo "  All PHP files clean"

echo "==> Zipping"
cd "$BUILD_DIR"
COPYFILE_DISABLE=1 zip -qr "$OUT_DIR/$SLUG.zip" "$SLUG"

echo ""
echo "=== BUILD COMPLETE ==="
ls -lh "$OUT_DIR/$SLUG.zip"
echo ""
echo "Sample contents:"
unzip -l "$OUT_DIR/$SLUG.zip" | head -20
echo ""
echo "Plugin header preview:"
unzip -p "$OUT_DIR/$SLUG.zip" "$SLUG/$GITHUB_FILE_NAME.php" | head -16

rm -rf "$BUILD_DIR"

echo ""
echo "Next steps:"
echo "  1. Install Plugin Check on a clean WP test site"
echo "       wp plugin install plugin-check --activate"
echo "       wp plugin check $SLUG"
echo "  2. Confirm: 'Checks complete. No errors found.'"
echo "  3. Submit at: https://wordpress.org/plugins/developers/add/"
