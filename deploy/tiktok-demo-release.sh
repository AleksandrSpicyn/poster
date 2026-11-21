#!/usr/bin/env bash
# Installed root-owned as the demo-only forced SSH command.
set -euo pipefail
umask 027
[[ ${SSH_ORIGINAL_COMMAND:-} =~ ^release\ ([0-9a-f]{40})$ ]] || exit 64
sha=${BASH_REMATCH[1]}
base=/srv/poster-tiktok-demo
exec 9>"$base/releases/.lock"
flock -w 60 9
tmp=$(mktemp -d "$base/releases/.incoming.XXXXXXXX")
trap 'rm -rf -- "$tmp"' EXIT
tar --extract --gzip --directory "$tmp" --no-same-owner --no-same-permissions
test -f "$tmp/routes/tiktok-demo.php" && test -f "$tmp/vendor/autoload.php"
test ! -e "$tmp/.env"
ln -s "$base/shared/.env" "$tmp/.env"
# No live data is imported. The archive contains only storage skeletons.
mv "$tmp/storage" "$tmp/storage-skeleton"
ln -s "$base/shared/storage" "$tmp/storage"
(cd "$tmp" && php8.4 artisan package:discover --no-interaction >/dev/null)
release="$base/releases/$sha-$(date +%s)"
mv "$tmp" "$release"
ln -s "$release" "$base/releases/.next"
mv -Tf "$base/releases/.next" "$base/current"
printf 'Activated demo %s\n' "$sha"
