#!/usr/bin/env bash
set -euo pipefail

[[ ${SSH_ORIGINAL_COMMAND:-} == deploy ]] || exit 64
cd /var/www/poster

incoming=$(mktemp -d)
trap '/usr/bin/php8.4 artisan up; rm -rf "$incoming"' EXIT
/usr/bin/php8.4 artisan down --retry=60

tar -xzf - -C "$incoming" --no-same-owner
rsync -a --delete --exclude=/.env --exclude=/storage --exclude=/public/storage "$incoming/" ./

/usr/bin/php8.4 artisan migrate --force --no-interaction
/usr/bin/php8.4 artisan storage:link --no-interaction
/usr/bin/php8.4 artisan scribe:generate --no-interaction
/usr/bin/php8.4 artisan optimize
/usr/bin/php8.4 artisan horizon:terminate
/usr/bin/php8.4 artisan reverb:restart
