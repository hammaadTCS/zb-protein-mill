#!/usr/bin/env bash
# Run on YOUR computer: download a release built by CI, check it, copy it to a server, deploy it.
#   bash infra/local/ship-release.sh v1.2.0 zbadmin@staging.example.com
# Needs: gh (logged in to GitHub), ssh access as your admin user. Staging first, always (spec 18.3).
set -euo pipefail
TAG="${1:?usage: ship-release.sh <tag> <admin@server>}"
HOST="${2:?usage: ship-release.sh <tag> <admin@server>}"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
cd "$WORK"
gh release download "$TAG" --pattern "zb-mill-$TAG.tar.gz" --pattern SHA256SUMS
grep " zb-mill-$TAG.tar.gz\$" SHA256SUMS | sha256sum --check -
SHA="$(grep " zb-mill-$TAG.tar.gz\$" SHA256SUMS | cut -d' ' -f1)"
echo "Checksum OK: $SHA"
read -r -p "Deploy $TAG to $HOST now? [y/N] " ok
[ "$ok" = y ] || { echo "Stopped."; exit 0; }
scp "zb-mill-$TAG.tar.gz" "$HOST:/tmp/"
ssh -t "$HOST" "sudo zb-deploy-release /tmp/zb-mill-$TAG.tar.gz --sha256 $SHA && rm -f /tmp/zb-mill-$TAG.tar.gz"
