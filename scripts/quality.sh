#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
export APP_ENV=test
php bin/console lint:yaml config translations .github/workflows compose.yaml --parse-tags
php bin/console lint:container
php bin/console doctrine:schema:validate
# Analysis uses dev container metadata but does not connect to the dev database.
APP_ENV=dev php bin/console cache:warmup --env=dev >/dev/null
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
vendor/bin/phpunit --fail-on-skipped --fail-on-deprecation --fail-on-phpunit-deprecation
composer validate --strict
composer audit --locked
for script in scripts/*.sh; do bash -n "$script"; done
