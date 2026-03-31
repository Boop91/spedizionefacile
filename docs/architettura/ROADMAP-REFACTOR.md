# Roadmap Refactor — SpediamoFacile

Data: 2026-03-20  
Tipo: piano esecutivo decision-complete  
Dipende da: [Audit master](AUDIT-MASTER.md)

---

## 1. Obiettivo

Questa roadmap traduce l'audit in un piano di refactor eseguibile senza interventi distruttivi.  
L'ordine non e' arbitrario: parte dai punti con piu' leva su stabilita' e comprensibilita', e rimanda le rifiniture a quando le sorgenti di verita' saranno state chiarite.

---

## 2. Principi di esecuzione

1. niente refactor "big bang";
2. prima test di caratterizzazione, poi refactor;
3. ogni fase deve lasciare il sistema piu' leggibile di prima;
4. ogni fase deve dichiarare cosa NON cambia;
5. ogni fase deve chiudersi con documentazione aggiornata.

---

## 3. Ordine delle fasi

### Fase 0 — Baseline, test, root hygiene e sincronizzazione documenti chiave

**Obiettivo**  
Creare una base di sicurezza minima prima di spezzare moduli o unificare logiche.

**Perche' viene prima**  
Senza baseline e test minimi, il refactor sul pricing o sul wizard rischia di rompere il flusso commerciale.

**Interventi**
- applicare la allowlist del root e spostare fuori dal top-level report, dump e materiali storici non canonici;
- separare in modo esplicito `docs/`, `reports/`, `output/`, `_backup/` e `scripts/`;
- segnare i documenti architetturali che oggi sono in drift;
- introdurre `STATO-DOCUMENTI`, `DEAD-CODE-LEDGER` e `ADR-LOG` come registri vivi;
- introdurre o rafforzare test di caratterizzazione sui flussi critici gia' esistenti;
- definire check minimi per:
  - preventivo step 1
  - checkout
  - pagamento riuscito
  - BRT/PUDO
  - admin immagini hero

**File/sottosistemi coinvolti**
- root della repo
- `docs/architettura/*`
- `docs/adr/*`
- `reports/*`
- `laravel-spedizionefacile-main/tests/Feature/Characterization/*`
- `nuxt-spedizionefacile-master/tests/unit/*`

**Non cambia**
- nessun contratto pubblico
- nessuna UI
- nessun comportamento business

**Criterio di completamento**
- il root contiene solo elementi canonici o esplicitamente ammessi;
- esistono test minimi per i flussi core;
- i documenti principali hanno stato sync esplicito.

---

### Fase 1 — Sorgente di verita' unica del pricing

**Obiettivo**  
Portare tutto il pricing sotto un solo motore backend esplicito.

**Problema che risolve**  
Oggi il prezzo puo' nascere in piu' punti con varianti divergenti.

**Interventi**
- fare di `PriceEngineService` il solo motore di calcolo;
- rendere `usePriceBands.js` un composable di consumo/configurazione, non di logica divergente;
- eliminare o svuotare i fallback hardcoded non allineati;
- uniformare il significato di:
  - peso
  - volume
  - price bands
  - supplementi CAP
  - fasce extra
- dichiarare le unita' nei nomi (`*_cents`, `*_eur`).

**File/sottosistemi coinvolti**
- `app/Services/PriceEngineService.php`
- `app/Http/Controllers/SessionController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PublicPriceBandController.php`
- `composables/usePriceBands.js`
- pannelli admin prezzi

**Test minimi**
- stessi input -> stesso totale su:
  - preventivo homepage
  - wizard/riepilogo
  - create order
  - checkout
- casi limite:
  - fascia standard
  - fascia extra peso
  - fascia extra volume
  - supplementi CAP

**Criterio di completamento**
- nessun prezzo critico viene piu' deciso fuori dal motore unico;
- frontend e backend concordano su tutti i casi caratterizzati.

---

### Fase 2 — Contratti dati e conversioni frontend/backend

**Obiettivo**  
Rendere leggibili e prevedibili le forme dei dati che attraversano il sistema.

**Problema che risolve**  
Stessi nomi con unita' diverse, conversioni sparse, payload trasformati in piu' punti.

**Interventi**
- creare una convenzione unica per money, peso, volume, indirizzi, servizi;
- introdurre mapper/normalizer espliciti dove oggi i controller fanno conversioni locali;
- centralizzare pattern ripetuti come:
  - `serviceData -> service_data`
  - normalizzazione servizi
  - mappatura PUDO/service_data
- documentare input/output per i moduli chiave.

**File/sottosistemi coinvolti**
- controllers cart/order/session
- resources package/order
- store/composables frontend

**Test minimi**
- payload frontend -> request backend -> resource ritorno senza perdita semantica;
- nessun valore denaro ambiguo per nome/unita'.

**Criterio di completamento**
- ogni shape critica ha un solo mapper noto;
- i nomi denaro dichiarano l'unita'.

---

### Fase 3 — Spezzare i file ad alta densita'

**Obiettivo**  
Ridurre la complessita' dei file che oggi fanno troppe cose.

**Problema che risolve**  
Preventivo, wizard, BRT e checkout sono difficili da leggere e da modificare in sicurezza.

**Interventi**
- spezzare `Preventivo.vue` in:
  - orchestration componente
  - logica colli
  - logica autocomplete/location
  - logica pricing display
  - validazione smart
- spezzare `[step].vue` in sotto-blocchi per:
  - services section
  - addresses section
  - PUDO section
  - summary sticky
- spezzare `BrtService.php` in sottoservizi o helper dedicati:
  - build payload
  - request client
  - response parser
  - geografia/PUDO
  - tracking/document dispatch
- alleggerire `StripeController.php`, `CartController.php`, `OrderController.php`.

**Regole operative**
- controller/page = orchestrazione;
- service/composable = logica;
- formatter/mapper = conversione;
- validator/request = regole.

**Criterio di completamento**
- i file core non sono piu' il solo posto dove avviene tutto;
- una persona nuova riesce a leggere una parte del flusso senza aprire 10 funzioni laterali immediate.

---

### Fase 4 — Utility condivise e standard cross-cutting

**Obiettivo**  
Rimuovere le duplicazioni semplici ma pervasive che aumentano il rumore.

**Interventi**
- estrarre formatter condivisi (`formatPrice`, icone collo, date, stato ordine);
- estrarre wallet movement service;
- estrarre pattern HTTP client BRT;
- uniformare naming composables e file admin;
- standardizzare notifiche, errori e feedback UI dove possibile.

**Beneficio**  
Questa fase non risolve i bug piu' grossi, ma abbassa molto il costo mentale quotidiano.

**Criterio di completamento**
- i pattern ripetuti 4-6 volte non esistono piu' come copia-incolla.

---

### Fase 5 — PUDO/BRT come sottosistema separato e leggibile

**Obiettivo**  
Trasformare PUDO/BRT da area "densa di eccezioni" a sottosistema leggibile con confini chiari.

**Interventi**
- separare ricerca punti, geografia, distanza, mappa, BRT shipment e tracking;
- chiarire provider/source/fallback strategy;
- distinguere completamente:
  - UX mappa e lista
  - ricerca punti
  - create shipment BRT
  - tracking
  - pickup/bordero/documenti

**Criterio di completamento**
- una modifica alla mappa non costringe a capire tutto il payload BRT;
- una modifica BRT non costringe a toccare la UI PUDO.

---

### Fase 6 — Documentazione finale e onboarding stabilizzato

**Obiettivo**  
Portare docs e codice allo stesso livello di verita'.

**Interventi**
- aggiornare `MAPPA-FLUSSI.md`, `MAPPA-DATI.md`, `MODULI.md`;
- mantenere il kit onboarding come porta di ingresso ufficiale;
- introdurre checklist permanente per nuovi moduli e nuovi refactor.

**Criterio di completamento**
- un nuovo sviluppatore puo' capire il progetto leggendo audit, standard, kit onboarding e i documenti architetturali principali.

---

## 4. Ordine consigliato dei sottosistemi

Ordine pratico consigliato dentro la roadmap:

1. pricing
2. contratti dati/cart/order/session
3. wizard/riepilogo/checkout
4. BRT/PUDO
5. admin runtime/preview e utility condivise
6. documentazione finale

---

## 5. Cosa non fare

Per mantenere il progetto stabile, questa roadmap esclude esplicitamente:

- riscrivere grandi blocchi frontend in una volta sola;
- cambiare UI e architettura nello stesso passaggio se non necessario;
- spostare tutto su nuove cartelle senza test e mappa dei riferimenti;
- introdurre nuove astrazioni solo per gusto teorico.

Ogni estrazione deve avere un motivo concreto: meno duplicazione, meno ambiguita', meno accoppiamento.

---

## 6. Definizione di successo del programma di refactor

Avremo davvero migliorato il progetto quando:

- chi entra nel repo capisce subito dove vive la logica di prezzo;
- i file chiave smettono di essere monoliti difficili da toccare;
- le unita' denaro sono evidenti dal nome;
- preview admin e runtime reale condividono le stesse strutture dove serve parita';
- docs e codice non si contraddicono sui flussi principali;
- ogni nuovo bug e' piu' facile da localizzare per modulo, non per ricerca globale caotica.
