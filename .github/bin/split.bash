#!/usr/bin/env bash

# Transforms input into lowercase-only.
#
# [1]: A string
lowercase() {
  echo "${1}" | tr '[:upper:]' '[:lower:]'
}

uppercase_first() {
  echo "$(tr '[:lower:]' '[:upper:]' <<< ${1:0:1})${1:1}"
}

# Creates a split repository for a subpackage of platform.
#
# [1]: A subpackage. e.g.: "Administration"
split_repo() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local rand; rand="$(head -c4 < /dev/urandom | base64 | tr -d '+/=')"

  local split_repos_dir; split_repos_dir="${PLATFORM_DIR}/repos"
  local split_repo_dir; split_repo_dir="${PLATFORM_DIR}/repos/$(lowercase ${package})"
  local tmp_target_repo_dir; tmp_target_repo_dir="$(mktemp -d)/"
  local split_branch; split_branch="${package_lower}-${rand}"
  local default_branch; default_branch="$(git config --global init.defaultBranch || true)"; default_branch=${default_branch:-trunk}

  git config --global --add safe.directory "${PLATFORM_DIR}" # TODO: Find out why this is necessary in CI.

  mkdir -p "${split_repos_dir}"

  git -C "${PLATFORM_DIR}" subtree split -q -P src/${package}/ -b ${split_branch}

  git -C "${PLATFORM_DIR}" remote remove "tmp_target_repo" > /dev/null 2>&1 || true

  git init -b "${default_branch}" --bare "${tmp_target_repo_dir}"

  git -C "${PLATFORM_DIR}" remote add -t "${default_branch}" "tmp_target_repo" "${tmp_target_repo_dir}"
  git -C "${PLATFORM_DIR}" push -u "tmp_target_repo" "${split_branch}:${default_branch}" -f

  if [ -d "${split_repo_dir}" ]; then
    local scrapyard; scrapyard="$(mktemp -d)"

    printf "INFO: Directory %s already exists, moving it out of the way to %s...\n" "${split_repo_dir}" "${scrapyard}"
    mv "${split_repo_dir}" "${scrapyard}"
  fi

  git clone -b "${default_branch}" "${tmp_target_repo_dir}" "${split_repo_dir}"
}

# Copies existing assets for a subpackage of platform into the respective split
# repositories.
#
# [1]: A subpackage. e.g.: "Administration"
copy_assets() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"

  if [ -d "${PLATFORM_DIR}/src/${package}/Resources/public" ]; then
    cp -r "${PLATFORM_DIR}/src/${package}/Resources/public" "${PLATFORM_DIR}/repos/${package_lower}/Resources/"
  fi

  if [ -d "${PLATFORM_DIR}/src/${package}/Resources/app/${package_lower}/dist" ]; then
    cp -r "${PLATFORM_DIR}/src/${package}/Resources/app/${package_lower}/dist" "${PLATFORM_DIR}/repos/${package_lower}/Resources/app/${package_lower}/"
  fi

  if [ -d "${PLATFORM_DIR}/src/${package}/Resources/app/${package_lower}/vendor" ]; then
    cp -r "${PLATFORM_DIR}/src/${package}/Resources/app/${package_lower}/vendor" "${PLATFORM_DIR}/repos/${package_lower}/Resources/app/${package_lower}/"
  fi
}

# Returns a list of mandatory assets for the Administration package.
check_admin_assets() {
  [[ "$(find ${PLATFORM_DIR}/repos/administration/Resources/public -iname "*.js"  | wc -l)" -gt 0 ]]
  [[ "$(find ${PLATFORM_DIR}/repos/administration/Resources/public -iname "*.css"  | wc -l)" -gt 0 ]]
}

# Returns a list of mandatory assets for the Storefront package.
storefront_assets_list() {
  cat <<EOF | tr -d '[:blank:]'
    ${PLATFORM_DIR}/repos/storefront/Resources/app/storefront/dist/storefront/storefront.js
    ${PLATFORM_DIR}/repos/storefront/Resources/app/storefront/vendor/bootstrap/package.json
EOF
}

check_storefront_assets() {
    stat -t $(storefront_assets_list) > /dev/null

    [[ "$(find ${PLATFORM_DIR}/repos/storefront/Resources/public/administration/assets/ -iname "*.js"  | wc -l)" -gt 0 ]]
    [[ "$(find ${PLATFORM_DIR}/repos/storefront/Resources/public/administration/assets/ -iname "*.css"  | wc -l)" -gt 0 ]]
}

# Checks whether all mandatory assets have been generated and copied to the
# correct repository.
check_assets() {
  local package; package=$(lowercase "${1:-""}")

  if [[ ${package} == "" || ${package} == "storefront" ]]; then
    check_storefront_assets
  fi

  if [[ ${package} == "" || ${package} == "administration" ]]; then
    check_admin_assets
  fi
}

require_core_version() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local package_version; package_version="${2}"
  local ref_type; ref_type="${3:-tag}"

  if [ "${package_lower}" != "core" ]; then
    if [[ $ref_type != "tag" ]]; then
      # version like? -> add dev at the end
      if echo -n "${package_version}" | grep -q -E '^[0-9]'; then
        package_version="${package_version}-dev"
      else
        package_version="dev-${package_version}"
      fi
    fi

    composer -d "${PLATFORM_DIR}/repos/${package_lower}" require "shopwell/core:${package_version}" --no-update --no-install
  fi
}

# Creates a new branch pointing at the current HEAD in a split repository of a
# subpackage of platform.
#
# [1]: A subpackage. e.g.: "Administration"
# [2]: The branch name.
branch() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local name; name="${2}"

  git -C "${PLATFORM_DIR}/repos/${package_lower}" checkout -B "${name}"
}

# Commits additional files in a split repository of a subpackage of platform.
#
# [1]: A subpackage. e.g.: "Administration"
# [2]: A commit message.
commit() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local message; message="${2}"

  git -C "${PLATFORM_DIR}/repos/${package_lower}" add .
  git -C "${PLATFORM_DIR}/repos/${package_lower}" commit --allow-empty -m "${message}"
}

# Creates a tag in a split repository of a subpackage of platform.
#
# [1]: A subpackage. e.g.: "Administration"
# [2]: The tag name.
tag() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local name; name="${2}"

  git -C "${PLATFORM_DIR}/repos/${package_lower}" tag -m "Release ${name}" "${name}"
}

# Pushes a split repository for a subpackage to it's remote.
#
# [1]: A subpackage. e.g.: "Administration"
# [2]: Base-URL of the remote repository, e.g.: "https://user:pass@git.example.com"
# [3]: The ref to push to, e.g.: "6.4.20.0"
push() {
  local package_lower; package_lower=$(lowercase "${1}")
  local package; package="$(uppercase_first "${package_lower}")"
  local remote_base_url; remote_base_url="${2}"
  local target_ref; target_ref="${3}"

  local remote_url; remote_url=$(printf "%s/%s.git" "${remote_base_url}" "${package_lower}")

  git -C "${PLATFORM_DIR}/repos/${package_lower}" remote remove upstream > /dev/null 2>&1 || true
  git -C "${PLATFORM_DIR}/repos/${package_lower}" remote add upstream "${remote_url}"

  git -C "${PLATFORM_DIR}/repos/${package_lower}" fetch -q upstream

  if git -C "${PLATFORM_DIR}/repos/${package_lower}" show-ref --verify "refs/tags/${target_ref}" > /dev/null 2>&1 ; then
    local remote_tag_ref; remote_tag_ref="refs/shopwell-remote-tags/${target_ref}"

    if git -C "${PLATFORM_DIR}/repos/${package_lower}" ls-remote --exit-code --tags upstream "refs/tags/${target_ref}" > /dev/null 2>&1; then
      git -C "${PLATFORM_DIR}/repos/${package_lower}" fetch -q upstream "refs/tags/${target_ref}:${remote_tag_ref}"

      local local_tree; local_tree=$(git -C "${PLATFORM_DIR}/repos/${package_lower}" rev-parse "refs/tags/${target_ref}^{tree}")
      local remote_tree; remote_tree=$(git -C "${PLATFORM_DIR}/repos/${package_lower}" rev-parse "${remote_tag_ref}^{tree}")

      # Release tags are immutable. A retry may generate a different commit with
      # the same files, but it must never replace published package contents.
      if [ "${local_tree}" = "${remote_tree}" ]; then
        printf "INFO: Tag %s already publishes the same %s tree; nothing to push.\n" "${target_ref}" "${package_lower}"
        return 0
      fi

      printf "ERROR: Tag %s already exists for %s with different contents; refusing to overwrite it.\n" "${target_ref}" "${package_lower}" >&2
      return 1
    fi

    git -C "${PLATFORM_DIR}/repos/${package_lower}" push upstream "refs/tags/${target_ref}:refs/tags/${target_ref}"
  else
    if git -C "${PLATFORM_DIR}/repos/${package_lower}" show-ref --verify "refs/remotes/upstream/${target_ref}" > /dev/null 2>&1 \
      && ! git -C "${PLATFORM_DIR}/repos/${package_lower}" merge-base --is-ancestor "upstream/${target_ref}" "${target_ref}"; then
      # Split branches are generated artifacts. Join the previous generated branch
      # as a second parent so publication remains fast-forward without changing the tree.
      git -C "${PLATFORM_DIR}/repos/${package_lower}" checkout "${target_ref}"
      git -C "${PLATFORM_DIR}/repos/${package_lower}" merge --strategy=ours --allow-unrelated-histories \
        -m "Join previous generated ${target_ref} branch" "upstream/${target_ref}"
    fi
    git -C "${PLATFORM_DIR}/repos/${package_lower}" push upstream "refs/heads/${target_ref}:refs/heads/${target_ref}"
  fi
}

portable_sed_in_place() {
  local expression; expression="${1}"
  local path; path="${2}"

  sed -E -i.bak "${expression}" "${path}"
  rm "${path}.bak"
}

# Removes certain asset-related entries from the admin .gitignore.
include_admin_assets() {
  portable_sed_in_place '/[/]?public([/]?|.*)/d' "${PLATFORM_DIR}/repos/administration/Resources/.gitignore"
}

# Removes certain asset-related entries from the storefront .gitignore.
include_storefront_assets() {
  portable_sed_in_place '/[/]?Resources[/]app[/]storefront[/]vendor([/]?|.*)/d' "${PLATFORM_DIR}/repos/storefront/.gitignore"
  portable_sed_in_place '/[/]?app[/]storefront[/]dist([/]?|.*)/d' "${PLATFORM_DIR}/repos/storefront/Resources/.gitignore"
  portable_sed_in_place '/[/]?public([/]?|.*)/d' "${PLATFORM_DIR}/repos/storefront/Resources/.gitignore"
}

include_assets() {
  local package; package=$(lowercase "${1}")

  if [[ ${package} == "administration" || ${package} == "storefront" ]]; then
    copy_assets "${package}"
    check_assets "${package}"
  fi

  if [[ ${package} == "administration" ]]; then
    include_admin_assets
  fi

  if [[ ${package} == "storefront" ]]; then
    include_storefront_assets
  fi
}

if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
    set -o errexit
    set -o pipefail

    if [ -n "${DEBUG:-}" ]; then
        set -x
    fi

    export PLATFORM_DIR="$(realpath "${CI_PROJECT_DIR:-$(dirname $(dirname $(dirname ${BASH_SOURCE[0]})))}")"

    "$@"
fi
