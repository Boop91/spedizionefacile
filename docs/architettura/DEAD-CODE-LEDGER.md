# Dead Code Ledger — Candidati codice morto, legacy o archiviabile

Data ultimo aggiornamento: 2026-03-24
Stato: attivo

## Regole stato
- `candidato` = forte sospetto, da verificare prima di rimuovere
- `confermato` = inutilizzato o superato, pronto per rimozione/archivio
- `rimosso` = gia' tolto dal codebase attivo
- `da archiviare` = non e' codice vivo ma va tenuto per storico

| Area | Elemento | Stato | Motivo | Azione proposta |
|------|----------|-------|--------|-----------------|
| frontend | `nuxt-spedizionefacile-master/components/Preventivo.vue.backup-1772462877` | rimosso | backup temporaneo dentro sorgente, nessun uso runtime | rimosso dal tracking; conservata copia in `_backup/repo_hygiene_20260320/` |
| frontend | `nuxt-spedizionefacile-master/components/VecchioPreventivo.vue` | rimosso | componente legacy senza import o uso reale | rimosso dal sorgente attivo; conservata copia in `_backup/repo_hygiene_20260320/` |
| frontend | `nuxt-spedizionefacile-master/components/ButtonArrow.vue` | rimosso | componente senza riferimenti runtime residui | rimosso dal sorgente attivo; conservata copia in `_backup/repo_hygiene_20260320/` |
| frontend | `nuxt-spedizionefacile-master/components/PromoBanner.vue` | rimosso | banner homepage non piu' richiamato dal progetto | rimosso dal sorgente attivo; conservata copia in `_backup/repo_hygiene_20260320/` |
| frontend | `nuxt-spedizionefacile-master/composables/useLogger.js` | rimosso | composable senza import o auto-uso rilevato | rimosso dal codebase attivo |
| frontend | `nuxt-spedizionefacile-master/composables/useLocationAutocomplete.js` | rimosso | composable autocomplete senza riferimenti runtime dopo l'estrazione di `useLocationSearch.js` | rimosso dal codebase attivo; conservata copia in `_backup/repo_hygiene_20260324/` |
| root | `ANALISI-COMPLETA-E-FIX.md` | archiviato | report storico, non doc canonica | spostato in `reports/legacy/` |
| root | `CHANGELOG_COMPLETO.md` | archiviato | changelog investigativo locale, non changelog prodotto | spostato in `reports/legacy/` |
| root | `MODIFICHE_ANIMAZIONI.md` | archiviato | note tecniche storiche | spostato in `reports/legacy/` |
| root | `MODIFICHE_NUOVO_FLUSSO_UX.md` | archiviato | note tecniche storiche | spostato in `reports/legacy/` |
| root | `PUDO_FIX_SUMMARY.md` | archiviato | report puntuale, non documentazione viva | spostato in `reports/legacy/` |
| root | `TEST_REPORT.md` | archiviato | report test storico | spostato in `reports/legacy/` |
| root | `README_TUTTOINSIEME.txt` | archiviato | duplicato operativo non canonico | spostato in `docs/_archivio/` |
| root | `REGOLE-PROGETTO.md` | archiviato | governance minima gia' assorbita nello standard canonico | spostato in `docs/_archivio/` |
| root | `TODO_SQUADRA.md` | archiviato | piano storico non piu' guida operativa principale | spostato in `docs/_archivio/` |
| root | `MASTER-IN-CUCINA-CUOCO-PROFESSIONISTA.jpg` | archiviato | asset senza riferimenti runtime o documentali utili | spostato in `docs/_archivio/assets/` |
| root | `BrtRestApi-PUDO-EN/` | archiviato dal root | reference esterna non deve stare tra i moduli attivi | spostato in `docs/riferimento/vendor/` |
| root | `nul` | archiviato | dump locale Windows senza valore applicativo | spostato in `reports/runtime/nul.txt` |
| root/tooling | `.worktrees/ui-hero-editorial-overlap` | rimosso | worktree duplicata pulita, senza commit unici rispetto al branch registrato | rimossa con `git worktree remove --force`, branch preservato |
| root/tooling | `.claude/worktrees/clever-bhaskara` | archiviato | worktree rotta/stale, non piu' affidabile come sorgente locale | cartella spostata in `_backup/tooling_archive/claude-worktree-clever-bhaskara-20260320/`, branch preservato |
| root/tooling | `.playwright-mcp/*.log` | archiviato | log MCP storici, non sorgente ne' diagnostica corrente | spostati in `reports/runtime/legacy-logs/playwright-mcp/` |
| root/tooling | `_LOGS/` | archiviato | vecchia cartella log non piu' usata dagli script attivi | spostata in `reports/runtime/legacy-logs/_LOGS/` |
| root/tooling | `tmp-diagnostica/report.txt` | archiviato | output diagnostico storico; i report correnti non devono sporcare il root | spostato in `reports/runtime/legacy-logs/tmp-diagnostica/` |
| root/tooling | `.claude/settings.local*.off.json`, `.claude/settings.local_vecchio.json` | archiviato | snapshot locali esplicitamente vecchi/off | spostati in `_backup/tooling_archive/claude-settings-20260320/` |
| root/tooling | `_LOG/` file setup/recovery storici | archiviato | log di bootstrap e recovery mischiati ai log correnti | spostati in `reports/runtime/legacy-logs/_LOG_setup/` e `_LOG_recovery/` |
| backend | `laravel-spedizionefacile-main/check_pudo.php` | rimosso dal backend root | helper diagnostico fuori convenzioni Laravel ma ancora utile localmente | ricollocato in `scripts/pudo/check-pudo-sqlite.php` |
| backend | helper PUDO manuali nel backend root (`ATTIVA_PUDO.bat`, `IMPORTA_PUDO.bat`, `setup-pudo-fallback.sh`, `PUDO_FALLBACK_SETUP.md`) | ricollocato | materiale operativo utile ma fuori posto nel backend attivo | spostato in `scripts/pudo/` e `docs/riferimento/operativo/` |
| docs | documenti architetturali in drift | candidato | descrivono parzialmente il sistema attuale | riallineare o archiviare in `docs/_archivio/` |

## Criterio di rimozione

Un elemento puo' essere rimosso solo dopo verifica di almeno uno di questi punti:
1. nessun import/include/richiamo diretto
2. nessun riferimento in docs canoniche o script operativi
3. nessuna funzione di rollback o audit che richieda di mantenerlo nel sorgente attivo

## Criterio di archiviazione

Se un file serve a capire la storia ma non il sistema attuale, va archiviato e non lasciato nel root o in una cartella sorgente.
