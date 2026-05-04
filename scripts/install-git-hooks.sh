#!/usr/bin/env bash

set -euo pipefail

# Configure git to use the .githooks directory
git config core.hooksPath .githooks

echo "Git hooks configured to use the .githooks directory."
