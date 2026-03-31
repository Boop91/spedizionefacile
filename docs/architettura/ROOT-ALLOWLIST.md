# Root Allowlist — Cosa puo' stare nel root della repo

Data ultimo aggiornamento: 2026-03-20
Stato: canonico

## Obiettivo

Il root della repo deve essere leggibile in meno di 30 secondi.
Una persona nuova deve capire subito:
- dove sta il frontend
- dove sta il backend
- dove stanno le docs vive
- dove stanno gli script
- dove stanno report/output/backup

## Consentito nel root

### Directory canoniche
- `nuxt-spedizionefacile-master/` — frontend Nuxt
- `laravel-spedizionefacile-main/` — backend Laravel
- `.devcontainer/` — configurazione condivisa ambiente sviluppo
- `docs/` — documentazione viva
- `docs/riferimento/operativo/` — procedure operative e manuali tecnici non di runtime
- `docs/riferimento/vendor/` — reference esterne o materiali vendor non di progetto
- `reports/` — report storici, debug e analisi non canoniche
- `reports/runtime/` — dump e output locali non canonici ma consultabili
- `reports/runtime/diagnostica/` — report diagnostici correnti generati dagli script
- `reports/runtime/legacy-logs/` — storico di log e dump locali non piu' attivi
- `output/` — screenshot, artefatti locali, export temporanei
- `scripts/` — automazioni vere
- `scripts/pudo/` — helper manuali PUDO e fallback locale
- `_backup/` — backup espliciti con politica di retention
- `_LOG/` — log operativi locali correnti
- `URL_ONLINE.txt`, `_PORTS.json`, `_STATE.json` — stato operativo locale usato da launcher e pannello

### Directory locali tollerate ma non canoniche
- `.claude/` — tooling locale; puo' contenere una worktree attiva ma non materiale canonico
- `.codex/` — tooling locale dell'assistente; non deve diventare sorgente di verita'

Regole aggiuntive:
- `.claude/worktrees/` puo' contenere al massimo una worktree attiva necessaria
- family duplicate come `.worktrees/` non devono restare nel root
- log MCP o tooling locale non devono vivere come directory top-level: vanno in `reports/runtime/legacy-logs/`

### File canonici
- `README.md`
- `AGENTS.md`
- `.gitignore`
- `Caddyfile*`
- script di avvio principali (`AVVIA_LOCALE.bat`, `PANNELLO.bat`, `CONDIVIDI_ONLINE.bat`, `PANNELLO.ps1`) se servono davvero al flusso operativo
- alias e scorciatoie locali (`AVVIA_TUTTO.bat`, `CHIUDI_TUTTO.bat`, `APRI_LOG.bat`, `pannello.sh`) solo se restano wrapper sottili senza logica duplicata
- `_SQUADRA_DIARIO.md`
- file di stato operativi strettamente necessari (`_STATE.json`, `_PORTS.json`, `URL_ONLINE.txt`) se usati dagli script

## Non consentito nel root

Questi file non devono restare sciolti nel root:
- report investigativi
- screenshot di debug
- dump di rete/console
- note temporanee
- changelog di debug locali
- immagini non canoniche non usate dal runtime
- file di output php o diagnostica temporanea

## Destinazione corretta

- documentazione viva -> `docs/`
- documentazione/reference esterna -> `docs/riferimento/vendor/`
- report storici -> `reports/legacy/`
- dump runtime -> `reports/runtime/`
- dump diagnostici correnti -> `reports/runtime/diagnostica/`
- log e dump runtime storici -> `reports/runtime/legacy-logs/`
- screenshot/artefatti -> `output/`
- documenti superati ma da tenere -> `docs/_archivio/`

## Regola operativa

Prima di creare un nuovo file nel root chiedersi:
1. serve al runtime?
2. serve come ingresso ufficiale del progetto?
3. e' documentazione canonica?
4. e' solo un report o un output temporaneo?

Se la risposta alla 4 e' si', il file **non deve stare nel root**.
