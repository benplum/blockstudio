#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PLUGIN_SLUG="$(basename "$ROOT_DIR")"
ARTIFACTS_DIR="$ROOT_DIR/artifacts"
STAGE_ROOT="$ARTIFACTS_DIR/stage"
STAGE_DIR="$STAGE_ROOT/$PLUGIN_SLUG"
ZIP_PATH="$ARTIFACTS_DIR/${PLUGIN_SLUG}.zip"

cd "$ROOT_DIR"

echo "Creating distribution for: $PLUGIN_SLUG"

if ! command -v composer >/dev/null 2>&1; then
  echo "Error: composer is required but was not found in PATH."
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "Error: npm is required but was not found in PATH."
  exit 1
fi

echo "Installing PHP dependencies (no-dev)..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Installing Node dependencies..."
npm ci

echo "Building admin assets..."
npm run build

echo "Preparing artifact directories..."
rm -rf "$STAGE_ROOT" "$ZIP_PATH"
mkdir -p "$STAGE_DIR"

echo "Staging distribution files..."
rsync -a \
  --exclude='.git' \
  --exclude='node_modules' \
  --exclude='/tests' \
  --exclude='/docs' \
  --exclude='/evals' \
  --exclude='/src' \
  --exclude='.github' \
  --exclude='_reference' \
  --exclude='.env*' \
  --exclude='*.config.*' \
  --exclude='tsconfig*' \
  --exclude='package.json' \
  --exclude='package-lock.json' \
  --exclude='.npmrc' \
  --exclude='.gitignore' \
  --exclude='.prettierrc*' \
  --exclude='eslint*' \
  --exclude='phpcs.xml*' \
  --exclude='scoper.inc.php' \
  --exclude='build.sh' \
  --exclude='CLAUDE.md' \
  --exclude='.claude' \
  --exclude='/build' \
  --exclude='/packages' \
  ./ "$STAGE_DIR/"

echo "Creating ZIP archive..."
if command -v ditto >/dev/null 2>&1; then
  (
    cd "$STAGE_ROOT"
    ditto -c -k --sequesterRsrc --keepParent "$PLUGIN_SLUG" "$ZIP_PATH"
  )
else
  (
    cd "$STAGE_ROOT"
    zip -qr "$ZIP_PATH" "$PLUGIN_SLUG"
  )
fi

echo "Done: $ZIP_PATH"
