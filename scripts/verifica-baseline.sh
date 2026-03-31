#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
source "${ROOT_DIR}/scripts/tooling/runtime-env.sh"

LARAVEL_DIR="${ROOT_DIR}/laravel-spedizionefacile-main"
NUXT_DIR="${ROOT_DIR}/nuxt-spedizionefacile-master"

if [[ ! -f "${LARAVEL_DIR}/artisan" ]]; then
  echo "Backend Laravel non trovato in ${LARAVEL_DIR}" >&2
  exit 1
fi

if [[ ! -f "${NUXT_DIR}/package.json" ]]; then
  echo "Frontend Nuxt non trovato in ${NUXT_DIR}" >&2
  exit 1
fi

echo "== Toolchain =="
echo "PHP: $(php_version_line)"
echo "Composer: $(composer_version_line)"
echo "Node: $(node_runtime_line)"
echo "npm: $(npm_runtime_line)"
echo

echo "== Frontend build =="
(
  cd "${NUXT_DIR}"
  if [[ ! -d "${NUXT_DIR}/node_modules" || ! -f "${NUXT_DIR}/node_modules/.package-lock.json" ]]; then
    echo "Dipendenze frontend mancanti: eseguo install una tantum."
    run_npm20 install --prefer-offline --no-audit --include=optional
  else
    echo "Dipendenze frontend gia' presenti: salto npm install."
  fi
  run_npm20 run build
)
echo

echo "== Backend tests =="
(
  cd "${LARAVEL_DIR}"
  run_php_cmd artisan test tests/Feature/AuthAndAdminAccountsTest.php tests/Feature/CartFlowTest.php
)
echo

echo "Baseline OK: build frontend e test backend minimi eseguiti con successo."
