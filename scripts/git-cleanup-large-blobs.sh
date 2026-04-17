#!/usr/bin/env bash
# Remove generated / build artifacts from *all* Git history so clones and pushes stay small.
# Requires: https://github.com/newren/git-filter-repo (brew install git-filter-repo)
#
# After running:
#   git gc --prune=now --aggressive
#   git push --force-with-lease origin main   # rewrites remote; coordinate with collaborators
#
set -euo pipefail

cd "$(dirname "$0")/.."

if ! command -v git-filter-repo &>/dev/null; then
    echo "Install git-filter-repo first, e.g.: brew install git-filter-repo"
    exit 1
fi

git filter-repo --force \
    --invert-paths \
    --path public/laravel-bundle.json \
    --path dist-wasm \
    --path src-tauri/target

echo "Done. Run: git gc --prune=now --aggressive"
