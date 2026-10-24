#!/usr/bin/env bash
# Upload screenshots for a pull request or issue and print Markdown to paste
# into its body or a comment.
#
# usage: scripts/pr-screenshots.sh <key> <image>...
#   key    folder name for this change, e.g. "pr-123" or "issue-45-dashboard"
#   image  PNG/JPEG/WebP files (light and dark, 1280px and 390px)
#
# Images go to the orphan branch "pr-assets" (never merged into main) under
# <key>/, and are linked through raw.githubusercontent.com. The repository is
# public, so only capture seeded demo data, never real student data.
set -euo pipefail

if [ "$#" -lt 2 ]; then
    echo "usage: $0 <key> <image>..." >&2
    exit 1
fi

key="$1"
shift
branch=pr-assets
repo=$(gh repo view --json nameWithOwner -q .nameWithOwner)
tmp=$(mktemp -d)
trap 'git worktree remove --force "$tmp" >/dev/null 2>&1 || rm -rf "$tmp"' EXIT

if git ls-remote --exit-code --heads origin "$branch" >/dev/null 2>&1; then
    git fetch -q origin "$branch"
    git worktree add -q --detach "$tmp" "origin/$branch"
else
    git worktree add -q --detach "$tmp"
    (cd "$tmp" && git checkout -q --orphan "$branch-new" && git rm -rqf .)
fi

mkdir -p "$tmp/$key"
for image in "$@"; do
    cp "$image" "$tmp/$key/"
done

(
    cd "$tmp"
    git add "$key"
    git commit -qm "assets: $key"
    git push -q origin "HEAD:$branch" 2>&1 | sed '/^remote:/d' >&2
)

for image in "$@"; do
    name=$(basename "$image")
    printf '![%s](https://raw.githubusercontent.com/%s/%s/%s/%s)\n' \
        "${name%.*}" "$repo" "$branch" "$key" "$name"
done
