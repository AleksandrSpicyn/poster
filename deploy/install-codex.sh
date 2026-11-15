#!/usr/bin/env bash
set -euo pipefail

version=0.169.0
destination=${1:-/usr/local/bin}

[[ $(uname -s) == Linux ]] || { echo 'This installer requires Linux.' >&2; exit 1; }

case $(uname -m) in
    x86_64)
        target=x86_64-unknown-linux-musl
        checksum=3b8ed091149cc63a59046b9f094bcfa21cdb4dda15ede441ce369bcee4a71ba0
        ;;
    aarch64|arm64)
        target=aarch64-unknown-linux-musl
        checksum=c8d0b4ab2a97c595b70b10412d36d0d5464dcef1e1d3246b073d48017cdf5a4f
        ;;
    *) echo 'Unsupported CPU architecture.' >&2; exit 1 ;;
esac

temporary=$(mktemp -d)
trap 'rm -rf "$temporary"' EXIT

curl --fail --silent --show-error --location \
    "https://github.com/openai/codex/releases/download/rust-v${version}/codex-package-${target}.tar.gz" \
    --output "$temporary/codex.tar.gz"
printf '%s  %s\n' "$checksum" "$temporary/codex.tar.gz" | sha256sum --check --status
tar -xzf "$temporary/codex.tar.gz" -C "$temporary" bin
install -d "$destination"
install -m 755 "$temporary/bin/"* "$destination/"
"$destination/codex" --version
