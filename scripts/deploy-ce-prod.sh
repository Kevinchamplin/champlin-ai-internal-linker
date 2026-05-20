#!/usr/bin/env bash
# Deploy Champlin AI Internal Linker to crm.champlinenterprises.com.
#
# Builds a production tree (composer install --no-dev, no tests, no Tailwind
# source, no scripts/), rsyncs to ce-prod, fixes ownership. Idempotent.
#
# Usage:  ./scripts/deploy-ce-prod.sh

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="champlin-ai-internal-linker"
SSH_ALIAS="ce-prod"
REMOTE_WP="/var/www/vhosts/champlinenterprises.com/crm.ChamplinEnterprises.com"
REMOTE_PLUGIN_DIR="$REMOTE_WP/wp-content/plugins/$SLUG"

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

echo "==> Building production assets locally"
(cd "$REPO_ROOT" && npm run build --silent)

echo "==> Staging in $STAGE/$SLUG"
mkdir -p "$STAGE/$SLUG"
rsync -a \
  --exclude '.git' \
  --exclude '.github' \
  --exclude 'node_modules/' \
  --exclude 'tests/' \
  --exclude 'scripts/' \
  --exclude 'assets/editor/' \
  --exclude 'assets/admin/' \
  --exclude '.editorconfig' \
  --exclude '.gitignore' \
  --exclude '.phpunit.cache/' \
  --exclude '.phpcs.cache' \
  --exclude '.DS_Store' \
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

echo "==> Installing production composer dependencies (autoloader only)"
(cd "$STAGE/$SLUG" && composer install --no-dev --optimize-autoloader --quiet --no-interaction)

echo "==> Rsync to $SSH_ALIAS:$REMOTE_PLUGIN_DIR"
rsync -avz --delete \
  -e "ssh" \
  "$STAGE/$SLUG/" \
  "$SSH_ALIAS:$REMOTE_PLUGIN_DIR/"

echo "==> Fixing ownership on remote"
ssh "$SSH_ALIAS" "sudo chown -R champline:psacln $REMOTE_PLUGIN_DIR"

echo ""
echo "=== DEPLOY COMPLETE ==="
echo "Plugin at: $REMOTE_PLUGIN_DIR"
echo ""
echo "Activate with:"
echo "  ssh $SSH_ALIAS \"/opt/plesk/php/8.3/bin/php /usr/local/bin/wp plugin activate $SLUG --path=$REMOTE_WP\""
