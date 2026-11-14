#!/usr/bin/env bash
set -euo pipefail

package=$(mktemp -d)
trap 'rm -rf "$package"' EXIT
chmod 755 "$package"

git archive "$GITHUB_SHA" | tar -x -C "$package"
cp -a vendor "$package/"
cp -a public/build public/vendor "$package/public/"
cp -a resources/views/scribe "$package/resources/views/"
tar -czf app.tar.gz -C "$package" .
