# Audit Master — Architettura, Leggibilità e Refactor di SpediamoFacile

Data audit: 2026-03-21  
Stato: fotografia completa del sistema prima del refactor  
Scope: intero progetto `nuxt-spedizionefacile-master` + `laravel-spedizionefacile-main`

---

## 1. Obiettivo di questo audit

Questo documento fotografa il progetto nello stato attuale con una priorita' chiara:

- rendere il codice piu' ordinato, leggibile e spiegabile a una persona nuova;
- ridurre i punti in cui la stessa logica esiste in piu' posti;
- isolare i moduli cosi' che bug, fix e nuove feature siano piu' facili da gestire;
- preparare una roadmap di refactor sicura, senza interventi "big bang".

Questo audit **non cambia contratti pubblici o API**. Segna dove sono i problemi, perche' pesano, e come intervenire in modo ordinato.

---

## 2. Metodo usato

L'audit e' stato costruito con questa procedura:

1. rilettura del diario squadra e dei documenti architetturali gia' presenti;
2. inventario del repository e delle dipendenze principali frontend/backend;
3. confronto tra documentazione attuale e codice reale;
4. raccolta di problemi per categoria:
   - comprensibilita'
   - stabilita'
   - sicurezza
   - performance
   - drift documentazione/codice
5. trasformazione dei problemi in una roadmap di refactor eseguibile per fasi.

Documenti base usati come riferimento:

- [Moduli](MODULI.md)
- [Mappa flussi](MAPPA-FLUSSI.md)
- [Mappa dati](MAPPA-DATI.md)
- [Audit leggibilita'](AUDIT-LEGGIBILITA.md)
- [Code Review: Opportunita' di Riuso Codice](../../reports/legacy/CODE_REVIEW_REUSE_OPPORTUNITIES.md)
- [Come leggere il codice](../impara/COME-LEGGERE-IL-CODICE.md)

---

## 3. Executive summary

### Valutazione sintetica

Il progetto ha una base forte e gia' piu' documentata della media. I punti buoni sono reali:

- backend Laravel e frontend Nuxt sono separati in modo leggibile;
- molte classi e file hanno intestazioni utili;
- esistono gia' documenti per onboarding, glossari e mappe dei flussi;
- alcune aree mostrano gia' un buon livello di disciplina tecnica (eventi, resources, usePriceBands, test di caratterizzazione).

Il problema non e' la mancanza totale di struttura. Il problema e' che il progetto e' cresciuto per successive aggiunte e oggi soffre in 5 aree chiave:

1. **sorgenti di verita' duplicate**, soprattutto sul pricing;
2. **file troppo grandi e multi-responsabilita'**, soprattutto nei controller critici e nei componenti wizard;
3. **naming e unita' dati incoerenti** tra frontend e backend;
4. **drift tra documentazione e codice reale**;
5. **utility e pattern ripetuti** che dovrebbero essere condivisi.

### Giudizio architetturale

- Stabilita' potenziale: buona
- Manutenibilita' attuale: media
- Leggibilita' per chi entra da zero: media-bassa
- Documentazione esistente: buona ma parzialmente disallineata
- Priorita' assoluta: unificare le sorgenti di verita' e spezzare i file troppo centrali

---

## 4. Fotografia completa del sistema

### 4.1 Moduli principali e confini reali

| Modulo | Responsabilita' reale | Input principali | Output principali | Sorgente di verita' attuale | Debito attuale |
|--------|------------------------|------------------|-------------------|-----------------------------|----------------|
| Homepage + Hero | ingresso brand, preventivo rapido, sezione marketing | config hero, price bands, stato auth/carrello | impression iniziale, invio step 1 | frontend Nuxt + config admin immagine | preview/admin e hero reale non sempre allineate |
| Preventivo rapido | raccoglie tratta, colli, peso/volume e calcola il primo totale | form utente, bands pubbliche, sessione | sessione step 1, store utente, totale | divisa tra frontend, sessione backend e store | rischio drift tra frontend/backend sul prezzo |
| Wizard spedizione | completa colli, servizi, indirizzi, PUDO, riepilogo | store Pinia, sessione, API locations/PUDO | ordine pronto per carrello/checkout | mix tra page state, store, sessione | file gigante e flusso difficile da seguire |
| Carrello | conserva pacchi/ordini pronti al checkout | utente auth/guest, package/service/address | elenco pacchi, subtotal, merge pacchi | DB per auth, sessione per guest | duplicazione tra guest e auth |
| Checkout/Pagamento | crea ordini, PaymentIntent, transazioni e pagamento wallet/bonifico | carrello, coupon, wallet, Stripe | ordine, transazione, eventi post-pagamento | backend Laravel | controller con troppe responsabilita' |
| BRT / PUDO / tracking | spedizione reale, etichetta, tracking, pickup, bordero, PUDO | ordine pagato, indirizzi, pacchi, opzioni servizio | etichetta, tracking, stato spedizione | backend services + order fields | BrtService concentra troppa logica e casi speciali |
| Account / Admin | profilo, carte, wallet, ordini, admin panels, editor hero | utente, settings, ordini, storage | configurazioni, gestione utente, preview | backend settings + pagine Nuxt | pannelli admin eterogenei e convenzioni non uniche |
| Pricing | bands nazionali, regole extra, supplementi CAP | DB/settings, API pubblica, frontend | prezzi finali, preview admin, calcoli ordine | non ancora pienamente unica | area piu' critica del progetto |
| Documentazione | onboarding, guide, mappe, glossari | codice reale, decisioni di team | documenti tecnici e didattici | repository docs | parte dei documenti non segue il codice attuale |

### 4.2 Flussi chiave

I flussi chiave reali da considerare come "spina dorsale" del progetto sono:

1. homepage -> preventivo rapido -> sessione step 1;
2. wizard spedizione -> riepilogo -> carrello;
3. carrello -> checkout -> ordine -> pagamento;
4. pagamento riuscito -> evento `OrderPaid` -> BRT -> tracking/documenti;
5. account/admin -> gestione immagini, utenti, prezzi, ordini, servizi;
6. PUDO/BRT -> ricerca punti -> selezione -> propagazione su wizard e riepilogo.

### 4.3 Dipendenze trasversali piu' pesanti

Le dipendenze che oggi attraversano troppi moduli sono:

- **pricing**: tocca homepage, preventivo, wizard, carrello, checkout, ordine e admin;
- **package/service/address shape**: gli stessi concetti cambiano forma tra store, payload frontend, request backend, resources e DB;
- **BRT/PUDO**: impatta wizard, riepilogo, ordine, listener post-pagamento e tracking;
- **auth guest vs auth loggato**: molte logiche esistono in doppia versione;
- **hero/admin image config**: stessa area visuale usata in piu' contesti con vincolo di parita' reale.

### 4.4 Baseline iniziale delle sorgenti di verita'

Questa tabella non risolve ancora i duplicati, ma fissa il punto di partenza reale per i refactor successivi.

| Flusso | Sorgente di verita' che deve vincere | Copie o derivati oggi presenti | Rischio attuale | Prima azione di refactor |
|--------|--------------------------------------|--------------------------------|-----------------|--------------------------|
| pricing preventivo e ordine | `PriceEngineService` backend | `usePriceBands.js`, sessione, store frontend, riepiloghi con fallback | alto | fare del backend il motore ufficiale e ridurre il frontend a preview coerente |
| wizard spedizione | sessione backend + store esplicitamente sincronizzato | stato locale pagina, query, calcoli derivati | alto | dichiarare ownership per step e sincronizzazione |
| checkout e totale carrello | backend ordine/carrello | riepiloghi frontend, subtotal derivati | medio-alto | unificare shape money e totali |
| PUDO reference point e risultati mappa/lista | stato PUDO dedicato e normalizzato | page state, props, marker state, response API | alto | separare reference point, risultati, selezione e render |
| auth utente corrente | sessione/auth backend + bootstrap frontend | store locale, fetch tardivi, branch guest/loggato | medio | consolidare boundary auth e fallback guest |
| hero config e preview admin | settings backend + stessa struttura view del runtime | preview separata, viewport editor, fallback immagine | medio | tenere preview e runtime sullo stesso view model |

### 4.5 Cartelle nascoste, tooling locale e duplicati

La ricognizione del workspace ha mostrato che il disordine non stava solo nel codice o nel root visibile. C'erano anche copie quasi complete della repo e log locali accumulati in cartelle nascoste.

Decisioni operative gia' validate:

- `.claude/worktrees/ui-hero-editorial-overlap` resta come **unica worktree attiva locale** perche' contiene commit unici e una modifica non ancora consolidata;
- `.worktrees/ui-hero-editorial-overlap` e' stata rimossa perche' duplicata, pulita e senza lavoro unico;
- `.claude/worktrees/clever-bhaskara` e' stata archiviata in `_backup/` perche' era una worktree rotta/stale, ma con branch ancora preservato;
- i log storici di `.playwright-mcp/`, `_LOGS/` e `tmp-diagnostica/` sono stati tolti dal root e archiviati sotto `reports/runtime/legacy-logs/`;
- `_LOG/` resta l'unica cartella log canonica nel root, limitata ai log operativi correnti;
- i file di stato `URL_ONLINE.txt`, `_PORTS.json` e `_STATE.json` restano canonici perche' usati dai launcher e dal pannello.

Conclusione architetturale: le cartelle nascoste e di tooling vanno trattate come parte del sistema da governare, ma **non** come sorgente di verita' del prodotto.

### 4.6 Stato operativo locale e policy dei file di supporto

La ricognizione ha chiarito anche quali file locali devono restare nel root e quali no.

Elementi che restano canonici nel root perche' fanno parte del flusso operativo umano:

- `URL_ONLINE.txt`
- `_PORTS.json`
- `_STATE.json`
- `_LOG/` limitato ai log live di Nuxt, Laravel, Caddy e Cloudflare

Elementi che **non** devono piu' ricrescere nel root:

- cartelle diagnostiche ad hoc come `tmp-diagnostica/`
- family worktree duplicate come `.worktrees/`
- log tooling come `.playwright-mcp/`
- cartelle log legacy come `_LOGS/`

Policy architetturale fissata:

- report diagnostici correnti -> `reports/runtime/diagnostica/`
- log storici e dump locali -> `reports/runtime/legacy-logs/`
- una sola family di worktree attive -> `.claude/worktrees/` se davvero necessaria
- nessun file o cartella locale "misteriosa" al root senza classificazione esplicita

---

## 5. Punti forti da preservare

Prima dei problemi, e' importante segnare cosa vale la pena conservare:

1. **Separa bene frontend e backend**: la divisione Nuxt/Laravel e' chiara e resta una buona scelta.
2. **Uso di Resources e Requests nel backend**: e' una base corretta per stabilizzare contratti e validazione.
3. **Commenti introduttivi in molti file**: sono gia' utili per onboarding e vanno mantenuti, ma resi piu' omogenei.
4. **Documentazione gia' presente**: il progetto ha gia' un patrimonio utile, quindi il lavoro giusto e' sincronizzare e non riscrivere tutto da zero.
5. **Test di caratterizzazione gia' introdotti in alcune aree**: ottimo punto di partenza per refactor sicuri.

---

## 6. Findings principali per severita'

## 6.1 Critici

### C1. Pricing con sorgenti di verita' duplicate e divergenti

**Categoria:** stabilita' + comprensibilita'  
**Severita':** CRITICO

Il prezzo non nasce in un solo punto. Oggi il progetto contiene logica prezzo in piu' aree:

- `SessionController.php`
- `OrderController.php`
- `usePriceBands.js`
- endpoint pubblici/admin price bands
- parti del checkout/riepilogo che fanno fallback locali

Questo genera due problemi grossi:

1. prezzi potenzialmente diversi per lo stesso caso a seconda del percorso;
2. impossibilita' pratica di capire "chi decide davvero il prezzo" senza inseguire il codice in piu' file.

**Impatto:** bug economici, regressioni difficili da scovare, refactor costosi.  
**Regola proposta:** una sola sorgente di verita' backend per il calcolo, frontend solo come preview coerente.

### C2. File centrali troppo grandi e multi-responsabilita'

**Categoria:** comprensibilita' + stabilita'  
**Severita':** CRITICO

Alcuni file sono diventati "hub" con troppe responsabilita':

- `nuxt-spedizionefacile-master/components/Preventivo.vue`
- `nuxt-spedizionefacile-master/pages/la-tua-spedizione/[step].vue`
- `laravel-spedizionefacile-main/app/Services/BrtService.php`
- `laravel-spedizionefacile-main/app/Http/Controllers/StripeController.php`
- `laravel-spedizionefacile-main/app/Http/Controllers/CartController.php`
- `laravel-spedizionefacile-main/app/Http/Controllers/OrderController.php`

Quando un file contiene orchestration, validazione, conversioni unita', fallback, UI state e casi speciali nello stesso posto, succedono 3 cose:

- leggere il file diventa lento e costoso;
- ogni bugfix porta rischio di regressione laterale;
- anche una persona esperta fatica a capire il confine della responsabilita'.

**Regola proposta:** page/controller orchestrano; composable/service eseguono logica; formatter/mapper convertono; validator valida.

### C3. Drift documentazione/codice nei flussi principali

**Categoria:** drift docs/codice  
**Severita':** CRITICO

Documenti importanti esistono, ma non sempre descrivono piu' il sistema reale. Esempi:

- [Mappa flussi](MAPPA-FLUSSI.md) descrive fasce prezzo e sequenze ormai superate in piu' punti;
- [Mappa dati](MAPPA-DATI.md) non riflette sempre i campi e i significati piu' recenti di pricing, execution state, servizi e admin config;
- le mappe non segnano sempre i nuovi confini tra settings, preview hero, PUDO e nuovi campi ordine.

**Impatto:** chi legge i documenti per imparare il progetto impara cose corrette "a meta'".  
**Regola proposta:** i documenti architetturali chiave devono avere owner, data ultimo audit e stato sync esplicito.

### C4. Stato frontend distribuito senza ownership esplicita

**Categoria:** stabilita' + leggibilita'  
**Severita':** CRITICO

Nel frontend lo stato puo' esistere in piu' luoghi contemporaneamente:

- Pinia (`userStore`)
- sessione server
- state locale del componente/pagina
- query params
- risposta API fresca

Il progetto funziona, ma in piu' aree non e' dichiarato in modo esplicito quale sia la sorgente di verita' finale.

**Esempio classico:** preventivo/wizard/riepilogo usano contemporaneamente store, sessione e ricalcoli.  
**Regola proposta:** ogni flusso deve dichiarare chiaramente:

- stato sorgente;
- stato cache locale;
- stato derivato;
- momento in cui una fonte sovrascrive l'altra.

### C5. Hotspot operativi con responsabilita' troppo dense

**Categoria:** comprensibilita' + stabilita'  
**Severita':** CRITICO

La ricognizione diretta dei file piu' grandi conferma che i problemi non sono solo "dimensione", ma soprattutto accoppiamento di responsabilita'.

#### `pages/la-tua-spedizione/[step].vue`

Responsabilita' oggi mescolate nello stesso file:
- selezione servizi
- calendario/data ritiro
- indirizzi origine/destinazione
- suggerimenti smart e validazioni
- autocompletamento citta'/provincia/CAP
- modalita' PUDO, mappa e selezione punto
- caricamento item in modifica da carrello
- riepilogo sticky e step observer
- submit e routing finale

Rischio: ogni bugfix UI o di validazione puo' toccare anche logica dati, sincronizzazione sessione e PUDO.

Direzione di split consigliata:
- composable `useShipmentWizardFlow`
- componenti step-specifici per servizi, indirizzi e riepilogo sticky
- modulo PUDO separato da vista indirizzi generica
- helper dedicati per validazione e suggestion logic

Avanzamento reale gia' fatto:
- gli helper puri di ricerca localita'/CAP/province e le chiamate base `/api/locations/*` sono stati estratti nel composable condiviso `composables/useLocationSearch.js`;
- il file resta comunque un hotspot critico perche' orchestration, validazione, PUDO e riepilogo sticky sono ancora mescolati.

#### `components/Preventivo.vue`

Responsabilita' oggi mescolate:
- layout preventivo homepage e variant preview
- autocomplete localita'
- package typing e visual state
- validazione smart
- calcolo preview prezzo
- persistenza sessione e routing step successivo

Rischio: il componente diventa contemporaneamente vista, motore di input, format layer e orchestratore sessione.

Direzione di split consigliata:
- vista hero/preventivo
- composable `usePreventivoForm`
- helper condivisi per localita', colli e formattazione
- price preview ridotta a solo derivato coerente con backend

Avanzamento reale gia' fatto:
- la suggestion logic di base per localita' e CAP non vive piu' solo qui: e' stata agganciata al composable condiviso `composables/useLocationSearch.js`;
- resta da estrarre il nucleo `usePreventivoForm` e il blocco prezzo/sessione.

#### `app/Services/BrtService.php`

Responsabilita' oggi mescolate:
- creazione spedizione
- conferma/cancellazione
- mapping servizi
- normalizzazione indirizzi
- geocoding
- ricerca PUDO multipass
- fallback DB locale
- ordinamento e distanza
- traduzione errori provider

Rischio: un singolo service diventa contemporaneamente client provider, orchestratore business, normalizzatore, fallback engine e utility geografica.

Direzione di split consigliata:
- `BrtShipmentService`
- `BrtPudoSearchService`
- `BrtAddressNormalizer`
- `BrtErrorMapper`
- helper geografici dedicati e testabili

## 6.2 Importanti

### I1. Naming uguale con unita' diverse

**Categoria:** comprensibilita'  
**Severita':** IMPORTANTE

Esempio tipico:

- `single_price`
- `weight_price`
- `volume_price`

Lo stesso nome viene usato in punti diversi con unita' diverse (euro vs centesimi). Questo da solo basta a generare bug e incomprensioni.

**Regola proposta:** nei nomi dove il valore e' denaro va dichiarata l'unita':

- `*_cents`
- `*_eur`
- `Money` object o formatter condiviso

### I2. Utility duplicate in frontend e backend

**Categoria:** manutenibilita'  
**Severita':** IMPORTANTE

Duplicazioni gia' visibili:

- `formatPrice()` in piu' pagine Vue;
- mapping icone/tipi collo ripetuto in piu' pagine;
- blocchi `serviceData -> service_data` ripetuti nei controller;
- creazione `WalletMovement::create([...])` ripetuta in piu' controller;
- pattern HTTP client ripetuto in `BrtService`.

**Regola proposta:** ogni pattern ripetuto 3 volte va valutato per estrazione.

### I3. Preview/admin e runtime reale non sempre speculari

**Categoria:** stabilita' + UI architecture  
**Severita':** IMPORTANTE

La hero e il suo editor admin mostrano il rischio classico del progetto: la preview nasce per rappresentare il runtime reale, ma se i due layout divergono anche di poco si creano bug percepiti immediatamente dal cliente.

**Regola proposta:** preview e runtime devono condividere la stessa struttura, non due implementazioni parallele che "si somigliano".

### I4. Guest/auth duplicano intere linee di codice

**Categoria:** leggibilita' + rischio regressione  
**Severita':** IMPORTANTE

Carrello guest e carrello auth sono separati in modo sensato a livello storage, ma parte della logica applicativa viene risolta con doppi rami frontend/backend invece che con adapter chiari.

**Regola proposta:** mantenere la differenza di persistenza, ma unificare i contratti e gli helper condivisi.

### I5. PUDO/BRT e' un modulo troppo denso di eccezioni

**Categoria:** stabilita'  
**Severita':** IMPORTANTE

La parte PUDO/BRT contiene:

- geocoding e reverse geocoding;
- ricerca multi-pass;
- fallback DB;
- distanza;
- stati apertura/chiusura;
- sincronizzazione lista/mappa;
- creazione spedizione BRT vera;
- tracking e documenti.

Tutto questo oggi si concentra soprattutto in pochi file ad alto rischio.  
**Regola proposta:** spezzare in sottoservizi chiari: ricerca punti, geografia, payload BRT, tracking, document dispatch.

## 6.3 Consigliati

### S1. Commenti troppo descrittivi del "cosa"

Molti commenti sono utili, ma una parte di essi spiega solo cio' che il codice gia' dice. Serve spostare il baricentro verso:

- perche' questa scelta esiste;
- vincolo esterno;
- rischio se si cambia;
- esempio di input/output.

### S2. Incoerenze minori di naming file

`UseAdminImage.js` e' l'esempio piu' evidente. Non e' il problema principale del progetto, ma segnala mancanza di standard rigido.

### S3. Indici documentali ricchi ma non prioritizzati

Oggi ci sono piu' indici e piu' mappe. Sono utili, ma una persona nuova puo' non capire da dove partire davvero. Serve una via di accesso unica: "leggi questi 5 file nell'ordine giusto".

---

## 7. Review per area

## 7.1 Homepage + Hero + Preventivo rapido

### Inventario rapido

- `components/ContenutoHeader.vue`
- `components/Preventivo.vue`
- `components/Steps.vue`
- `pages/index.vue`
- `pages/preview/home-hero.vue`
- `pages/account/amministrazione/immagine-homepage.vue`

### Problema centrale

UI e logica di preview si toccano molto. La hero non e' solo grafica: dipende da bands prezzo, immagine admin, stato viewport e bridge con preventivo.

### Rischio

Se si fa un cambio visuale senza boundary chiaro, si rompe:

- runtime homepage;
- preview admin;
- mobile;
- hydration.

### Refactor minimo sicuro

- isolare un "hero runtime model" unico;
- usare preview e home con lo stesso layer di layout e transform;
- separare configurazione immagine da composizione hero.

## 7.2 Wizard spedizione + riepilogo + checkout

### Inventario rapido

- `pages/la-tua-spedizione/[step].vue`
- `pages/riepilogo.vue`
- `pages/checkout.vue`
- `stores/userStore.js`
- `composables/useSession.js`
- `composables/useCart.js`

### Problema centrale

Questa e' la parte piu' "business critical", ma anche la piu' complessa. E' il flusso dove piu' spesso convivono store, sessione, API, validazioni, UI state e casi PUDO.

### Rischio

Un bug qui tocca conversione, prezzo, ordine, UX e completamento del checkout.

### Refactor minimo sicuro

- dichiarare la sorgente di verita' per ogni step;
- separare summary/view model dalla logica di validazione;
- estrarre helpers per colli, servizi, indirizzi, error summary, wizard navigation.

## 7.3 Pricing

### Inventario rapido

- `app/Http/Controllers/SessionController.php`
- `app/Http/Controllers/OrderController.php`
- `app/Http/Controllers/PublicPriceBandController.php`
- `app/Services/PriceEngineService.php`
- `composables/usePriceBands.js`
- pannelli admin prezzi

### Problema centrale

Il pricing e' la zona con il piu' alto valore economico e la piu' alta possibilita' di drift.

### Refactor minimo sicuro

- `PriceEngineService` come unico motore;
- frontend usa bands e preview, non inventa regole proprie;
- ogni valore denaro nominato con unita' esplicita.

## 7.4 BRT / PUDO / tracking

### Inventario rapido

- `app/Services/BrtService.php`
- `app/Http/Controllers/BrtController.php`
- `components/PudoSelector.vue`
- `components/MapPudo.client.vue`
- listener post-pagamento

### Problema centrale

Lo stesso modulo deve tenere insieme vincoli BRT esterni, UX mappa, ricerca punti, fallback e spedizione reale.

### Refactor minimo sicuro

- spezzare la geografia e la ricerca PUDO dalla spedizione BRT vera;
- introdurre mappers espliciti input/output;
- isolare i fallback come strategia, non come blocchi sparsi.

## 7.5 Auth / account / admin

### Inventario rapido

- `pages/autenticazione.vue`, `registrazione.vue`, `account/*`
- controller auth e profile in Laravel
- Sanctum bootstrap/plugin
- settings/admin panels

### Problema centrale

Molte schermate admin e account funzionano, ma non hanno sempre una convenzione uniforme su:

- layout pannelli;
- naming composables/admin utilities;
- distinzione draft/published;
- handling guest vs auth.

### Refactor minimo sicuro

- definire standard admin UI/data flow;
- uniformare pannelli settings/preview/upload;
- dichiarare i pattern standard auth-required vs guest-safe.

---

## 8. Drift documentazione/codice da correggere per primo

| Documento | Stato reale | Problema | Azione |
|-----------|-------------|----------|--------|
| `docs/architettura/MAPPA-FLUSSI.md` | parzialmente obsoleto | descrive prezzo e alcune sequenze non piu' allineate | aggiornarlo dopo il refactor pricing |
| `docs/architettura/MAPPA-DATI.md` | parzialmente obsoleto | alcuni campi e significati non coprono le ultime evoluzioni | riallineare per moduli, non per elenco statico puro |
| `docs/architettura/MODULI.md` | utile ma semplificato | i moduli esistono, ma alcuni confini reali oggi sono piu' porosi | aggiungere ownership e source of truth |
| `docs/INDICE*.md` | validi | non evidenziano il percorso giusto per onboarding + audit | aggiungere sezione audit/refactor |

---

## 9. Regole guida che emergono dall'audit

1. **Una sola sorgente di verita' per ogni dato critico**.
2. **I nomi devono dichiarare unita' e ruolo**.
3. **Page e controller orchestrano, non fanno tutto**.
4. **Ogni conversione frontend/backend deve vivere in un mapper noto**.
5. **Una preview che deve essere identica al runtime non puo' avere markup divergente**.
6. **Ogni documento architetturale chiave deve avere stato sync e data ultimo audit**.
7. **Se una logica appare in tre punti, non e' piu' un caso locale: e' un modulo mancato**.

---

## 10. Criteri di successo del refactor futuro

Il refactor avra' successo quando:

- il pricing nasce da un solo motore e frontend/backend coincidono;
- il wizard spedizione e il PUDO sono spezzati in unita' leggibili;
- un nuovo sviluppatore capisce i flussi chiave leggendo 5 documenti, non 50 file a caso;
- i documenti principali descrivono davvero il codice attuale;
- i file piu' critici scendono sotto soglie di complessita' ragionevoli;
- i pattern ripetuti (price formatting, wallet movements, payload normalizations, BRT request setup) sono centralizzati.

---

## 11. Collegamenti ai deliverable di questa fase

- [Roadmap refactor](ROADMAP-REFACTOR.md)
- [Standard progetto](STANDARD-PROGETTO.md)
- [Matrice priorita'](MATRICE-PRIORITA.md)
- [Kit onboarding](../impara/KIT-ONBOARDING.md)


---

## 7. Root hygiene e struttura fisica della repo

L'audit sul codice da solo non basta. Anche la **struttura fisica del repository** oggi contribuisce al costo mentale del progetto.

### Problema rilevato
Nel root convivono ancora:
- ingressi canonici del progetto;
- documentazione viva;
- report storici;
- screenshot e dump runtime;
- note operative e file temporanei.

Questo crea ambiguita' immediata per chi entra nella repo.

### Decisione
La repo adotta da ora una tassonomia esplicita:
- `docs/` = documentazione viva
- `reports/` = report storici, debug e analisi narrative
- `output/` = screenshot e artefatti locali
- `docs/_archivio/` = documenti superati ma da conservare

Documenti di supporto introdotti:
- [Root allowlist](ROOT-ALLOWLIST.md)
- [Classificazione del root](ROOT-CLASSIFICAZIONE.md)
- [Stato dei documenti](STATO-DOCUMENTI.md)
- [Dead code ledger](DEAD-CODE-LEDGER.md)
- [ADR log](../adr/ADR-LOG.md)

### Impatto
Questa parte non cambia il runtime dell'app, ma abbassa subito il rumore di repository e rende piu' chiaro dove mettere ogni nuova cosa.

## 8. Nuovi findings prioritari sulla repo

### C5. Root clutter e categorie fisiche non governate

**Categoria:** comprensibilita' + drift organizzativo  
**Severita':** CRITICO

Il root ha accumulato file eterogenei non canonici. Anche quando il codice funziona, questo peggiora:
- onboarding
- ricerca file
- manutenzione
- rischio di documenti usati per errore come fonte ufficiale

**Regola proposta:** allowlist del root e categoria obbligatoria per ogni nuovo file.

### I5. Report e documentazione storica mescolati alla documentazione viva

**Categoria:** drift docs/codice  
**Severita':** IMPORTANTE

Report investigativi, code review narrative e changelog storici non devono stare accanto alla documentazione viva senza distinzione.

**Regola proposta:** spostare il materiale storico in `reports/legacy/` o `docs/_archivio/` e dichiararne lo stato.

### C6. Preview locale canonica non valida ancora i flussi API reali

**Categoria:** stabilita' runtime + quality gate  
**Severita':** CRITICO

Nel collaudo reale del 2026-03-24 il frontend Nuxt su `3001` e' risultato navigabile, ma non realmente integrato con i flussi backend richiesti dal prodotto.

Evidenze raccolte in browser reale:

- homepage visibile, ma `GET /api/public/homepage-image` e `GET /api/public/price-bands` vanno in `404`;
- pagina `preventivo` visibile, ma `GET /api/locations/by-city`, `GET /api/locations/search` e `GET /api/locations/by-cap` vanno in `404`;
- login e continui del preventivo tentano `GET /sanctum/csrf-cookie` e ricevono `404`;
- pagina `carrello` rende la UI, ma `GET /api/guest-cart` va in `404`.

Il problema non e' solo "manca un endpoint": la preview locale canonica oggi non garantisce che frontend, backend e proxy siano nello stesso percorso di collaudo realmente raggiungibile.

Contesto del finding:

- `scripts/avvia-locale.sh` ha alzato Nuxt correttamente su `3001`;
- Caddy non e' installato in questo ambiente, quindi `8787` non e' disponibile;
- il backend Laravel logga `Server running on [http://0.0.0.0:8000]`, ma da questa sessione/browser non risulta raggiungibile su `127.0.0.1:8000`.

**Impatto:** homepage, preventivo, auth, guest cart, checkout e admin non sono collaudabili end-to-end finche' il percorso locale canonico non espone davvero l'API.

**Regola proposta:** l'avvio locale deve fallire presto se `frontend`, `backend` e `proxy` non sono tutti raggiungibili; il giro preview non puo' essere considerato verde con `404` sugli endpoint core.

**Aggiornamento 2026-03-24 sera:** corretto il bug frontend che in sviluppo puro riscriveva l'origine API su `3001`. Dopo il fix, homepage/auth/preventivo puntano correttamente a `8000` quando Caddy non e' presente. Il finding resta aperto come **blocco di reachability runtime** (`ERR_CONNECTION_REFUSED` su `8000` in questa sandbox), non piu' come instradamento frontend errato.

### I6. Flussi auth/guard con redirect e hydration non ancora lineari

**Categoria:** correttezza logica + UX  
**Severita':** IMPORTANTE

Nel giro manuale su preview reale sono emersi due problemi riproducibili legati ai boundary auth:

- sulla pagina `autenticazione`, il link Google genera hydration mismatch tra SSR e client:
  - SSR: `http://127.0.0.1:8787/api/auth/google/redirect?frontend=&redirect=%2Faccount`
  - client: `http://127.0.0.1:3001/api/auth/google/redirect?frontend=http%3A%2F%2F127.0.0.1%3A3001&redirect=%2Faccount`
- accedendo a route protette come `/checkout` o `/account/amministrazione/prezzi`, il link `Account` sulla pagina di login accumula redirect annidati:
  - esempio reale: `/autenticazione?redirect=%2Fautenticazione%3Fredirect%3D%2Faccount%2Famministrazione%2Fprezzi`

**Impatto:** warning runtime, UI meno prevedibile e rischio di percorsi auth che si degradano progressivamente quando l'utente rimbalza tra guard e login.

**Regola proposta:** un solo builder di redirect auth e un solo costruttore di URL OAuth, coerente tra SSR e client.

**Aggiornamento 2026-03-24 sera:** corretti in codice sia il redirect annidato sia il mismatch SSR/client del link Google OAuth. La review manuale successiva ha confermato:
- link `Account` su `/autenticazione?redirect=/checkout` stabile e non annidato;
- link Google coerente su `http://127.0.0.1:8000/api/auth/google/redirect?...frontend=http://127.0.0.1:3001...`.
Il punto va ricollaudato ancora con backend raggiungibile, ma il difetto logico lato frontend risulta chiuso.

### I7. Quality gate frontend dichiarati ma non ancora riproducibili dalla repo

**Categoria:** developer experience + controllo regressioni  
**Severita':** IMPORTANTE

La repo contiene gia' `playwright.config.ts`, `vitest.config.ts` e test E2E/unit, ma lo stato del frontend non e' ancora auto-consistente per lanciare quei gate in modo canonico:

- `package.json` espone solo `build/dev/generate/preview/postinstall`;
- `eslint`, `prettier`, `vitest` e `@playwright/test` non risultano installati come toolchain locale del progetto;
- l'esecuzione ad hoc con `npx` non e' bastata a produrre un giro affidabile e ripetibile.

**Impatto:** il progetto sembra piu' verificato di quanto sia davvero, perche' i test esistono ma non sono ancora parte di un percorso standard di esecuzione.

**Regola proposta:** dichiarare in `package.json` i quality gate realmente supportati e installare la toolchain minima necessaria prima di considerare "chiusa" la validazione frontend.
