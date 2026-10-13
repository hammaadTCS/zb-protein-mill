#!/bin/bash
# CLOUD SESSIONS ONLY. Puts every PHP package zip that composer.lock names into Composer's download
# cache, built from git, so that `composer install` never has to download a zip from GitHub.
#
# Why: in Claude Code cloud sessions all GitHub traffic goes through a GitHub proxy that refuses
# GitHub API requests (HTTP 403, "GitHub access to this repository is not enabled for this
# session") for every repository not attached to the session. Composer downloads each package as
# https://api.github.com/repos/<owner>/<repo>/zipball/<commit>, so every download fails; the proxy
# does allow `git fetch` of public repositories. (docs/stack-baseline.md, "Cloud sessions".)
#
# How: for each package whose download is such a GitHub zip, fetch exactly that commit with git and
# build the zip with `git archive`, which is how GitHub builds its zips (same files, same
# export-ignore rules). The zip is stored where Composer looks for it:
#   <cache-files-dir>/<package name>/<sha1 of the download URL>.zip
# Composer uses a cached zip without downloading when composer.lock has no checksum for it (true
# for GitHub zips). composer.lock is never changed. Packages with a checksum, or not hosted as
# GitHub zips, are skipped and Composer downloads them itself.
#
#   bash scripts/cloud-composer-cache.sh [directory containing composer.lock]   (default: .)
#
# Used by scripts/cloud-session-start.sh and card T0.1. Never needed on the laptop or in CI: they
# download from GitHub directly. Public packages and test machines only; no credentials involved.
set -uo pipefail

DIR="${1:-.}"
LOCK="$DIR/composer.lock"
if [ ! -f "$LOCK" ]; then
  echo "cloud-composer-cache: no composer.lock in $DIR"
  exit 1
fi
export COMPOSER_ALLOW_SUPERUSER=1
CACHE="$(composer config --global cache-files-dir 2>/dev/null)"
if [ -z "$CACHE" ]; then
  echo "cloud-composer-cache: cannot find Composer's cache folder"
  exit 1
fi
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# One line per package: name, owner, repo, commit, cache file. PHP reads the lock (JSON).
php -r '
$lock = json_decode(file_get_contents($argv[1]), true);
foreach (array_merge($lock["packages"] ?? [], $lock["packages-dev"] ?? []) as $p) {
    $d = $p["dist"] ?? [];
    $url = $d["url"] ?? "";
    if (($d["type"] ?? "") !== "zip" || ($d["shasum"] ?? "") !== ""
        || !preg_match("#^https://api\.github\.com/repos/([^/]+)/([^/]+)/zipball/([0-9a-f]{40})$#", $url, $m)) {
        fwrite(STDERR, "cloud-composer-cache: skipped " . $p["name"] . " (not a GitHub zip without checksum)\n");
        continue;
    }
    echo $p["name"], " ", $m[1], " ", $m[2], " ", $m[3], " ", $argv[2], "/", $p["name"], "/", sha1($url), ".zip\n";
}' "$LOCK" "$CACHE" > "$WORK/list" || { echo "cloud-composer-cache: cannot read $LOCK"; exit 1; }

build() { # build <name> <owner> <repo> <commit> <cache file>
  local name="$1" owner="$2" repo="$3" ref="$4" target="$5"
  [ -s "$target" ] && return 0
  local git_dir="$WORK/${name//\//__}"
  for attempt in 1 2; do
    rm -rf "$git_dir"
    if git init -q "$git_dir" \
      && git -C "$git_dir" fetch -q --depth 1 "https://github.com/$owner/$repo.git" "$ref" \
      && mkdir -p "$(dirname "$target")" \
      && git -C "$git_dir" archive --format=zip --prefix="$owner-$repo-${ref:0:7}/" -o "$target.part" FETCH_HEAD \
      && mv "$target.part" "$target"; then
      rm -rf "$git_dir"
      return 0
    fi
    rm -f "$target.part"
    [ "$attempt" = 1 ] && sleep 3
  done
  rm -rf "$git_dir"
  echo "cloud-composer-cache: FAILED $name ($owner/$repo $ref)"
  return 1
}
export -f build
export WORK

TOTAL=$(wc -l < "$WORK/list")
# Four at a time: fast enough, and gentle on the session's proxy.
if xargs -P 4 -L 1 bash -c 'build "$@"' _ < "$WORK/list"; then
  echo "cloud-composer-cache: $TOTAL package zips ready in Composer's cache."
  exit 0
fi
echo "cloud-composer-cache: some packages failed (listed above); composer install will try to download them and may fail."
exit 1
