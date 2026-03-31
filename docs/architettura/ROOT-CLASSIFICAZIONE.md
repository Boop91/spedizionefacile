# Classificazione del root — Stato attuale e destinazione

Data ultimo aggiornamento: 2026-03-21
Stato: canonico

## Regola di lettura

Questa tabella classifica gli elementi top-level del repository e indica dove devono vivere o come devono essere trattati.

| Voce root | Categoria | Stato attuale | Destinazione/decisione |
|-----------|-----------|---------------|------------------------|
| `nuxt-spedizionefacile-master/` | sorgente | canonico | resta nel root |
| `laravel-spedizionefacile-main/` | sorgente | canonico | resta nel root |
| `.devcontainer/` | config sviluppo | canonico | resta nel root |
| `docs/` | documentazione viva | canonico | resta nel root |
| `docs/riferimento/operativo/` | documentazione operativa | canonico | resta sotto `docs/` |
| `docs/riferimento/vendor/` | reference esterne | canonico | resta sotto `docs/` |
| `scripts/` | tooling | canonico | resta nel root |
| `scripts/pudo/` | tooling operativo locale | canonico | helper manuali PUDO fuori dal backend root |
| `reports/` | report storici/debug | creato in questo intervento | resta nel root |
| `output/` | artefatti locali | canonico ma da governare | resta nel root con policy: tenere solo snapshot correnti e archiviare quelli storici |
| `_backup/` | backup | canonico ma da governare | resta nel root con policy |
| `_LOG/` | log operativi correnti | canonico locale | resta nel root, limitato ai log live |
| `.claude/` | tooling locale | non canonico per il repo condiviso | resta solo per lock e worktree attive |
| `.codex/` | tooling locale | non canonico per il repo condiviso | resta ignorato e fuori dalla documentazione di prodotto |
| `README.md` | ingresso progetto | canonico | resta nel root |
| `AGENTS.md` | regole operative | canonico | resta nel root |
| `_SQUADRA_DIARIO.md` | tracciamento turni | canonico | resta nel root |
| `Caddyfile*` | configurazione operativa | canonico | resta nel root |
| `AVVIA_LOCALE.bat`, `CONDIVIDI_ONLINE.bat`, `PANNELLO.bat` | launcher Windows | canonico locale | restano nel root come ingressi rapidi principali |
| `CHIUDI_TUTTO.bat`, `APRI_LOG.bat`, `AVVIA_TUTTO.bat` | launcher di supporto/alias | tollerati ma non primari | restano solo come scorciatoie locali senza logica propria |
| `PANNELLO.ps1` | pannello operativo Windows | canonico locale | resta come orchestratore locale principale |
| `pannello.sh` | pannello shell | ridotto e tollerato | resta solo come wrapper leggero verso `scripts/` |
| `ANALISI-COMPLETA-E-FIX.md` | report storico | archiviato | spostato in `reports/legacy/ANALISI-COMPLETA-E-FIX.md` |
| `CHANGELOG_COMPLETO.md` | report storico | archiviato | spostato in `reports/legacy/CHANGELOG_COMPLETO.md` |
| `MODIFICHE_ANIMAZIONI.md` | report tecnico storico | archiviato | spostato in `reports/legacy/MODIFICHE_ANIMAZIONI.md` |
| `MODIFICHE_NUOVO_FLUSSO_UX.md` | report tecnico storico | archiviato | spostato in `reports/legacy/MODIFICHE_NUOVO_FLUSSO_UX.md` |
| `PUDO_FIX_SUMMARY.md` | report tecnico storico | archiviato | spostato in `reports/legacy/PUDO_FIX_SUMMARY.md` |
| `TEST_REPORT.md` | report test storico | archiviato | spostato in `reports/legacy/TEST_REPORT.md` |
| `README_TUTTOINSIEME.txt` | documento operativo duplicato | archiviato | spostato in `docs/_archivio/README_TUTTOINSIEME.txt` |
| `REGOLE-PROGETTO.md` | documento di governance | archiviato | spostato in `docs/_archivio/REGOLE-PROGETTO.md` dopo consolidamento nello standard canonico |
| `TODO_SQUADRA.md` | piano storico | archiviato | spostato in `docs/_archivio/TODO_SQUADRA.md` per storico, sostituito da diario + roadmap |
| `MASTER-IN-CUCINA-CUOCO-PROFESSIONISTA.jpg` | asset non chiaramente di runtime | archiviato | spostato in `docs/_archivio/assets/` in assenza di riferimenti runtime |
| `BrtRestApi-PUDO-EN/` | reference esterna | canonico locale | spostato in `docs/riferimento/vendor/BrtRestApi-PUDO-EN/` |
| `URL_ONLINE.txt` | file di stato operativo | canonico locale | resta nel root ma ignorato da git |
| `_PORTS.json`, `_STATE.json` | file di stato operativo | canonico locale | restano nel root per launcher/pannello |
| `cookies.txt`, `headers.txt` | output locale | archiviato dal root | spostati in `reports/runtime/` |
| `.claude/worktrees/ui-hero-editorial-overlap` | worktree locale attiva | tollerata ma non canonica | resta finche' contiene lavoro unico non consolidato |
| `.playwright-mcp/` | log tooling locale | archiviato dal root | log spostati in `reports/runtime/legacy-logs/playwright-mcp/` |
| `_LOGS/` | log legacy | archiviato dal root | spostato in `reports/runtime/legacy-logs/_LOGS/` |
| `tmp-diagnostica/` | output runtime locale | archiviato dal root | report spostato in `reports/runtime/legacy-logs/tmp-diagnostica/`; nuovo output in `reports/runtime/diagnostica/` |
| `.worktrees/` | family worktree duplicata | rimossa | tolta dal root dopo pulizia worktree duplicate |
| `.claude/worktrees/clever-bhaskara` | worktree rotta/stale | archiviata | spostata in `_backup/tooling_archive/claude-worktree-clever-bhaskara-20260320/` e rimossa dalla lista worktree |
| `.claude/settings.local*.json` e `.claude/settings.local_vecchio.json` | snapshot locali tooling | archiviati | spostati in `_backup/tooling_archive/claude-settings-20260320/` |
| `nul` | dump locale accidentale | archiviato | spostato in `reports/runtime/nul.txt` |
| `laravel-spedizionefacile-main/check_pudo.php` | helper diagnostico fuori posto | ricollocato | spostato in `scripts/pudo/check-pudo-sqlite.php` |
| `laravel-spedizionefacile-main/ATTIVA_PUDO.bat` | helper operativo fuori posto | ricollocato | spostato in `scripts/pudo/ATTIVA_PUDO.bat` |
| `laravel-spedizionefacile-main/IMPORTA_PUDO.bat` | helper operativo fuori posto | ricollocato | spostato in `scripts/pudo/IMPORTA_PUDO.bat` |
| `laravel-spedizionefacile-main/setup-pudo-fallback.sh` | helper operativo fuori posto | ricollocato | spostato in `scripts/pudo/setup-pudo-fallback.sh` |
| `laravel-spedizionefacile-main/PUDO_FALLBACK_SETUP.md` | manuale tecnico fuori posto | ricollocato | spostato in `docs/riferimento/operativo/PUDO_FALLBACK_SETUP.md` |

## Azioni immediate già fatte
- creato `reports/`
- creato `docs/_archivio/`
- creato `docs/adr/`
- consolidato `docs/INDICE.md` come unico indice canonico e rimosso l'indice duplicato
- spostati alcuni report e dump runtime fuori dal root
- spostati nel legacy i report storici principali che non devono piu' stare sciolti al root
- spostati in `scripts/pudo/` gli helper manuali PUDO che erano mischiati al backend
- spostati in `reports/runtime/` i dump locali `cookies.txt` e `headers.txt`
- rimossa la family worktree duplicata `.worktrees/`
- archiviata la worktree rotta `.claude/worktrees/clever-bhaskara`
- archiviati i log `.playwright-mcp/`, `_LOGS/` e `tmp-diagnostica/`
- ridotto `_LOG/` ai soli log operativi correnti
- riallineati gli script di diagnostica per scrivere in `reports/runtime/diagnostica/`
- potata `output/playwright/` lasciando solo gli screenshot correnti e archiviando quelli storici in `_backup/output_playwright_20260321/`

## Azioni successive bloccate
1. mantenere `docs/riferimento/vendor/` come unica casa per reference esterne
2. monitorare che `.claude/worktrees/ui-hero-editorial-overlap` resti l'unica worktree attiva necessaria
3. ridurre al minimo i file top-level non canonici
