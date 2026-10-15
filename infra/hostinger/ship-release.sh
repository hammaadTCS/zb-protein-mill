#!/usr/bin/env bash
# Run on YOUR computer: download a release built by CI, check it, copy it to the shared host,
# then deploy it over SSH.
#   bash infra/hostinger/ship-release.sh v1.2.0 staging.example.com
# Needs: gh (logged in to GitHub) and SSH access as the hosting account's own user (no sudo).
# Staging first, always (spec 18.3). The host side unpacks a new releases/<version>/ folder and
# switches the current link (infra/hostinger/zb-deploy-release).
set -euo pipefail
TAG="${1:?usage: ship-release.sh <tag> <host>}"
HOST="${2:?usage: ship-release.sh <tag> <host>}"
APP_BASE="${APP_BASE:-accounts}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
cd "$WORK"
gh release download "$TAG" --pattern "zb-mill-$TAG.tar.gz" --pattern SHA256SUMS
grep " zb-mill-$TAG.tar.gz\$" SHA256SUMS | sha256sum --check -
SHA="$(grep " zb-mill-$TAG.tar.gz\$" SHA256SUMS | cut -d' ' -f1)"
echo "Checksum OK: $SHA"
read -r -p "Deploy $TAG to $HOST now? [y/N] " ok
[ "$ok" = y ] || { echo "Stopped."; exit 0; }
scp "zb-mill-$TAG.tar.gz" "$HOST:zb-mill-$TAG.tar.gz"
# zb-deploy-release verifies the checksum again, unpacks the new release folder and switches the
# current link, rolling back by itself if the smoke test fails.
ssh -t "$HOST" "\"\$HOME/$APP_BASE/bin/zb-deploy-release\" \"\$HOME/zb-mill-$TAG.tar.gz\" --sha256 $SHA && rm -f \"\$HOME/zb-mill-$TAG.tar.gz\""
