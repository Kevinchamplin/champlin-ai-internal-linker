#!/usr/bin/env bash
# Build the GitHub-release variant of Champlin AI Internal Linker.
#
# Produces dist/champlin-ai-internal-linker-<version>.zip, ready to attach
# to a `gh release create` call. PUC stays IN (GitHub installs auto-update
# via the plugin-update-checker library). For the WP.org-stripped variant,
# use scripts/build-wp-org.sh instead.
#
# Output is a self-contained zip with:
#   - All plugin PHP + views + dist assets (CSS/JS already built)
#   - vendor/ (production composer deps, --no-dev)
#   - vendor/plugin-update-checker/ for self-hosted updates
# Excluded:
#   - .git*, .DS_Store, .editorconfig
#   - node_modules/, tests/, scripts/
#   - assets/editor/, assets/admin/ source (only assets/dist/ ships)
#   - README/CHANGELOG/etc. metadata (not needed at install time)

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="champlin-ai-internal-linker"
VERSION="$(grep -E "^\s*\*\s*Version:" "$REPO_ROOT/$SLUG.php" | awk '{print $NF}')"
if [[ -z "$VERSION" ]]; then
    echo "FAIL: couldn't read Version from plugin header" >&2
    exit 1
fi

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

OUT_DIR="$REPO_ROOT/dist"
mkdir -p "$OUT_DIR"
OUT_ZIP="$OUT_DIR/$SLUG-$VERSION.zip"
rm -f "$OUT_ZIP"

echo "==> Building production assets"
(cd "$REPO_ROOT" && npm run build --silent)

echo "==> Staging in $STAGE/$SLUG"
mkdir -p "$STAGE/$SLUG"
rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude '.gitignore' \
  --exclude '.editorconfig' \
  --exclude '.DS_Store' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpcs.cache' \
  --exclude 'node_modules/' \
  --exclude 'tests/' \
  --exclude 'scripts/' \
  --exclude 'assets/editor/' \
  --exclude 'assets/admin/' \
  --exclude 'phpcs.xml' \
  --exclude 'phpunit.xml.dist' \
  --exclude 'tailwind.config.js' \
  --exclude 'package.json' \
  --exclude 'package-lock.json' \
  --exclude 'composer.lock' \
  --exclude 'CONTRIBUTING.md' \
  --exclude 'SECURITY.md' \
  --exclude 'README.md' \
  --exclude 'CHANGELOG.md' \
  --exclude 'vendor/' \
  "$REPO_ROOT/" "$STAGE/$SLUG/"

echo "==> Installing production composer dependencies"
(cd "$STAGE/$SLUG" && composer install --no-dev --optimize-autoloader --quiet --no-interaction)

echo "==> Bundling PUC for self-hosted auto-updates"
cp -R "$REPO_ROOT/vendor/plugin-update-checker" "$STAGE/$SLUG/vendor/"

echo "==> PHP syntax check"
ERR=0
while IFS= read -r f; do
  if ! php -l "$f" > /dev/null 2>&1; then
    php -l "$f"
    ERR=1
  fi
done < <(find "$STAGE/$SLUG" -name "*.php" -not -path "*/vendor/composer/*")
if [ $ERR -ne 0 ]; then
    echo "FAIL: PHP syntax errors. Aborting build."
    exit 1
fi
echo "  all PHP files clean"

echo "==> Zipping"
(cd "$STAGE" && COPYFILE_DISABLE=1 zip -qr "$OUT_ZIP" "$SLUG")

echo ""
echo "=== BUILD COMPLETE ==="
ls -lh "$OUT_ZIP"
echo ""
echo "Contents preview:"
unzip -l "$OUT_ZIP" | head -25
echo "..."
echo ""
echo "Next steps:"
echo "  git tag v$VERSION && git push origin v$VERSION"
echo "  gh release create v$VERSION \"$OUT_ZIP\" --title \"v$VERSION\" --notes-file <notes.md>"
