#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
if [[ "${1:-}" != --apply || $# != 5 ]]; then
    echo 'Usage: scripts/deploy.sh --apply HOST USER /absolute/app-root https://host/health' >&2
    echo 'Run scripts/package.sh first for a local artifact. Deployment requires separate authorization.' >&2
    exit 2
fi
host=$2
remote_user=$3
app_root=$4
health_url=$5
[[ "$host" =~ ^[a-zA-Z0-9.-]+$ && "$remote_user" =~ ^[a-zA-Z0-9_-]+$ && "$app_root" =~ ^/[a-zA-Z0-9/_-]+$ && "$health_url" =~ ^https://[a-zA-Z0-9.-]+/health$ ]] || exit 2
[[ "$health_url" == "https://$host/health" ]] || exit 2
archive=$(mktemp /tmp/adem-release.XXXXXX)
# mktemp reserves the name; package refuses overwriting existing artifacts.
rm "$archive"
trap 'rm -f "$archive"' EXIT
bash scripts/package.sh "$archive"
release="$(date -u +%Y%m%dT%H%M%SZ)-$(git rev-parse --short HEAD)"
scp "$archive" "$remote_user@$host:/tmp/adem-$release.tar.gz"
ssh "$remote_user@$host" bash -se -- "$app_root" "$release" <<'REMOTE'
set -euo pipefail
app_root=$1
release=$2
release_dir="$app_root/releases/$release"
[[ -f "$app_root/shared/.env.local" ]] || { echo 'Missing server-managed production environment.' >&2; exit 1; }
mkdir -p "$release_dir" "$app_root/shared/media" "$app_root/shared/log"
tar -xzf "/tmp/adem-$release.tar.gz" -C "$release_dir"
cd "$release_dir"
ln -s "$app_root/shared/.env.local" .env.local
# Keep uploads across releases. Existing packaged media are only copied if absent.
if [[ -d public/img/ruelle ]]; then cp -Rn public/img/ruelle/. "$app_root/shared/media/"; fi
mv public/img/ruelle public/img/ruelle-packaged
ln -s "$app_root/shared/media" public/img/ruelle
mkdir -p var/cache
ln -s "$app_root/shared/log" var/log
export APP_ENV=prod APP_DEBUG=0
composer install --no-dev --prefer-dist --classmap-authoritative --no-interaction --no-scripts
php bin/console lint:container --env=prod
php bin/console doctrine:schema:validate --env=prod
# Schema changes are reviewed/applied separately before switch; never run destructive migration blindly.
php bin/console cache:warmup --env=prod
php bin/console asset-map:compile --env=prod
chmod -R u=rwX,g=rX,o= "$release_dir"
chmod -R ug=rwX,o= var/cache "$app_root/shared/log" "$app_root/shared/media"
ln -s "$release_dir" "$app_root/current.next"
mv -Tf "$app_root/current.next" "$app_root/current"
rm "/tmp/adem-$release.tar.gz"
REMOTE
curl --fail --silent --show-error --max-time 15 "$health_url"
echo 'Release switched and readiness HTTP check passed. Verify business smoke tests and monitoring.'
