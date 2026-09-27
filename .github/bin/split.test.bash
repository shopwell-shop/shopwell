#!/usr/bin/env bash

set -euo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/split.bash"

test_root="$(mktemp -d)"
export PLATFORM_DIR="${test_root}/platform"
remote_base="${test_root}/remotes"
repo="${PLATFORM_DIR}/repos/core"

mkdir -p "${repo}" "${remote_base}"
git init -q --bare "${remote_base}/core.git"
git -C "${repo}" init -q -b trunk
git -C "${repo}" config user.email "test@shopwell.com"
git -C "${repo}" config user.name "Shopwell Test"

printf '%s\n' "first release" > "${repo}/package.txt"
git -C "${repo}" add package.txt
git -C "${repo}" commit -q -m "Initial package"
git -C "${repo}" tag -m "Release v1.0.0" v1.0.0

push core "${remote_base}" v1.0.0
published_tree="$(git --git-dir="${remote_base}/core.git" rev-parse 'v1.0.0^{tree}')"

# An exact retry is a no-op.
push core "${remote_base}" v1.0.0

# Generated asset commits can differ while publishing the same package tree.
git -C "${repo}" tag -d v1.0.0 >/dev/null
git -C "${repo}" commit -q --allow-empty -m "Regenerate package"
git -C "${repo}" tag -m "Release v1.0.0" v1.0.0
push core "${remote_base}" v1.0.0

# Different package contents under an existing version must remain blocked.
git -C "${repo}" tag -d v1.0.0 >/dev/null
printf '%s\n' "changed release" > "${repo}/package.txt"
git -C "${repo}" add package.txt
git -C "${repo}" commit -q -m "Change package"
git -C "${repo}" tag -m "Release v1.0.0" v1.0.0

if push core "${remote_base}" v1.0.0; then
  echo "Expected a changed release tag to be rejected" >&2
  exit 1
fi

current_tree="$(git --git-dir="${remote_base}/core.git" rev-parse 'v1.0.0^{tree}')"
if [ "${current_tree}" != "${published_tree}" ]; then
  echo "Remote release tag changed after a rejected retry" >&2
  exit 1
fi

echo "split tag publication tests passed"
