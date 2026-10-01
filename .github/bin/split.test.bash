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

# Asset directories are ignored in the monorepo but are release artifacts in
# the split packages. Force-add them so the immutable package tag contains the
# same runtime files that check_assets validated.
admin_repo="${PLATFORM_DIR}/repos/administration"
mkdir -p "${admin_repo}/Resources/public/administration/assets"
git -C "${admin_repo}" init -q -b trunk
git -C "${admin_repo}" config user.email "test@shopwell.com"
git -C "${admin_repo}" config user.name "Shopwell Test"
printf '%s\n' 'public/' > "${admin_repo}/Resources/.gitignore"
printf '%s\n' 'console.log("admin");' > "${admin_repo}/Resources/public/administration/assets/app.js"
printf '%s\n' 'body {}' > "${admin_repo}/Resources/public/administration/assets/app.css"
git -C "${admin_repo}" add Resources/.gitignore
git -C "${admin_repo}" commit -q -m "Initial administration package"
include_admin_assets

git -C "${admin_repo}" diff --cached --name-only | grep -qx 'Resources/public/administration/assets/app.js'
git -C "${admin_repo}" diff --cached --name-only | grep -qx 'Resources/public/administration/assets/app.css'

storefront_repo="${PLATFORM_DIR}/repos/storefront"
mkdir -p \
  "${storefront_repo}/Resources/app/storefront/dist/storefront" \
  "${storefront_repo}/Resources/app/storefront/vendor/bootstrap" \
  "${storefront_repo}/Resources/public/administration/assets"
git -C "${storefront_repo}" init -q -b trunk
git -C "${storefront_repo}" config user.email "test@shopwell.com"
git -C "${storefront_repo}" config user.name "Shopwell Test"
printf '%s\n' 'Resources/app/storefront/vendor/' > "${storefront_repo}/.gitignore"
printf '%s\n' 'app/storefront/dist/' 'public/' > "${storefront_repo}/Resources/.gitignore"
printf '%s\n' 'console.log("storefront");' > "${storefront_repo}/Resources/app/storefront/dist/storefront/storefront.js"
printf '%s\n' '{}' > "${storefront_repo}/Resources/app/storefront/vendor/bootstrap/package.json"
printf '%s\n' 'console.log("storefront-admin");' > "${storefront_repo}/Resources/public/administration/assets/app.js"
printf '%s\n' 'body {}' > "${storefront_repo}/Resources/public/administration/assets/app.css"
git -C "${storefront_repo}" add .gitignore Resources/.gitignore
git -C "${storefront_repo}" commit -q -m "Initial storefront package"
include_storefront_assets

git -C "${storefront_repo}" diff --cached --name-only | grep -qx 'Resources/app/storefront/dist/storefront/storefront.js'
git -C "${storefront_repo}" diff --cached --name-only | grep -qx 'Resources/app/storefront/vendor/bootstrap/package.json'
git -C "${storefront_repo}" diff --cached --name-only | grep -qx 'Resources/public/administration/assets/app.js'
git -C "${storefront_repo}" diff --cached --name-only | grep -qx 'Resources/public/administration/assets/app.css'

echo "split tag publication tests passed"
