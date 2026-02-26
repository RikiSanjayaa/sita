#!/usr/bin/env bash

set -euo pipefail

mkdir -p .git/hooks
cp .githooks/pre-push .git/hooks/pre-push
chmod +x .git/hooks/pre-push

echo "Installed pre-push hook: .git/hooks/pre-push"
