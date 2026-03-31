#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
NUXT_PORT="${NUXT_PORT:-3001}"
LARAVEL_PORT="${LARAVEL_PORT:-8000}"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
DIM='\033[2m'
RESET='\033[0m'

clear_screen() {
  printf '\033[2J\033[H'
}

status_label() {
  local pattern="$1"
  local port="$2"
  if pgrep -f "$pattern" >/dev/null 2>&1; then
    printf '%bAttivo%b (porta %s)' "$GREEN" "$RESET" "$port"
  else
    printf '%bSpento%b' "$RED" "$RESET"
  fi
}

show_menu() {
  clear_screen
  echo ""
  echo -e "${CYAN}${BOLD}SpediamoFacile - pannello shell${RESET}"
  echo -e "${DIM}Wrapper leggero: la logica vera vive in scripts/.${RESET}"
  echo ""
  echo -e "Laravel: $(status_label "artisan serve.*--port ${LARAVEL_PORT}" "${LARAVEL_PORT}")"
  echo -e "Nuxt:    $(status_label "node .*nuxi\\.mjs dev.*--port ${NUXT_PORT}" "${NUXT_PORT}")"
  echo ""
  echo "1) Avvia locale"
  echo "2) Condividi online"
  echo "3) Chiudi tutto"
  echo "4) Log Laravel"
  echo "5) Log Nuxt"
  echo "q) Esci"
  echo ""
}

tail_log() {
  local path="$1"
  local label="$2"
  if [[ ! -f "$path" ]]; then
    echo "Log ${label} non disponibile: $path"
    read -r -p "Invio per tornare al menu..."
    return 0
  fi

  echo ""
  echo -e "${CYAN}Log ${label} (Ctrl+C per tornare al menu)${RESET}"
  tail -n 40 -f "$path" || true
}

stop_all() {
  pkill -f "artisan serve.*--port ${LARAVEL_PORT}" >/dev/null 2>&1 || true
  pkill -f "node .*nuxi\\.mjs dev.*--port ${NUXT_PORT}" >/dev/null 2>&1 || true
  pkill -f "cloudflared tunnel --url http://127.0.0.1:${LARAVEL_PORT}" >/dev/null 2>&1 || true
  pkill -f "cloudflared tunnel --url http://127.0.0.1:${NUXT_PORT}" >/dev/null 2>&1 || true
  echo ""
  echo -e "${GREEN}Servizi chiusi.${RESET}"
  read -r -p "Invio per tornare al menu..."
}

while true; do
  show_menu
  read -r -p "Scelta: " choice
  case "$choice" in
    1) bash "${ROOT_DIR}/scripts/avvia-tutto.sh"; read -r -p "Invio per tornare al menu..." ;;
    2) bash "${ROOT_DIR}/scripts/avvia-cloudflare.sh"; read -r -p "Invio per tornare al menu..." ;;
    3) stop_all ;;
    4) tail_log /tmp/laravel.log "Laravel" ;;
    5) tail_log /tmp/nuxt.log "Nuxt" ;;
    q|Q) exit 0 ;;
    *) echo -e "${YELLOW}Scelta non valida.${RESET}"; sleep 1 ;;
  esac
done
