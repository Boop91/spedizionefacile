#!/usr/bin/env bash
# Shared runtime resolution for local scripts.
# Keeps the repo runnable on mixed WSL/Windows setups without assuming PATH is perfect.

if declare -F run_php_cmd >/dev/null 2>&1 && declare -F run_npm20 >/dev/null 2>&1; then
  return 0 2>/dev/null || exit 0
fi

current_node_major() {
  node -p "Number(process.versions.node.split('.')[0])" 2>/dev/null || echo 0
}

has_native_node20() {
  [[ "$(current_node_major)" =~ ^[0-9]+$ ]] && (( $(current_node_major) >= 20 ))
}

resolve_php_bin() {
  if command -v php >/dev/null 2>&1; then
    command -v php
    return 0
  fi

  local candidate
  local -a candidates=(
    "/mnt/c/Users/${USER:-}/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
    "/mnt/c/Users/${USER:-}/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
    "/mnt/c/Users/${USER:-}/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
  )

  for candidate in "${candidates[@]}"; do
    if [[ -x "$candidate" ]]; then
      echo "$candidate"
      return 0
    fi
  done

  shopt -s nullglob
  for candidate in /mnt/c/Users/*/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.*_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe; do
    if [[ -x "$candidate" ]]; then
      echo "$candidate"
      shopt -u nullglob
      return 0
    fi
  done
  shopt -u nullglob

  return 1
}

resolve_composer_phar() {
  local candidate
  local -a candidates=(
    "/mnt/c/composer/composer.phar"
    "/mnt/c/ProgramData/ComposerSetup/bin/composer.phar"
  )

  for candidate in "${candidates[@]}"; do
    if [[ -f "$candidate" ]]; then
      echo "$candidate"
      return 0
    fi
  done

  return 1
}

to_native_path() {
  local path="$1"
  if [[ "$path" == /mnt/* ]] && command -v wslpath >/dev/null 2>&1; then
    wslpath -w "$path"
    return 0
  fi
  echo "$path"
}

php_version_line() {
  local php_bin
  php_bin="$(resolve_php_bin 2>/dev/null)" || {
    echo "non trovato"
    return 0
  }
  "$php_bin" -v 2>/dev/null | head -n 1 || echo "non trovato"
}

composer_version_line() {
  local php_bin composer_phar
  php_bin="$(resolve_php_bin 2>/dev/null)" || {
    echo "non trovato"
    return 0
  }
  composer_phar="$(resolve_composer_phar 2>/dev/null)" || {
    if command -v composer >/dev/null 2>&1; then
      composer --version 2>/dev/null || echo "non trovato"
      return 0
    fi
    echo "non trovato"
    return 0
  }
  "$php_bin" "$(to_native_path "$composer_phar")" --version 2>/dev/null || echo "non trovato"
}

node_runtime_line() {
  if has_native_node20; then
    echo "$(node -v 2>/dev/null || echo 'non trovato') (system)"
    return 0
  fi
  local version
  version="$(npx -y -p node@20 node -v 2>/dev/null || true)"
  if [[ -n "$version" ]]; then
    echo "$version (npx node@20)"
  else
    echo "non trovato"
  fi
}

npm_runtime_line() {
  if has_native_node20; then
    npm -v 2>/dev/null || echo "non trovato"
    return 0
  fi
  npx -y -p node@20 npm -v 2>/dev/null || echo "non trovato"
}

run_php_cmd() {
  local php_bin
  php_bin="$(resolve_php_bin)" || {
    echo "PHP non trovato: installa PHP 8.2+ oppure rendilo disponibile nel PATH." >&2
    return 127
  }
  "$php_bin" "$@"
}

run_composer_cmd() {
  local composer_phar
  composer_phar="$(resolve_composer_phar)" || {
    if command -v composer >/dev/null 2>&1 && command -v php >/dev/null 2>&1; then
      composer "$@"
      return $?
    fi
    echo "Composer non trovato: installa Composer oppure /mnt/c/composer/composer.phar." >&2
    return 127
  }
  run_php_cmd "$(to_native_path "$composer_phar")" "$@"
}

run_node20() {
  if has_native_node20; then
    node "$@"
    return $?
  fi
  npx -y -p node@20 node "$@"
}

run_npm20() {
  if has_native_node20; then
    npm "$@"
    return $?
  fi
  npx -y -p node@20 npm "$@"
}
