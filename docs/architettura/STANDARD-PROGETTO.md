# Standard di Progetto — SpediamoFacile

Data: 2026-03-20  
Scopo: regole comuni per mantenere il codice ordinato, leggibile e coerente

---

## 1. Principio guida

Il codice di SpediamoFacile deve essere:

- leggibile da chi entra per la prima volta;
- coerente tra frontend e backend;
- esplicito nelle responsabilita';
- prudente con soldi, stato ordine e integrazioni esterne;
- documentato in modo utile, non verboso.

Se una scelta rende il codice piu' "furbo" ma meno leggibile, la scelta giusta e' quasi sempre la piu' leggibile.

---

## 2. Regole di naming

## 2.1 Frontend

- file Vue/pages/components/composables in `camelCase` o convenzione gia' dominante del framework;
- composables sempre con prefisso `use`:
  - corretto: `useCart.js`
  - da evitare: `UseAdminImage.js`
- variabili, computed e funzioni in `camelCase`.

## 2.2 Backend

- classi PHP in `PascalCase`;
- campi DB e payload Laravel in `snake_case`;
- costanti descrittive, preferibilmente legate al dominio.

## 2.3 Nome e unita' dati

Per valori monetari, di peso o volume il nome deve dichiarare l'unita'.

### Denaro

Usare uno di questi pattern:

- `amount_cents`
- `price_cents`
- `total_cents`
- `price_eur` solo se davvero e' in euro float/string

Da evitare:

- `single_price` se in alcuni punti e' in euro e in altri in centesimi;
- `subtotal` senza contesto unita' se usato sia come money object sia come integer.

### Misure e peso

- peso: `weight_kg` se il dato e' gia' convertito e non ambiguo;
- volume: `volume_m3` se il valore e' espresso in metri cubi;
- misure lineari: `length_cm`, `width_cm`, `height_cm`.

Se il DB usa nomi storici diversi, introdurre mapper/documentazione esplicita e non aggiungere nuovi casi ambigui.

---

## 3. Regole di responsabilita' per modulo

## 3.1 Pages e Controller

**Ruolo corretto:** orchestrare il flusso.  
Non devono essere il posto dove si concentrano tutte le regole.

Devono fare soprattutto:

- validare input o delegare a request/validator;
- chiamare servizi/composables/helpers;
- comporre risposta o vista finale.

Non devono fare direttamente:

- business logic complessa;
- conversioni sparse replicate;
- formattazioni da riusare altrove;
- calcoli economici critici duplicati.

## 3.2 Services e Composables

**Ruolo corretto:** contenere logica riusabile e coerente.

- `Service` backend: regole di dominio, integrazioni esterne, use case specifici.
- `Composable` frontend: logica riusabile di stato/IO/formatting lato interfaccia.

Ogni service/composable deve avere uno scopo chiaro, non diventare un "cassetto miscela".

## 3.3 Resources, Mappers, Formatters

Ogni conversione ricorrente deve stare in un punto noto.

Esempi da centralizzare:

- `serviceData -> service_data`
- `formatPrice`
- mapping tipo collo -> icona
- mapping stato ordine -> label/colore
- normalizzazione payload BRT

## 3.4 Requests e Validator

Le regole di validazione devono stare:

- nel backend in `FormRequest` o validator dedicati;
- nel frontend in composables/helper di validazione riusabili quando servono feedback anticipati.

La stessa regola non deve vivere in 4 posti diversi con testi divergenti.

---

## 4. Sorgente di verita'

Per ogni flusso critico va dichiarata una sola sorgente di verita'.

Checklist minima da rispettare in ogni modulo:

- qual e' il dato sorgente?
- quali copie locali esistono?
- quando si sincronizzano?
- chi puo' sovrascrivere chi?
- dove avviene il ricalcolo ufficiale?

### Esempio corretto

- pricing finale: backend `PriceEngineService`
- frontend: preview e display coerente con dati pubblici, non motore alternativo nascosto

### Esempio da evitare

- store frontend con prezzo A;
- sessione server con prezzo B;
- order creation con prezzo C;
- riepilogo che ricombina tutto con fallback D.

---

## 5. Regole per la dimensione dei file

Non esiste una soglia magica, ma questi segnali devono far partire un refactor:

- file Vue o controller con piu' di ~250-300 righe di logica reale;
- metodo singolo oltre ~50-60 righe se contiene piu' blocchi di responsabilita';
- presenza di commenti che separano artificialmente 5 mini-moduli nello stesso file;
- necessita' di scroll continuo per capire un solo flusso.

Quando un file supera queste soglie, la regola non e' "accettarlo perche' funziona": la regola e' spezzarlo in unita' leggibili.

---

## 6. Commenti e documentazione inline

## 6.1 Commenti buoni

Un commento e' buono se spiega almeno una di queste cose:

- perche' il codice esiste;
- quale vincolo esterno lo impone;
- quale bug/rischio evita;
- quale formato o assunzione non e' ovvia.

## 6.2 Commenti da evitare

Da limitare:

- commenti che ripetono solo il nome del metodo;
- commenti che descrivono una query ovvia senza spiegare il motivo;
- commenti troppo vecchi non piu' coerenti col codice reale.

## 6.3 Intestazioni file

Le intestazioni descrittive gia' presenti sono utili. Vanno mantenute con questo schema minimo:

- scopo del file
- input principali
- output principali
- chiamato da / collegamenti
- effetti collaterali importanti
- errori o rischi tipici

---

## 7. UI state e preview parity

Quando una preview admin deve essere identica al runtime reale:

- deve condividere lo stesso markup o lo stesso view model centrale;
- non deve replicare una seconda implementazione "simile";
- le trasformazioni immagine/layout devono essere applicate allo stesso layer semantico.

Questa regola vale soprattutto per:

- hero homepage
- preview immagini admin
- componenti che mostrano lo stesso contenuto in desktop/mobile simulato

---

## 8. Test minimi per area critica

## Pricing
- casi standard
- casi extra peso/volume
- supplementi CAP
- parita' frontend/backend

## Checkout
- creazione ordine
- payment intent
- pagamento riuscito/fallito
- ordine gia' pagato

## BRT/PUDO
- create shipment
- tracking
- fallback PUDO
- ricerca con citta' piccole

## Hero/Admin preview
- parita' runtime/preview
- zoom/offset
- mobile/desktop

---

## 9. Regola anti-duplicazione

Se una logica appare in 3 punti o piu', va aperta una valutazione immediata:

- e' davvero locale?
- oppure e' una utility mancata?
- oppure e' un mapper che doveva essere condiviso?
- oppure e' un segnale che manca un modulo dedicato?

---

## 10. Repository hygiene

Il root della repo deve restare leggibile a colpo d'occhio.

Regole obbligatorie:

- nessun report o screenshot sciolto nel root;
- reference vendor e materiali esterni solo in `docs/riferimento/vendor/`;
- documenti storici o superati solo in `docs/_archivio/`;
- backup solo in `_backup/`;
- output locali solo in `output/` o `reports/runtime/`.

Se un file non serve al runtime, all'ingresso del progetto o alla documentazione viva, va ricollocato fuori dal root.

---

## 11. Invarianti di business da non rompere

Queste regole non sono opzionali e valgono anche durante i refactor:

- mai committare segreti o credenziali reali;
- quando una modifica attraversa un flusso completo, aggiornare insieme frontend e backend;
- il portafoglio deve restare idempotente: nessuna duplicazione dei movimenti;
- il saldo del portafoglio deve derivare solo dai movimenti confermati;
- il prezzo finale ufficiale deve avere una sola sorgente di verita' dichiarata.

La duplicazione ammessa deve essere intenzionale, breve e spiegata.

---

## 10. Regole documentali

Ogni documento architetturale chiave deve avere:

- data ultimo audit o aggiornamento;
- scopo chiaro;
- riferimenti ai file veri;
- stato sync, se non pienamente allineato.

Gli indici documentali devono puntare a un percorso di lettura reale, non solo a una lista completa.

### Root hygiene

Il root della repo non e' una scrivania temporanea.

Regole obbligatorie:

- report investigativi e changelog locali vanno in `reports/legacy/`
- dump runtime e output di diagnostica vanno in `reports/runtime/`
- screenshot e artefatti locali vanno in `output/`
- documenti storici non piu' canonici vanno in `docs/_archivio/`
- nuove decisioni strutturali vanno registrate in `docs/adr/ADR-LOG.md`

Ogni file nuovo deve avere subito una categoria chiara. Se non esiste una categoria, va definita prima di introdurre altro rumore nel root.

---

## 11. Checklist prima di aprire un refactor

1. sto toccando una sola sorgente di verita' o ne esistono altre nascoste?
2. il nome dei dati dice chiaramente unita' e ruolo?
3. sto mettendo logica in un controller/page invece che in un service/composable?
4. esiste gia' una utility che fa la stessa cosa?
5. chi arriva dopo di me capira' il flusso senza leggere mezzo progetto?
6. la documentazione collegata restera' vera dopo la modifica?

Se la risposta a 2 o piu' domande e' "no", il refactor va ripensato prima di procedere.
