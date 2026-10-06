#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
output=${1:?Usage: scripts/package.sh /absolute/path/release.tar.gz}
[[ "$output" = /* && ! -e "$output" ]] || { echo 'Output must be a new absolute file.' >&2; exit 2; }
tar --exclude='config/secrets/*/*.decrypt.private.php' \
    --exclude='config/packages/local' --exclude='public/assets' \
    --exclude='public/bundles' --exclude='public/uploads' \
    --exclude='.DS_Store' --exclude='src/var' \
    -czf "$output" bin src config migrations public templates assets translations \
    composer.json composer.lock symfony.lock importmap.php .env
# .env contains versioned defaults only; all local/prod credentials stay outside the package.
