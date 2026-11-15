#!/usr/bin/env bash
set -euo pipefail

version=0.169.0
destination=${1:-/usr/local/bin}

[[ $(uname -s) == Linux ]] || { echo 'This installer requires Linux.' >&2; exit 1; }

case $(uname -m) in
    x86_64)
        target=x86_64-unknown-linux-musl
        checksum=9af4d751fce183faedea629437d5252dbf458eeb04130cf4f97be844621ea985
        ;;
    aarch64|arm64)
        target=aarch64-unknown-linux-musl
        checksum=97b97d87eea075c5cb7709c7f6156a451622a7667953ecda40e0276637196f3f
        ;;
    *) echo 'Unsupported CPU architecture.' >&2; exit 1 ;;
esac

temporary=$(mktemp -d)
trap 'rm -rf "$temporary"' EXIT

curl --fail --silent --show-error --location \
    "https://github.com/openai/codex/releases/download/rust-v${version}/codex-${target}.tar.gz" \
    --output "$temporary/codex.tar.gz"
printf '%s  %s\n' "$checksum" "$temporary/codex.tar.gz" | sha256sum --check --status
tar -xzf "$temporary/codex.tar.gz" -C "$temporary"
install -d "$destination"
install -m 755 "$temporary/codex-${target}" "$destination/codex"
"$destination/codex" --version
