# Stato dei documenti architetturali

Data ultimo aggiornamento: 2026-03-20
Stato: canonico

## Legenda
- `canonico` = documento attuale da usare come riferimento
- `da consolidare` = utile ma va riallineato o assorbito
- `obsoleto` = non piu' affidabile come fonte primaria
- `archivio` = da conservare ma fuori dal percorso principale

| Documento | Stato | Nota |
|-----------|-------|------|
| `docs/architettura/AUDIT-MASTER.md` | canonico | fotografia principale del sistema |
| `docs/architettura/ROADMAP-REFACTOR.md` | canonico | piano a tranche compatibili |
| `docs/architettura/STANDARD-PROGETTO.md` | canonico | regole di qualità e struttura |
| `docs/architettura/MATRICE-PRIORITA.md` | canonico | priorità operative |
| `docs/architettura/ROOT-ALLOWLIST.md` | canonico | regole del root e destinazioni canoniche |
| `docs/architettura/ROOT-CLASSIFICAZIONE.md` | canonico | inventario top-level e decisioni di collocazione |
| `docs/architettura/DEAD-CODE-LEDGER.md` | canonico operativo | ledger vivo dei candidati legacy/morti/archiviabili |
| `docs/architettura/STATO-DOCUMENTI.md` | canonico | registro dello stato documentale |
| `docs/adr/ADR-LOG.md` | canonico | decisioni architetturali e di governance |
| `docs/INDICE.md` | canonico | unico ingresso documentale da aprire per orientarsi |
| `docs/impara/KIT-ONBOARDING.md` | canonico | ingresso umano al progetto |
| `docs/riferimento/vendor/README.md` | canonico locale | indice delle reference esterne mantenute solo come supporto |
| `docs/INDICE-DOCUMENTAZIONE.md` | archivio/rimosso | indice duplicato eliminato per ridurre rumore documentale |
| `docs/architettura/MODULI.md` | da consolidare | utile, ma da riallineare ai confini modulo più recenti |
| `docs/architettura/MAPPA-FLUSSI.md` | da consolidare | alcuni flussi sono cresciuti oltre la descrizione attuale |
| `docs/architettura/MAPPA-DATI.md` | da consolidare | alcune shape e sorgenti di verità non sono pienamente aggiornate |
| `docs/architettura/INVENTARIO-FLUSSI.md` | da consolidare | molto ricco, ma da riclassificare rispetto ai moduli attuali |
| `docs/architettura/GAP-ANALYSIS-RITIRO-BORDERO-NOTIFICHE.md` | canonico locale | valido per quel sottodominio, non documento globale |
| report storici nel root o in `reports/legacy/` | archivio | non usare come fonte primaria dello stato attuale |
| `docs/_archivio/README_TUTTOINSIEME.txt` | archivio | documento operativo storico non piu' canonico |
| `docs/_archivio/REGOLE-PROGETTO.md` | archivio | regole assorbite in `STANDARD-PROGETTO.md` |
| `docs/_archivio/TODO_SQUADRA.md` | archivio | piano storico sostituito da diario e roadmap |

## Regola

Chi apre un nuovo documento tecnico deve dichiarare subito il suo stato e il suo rapporto con i documenti canonici.
