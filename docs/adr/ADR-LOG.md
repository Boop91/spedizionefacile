# ADR Log — SpediamoFacile

Data ultimo aggiornamento: 2026-03-20
Stato: attivo

| ADR | Titolo | Stato | Data | Nota |
|-----|--------|-------|------|------|
| ADR-001 | Monorepo con doppia applicazione separata (`nuxt-spedizionefacile-master` / `laravel-spedizionefacile-main`) | accettato | 2026-03-20 | Si mantiene per compatibilita' operativa; eventuale rename a `frontend/` e `backend/` solo in tranche dedicata. |
| ADR-002 | Audit-first e refactor a tranche compatibili | accettato | 2026-03-20 | Vietato approccio big bang sul progetto. |
| ADR-003 | Pricing con sorgente di verita' backend unica | target architetturale | 2026-03-20 | Il calcolo ufficiale deve convergere su `PriceEngineService`. |
| ADR-004 | Root della repo con allowlist e categorie esplicite | accettato | 2026-03-20 | Il root non puo' piu' accumulare report, screenshot o dump sciolti. |
| ADR-005 | Preview admin e runtime hero devono condividere la stessa struttura semantica | target architetturale | 2026-03-20 | Evitare preview "simili" ma non equivalenti. |
| ADR-006 | Documentazione viva separata da report storici e archivio | accettato | 2026-03-20 | `docs/` per documenti canonici, `reports/` per analisi storiche, `docs/_archivio/` per documenti superati. |

## Regola di crescita

Quando una decisione:
- cambia i confini di un modulo,
- cambia una sorgente di verita',
- cambia una convenzione obbligatoria,
- cambia un contratto importante,

va aggiunta qui e, se necessario, anche in un file ADR dedicato.
