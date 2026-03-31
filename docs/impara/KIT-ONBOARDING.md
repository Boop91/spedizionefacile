# Kit Onboarding — Come capire SpediamoFacile da zero

Data: 2026-03-20  
Scopo: aiutare una persona nuova a capire struttura, significato e collegamenti del progetto senza perdersi nei dettagli

---

## 1. Se hai 30 minuti, leggi questo ordine

1. [Audit master](../architettura/AUDIT-MASTER.md)
2. [Moduli](../architettura/MODULI.md)
3. [Mappa flussi](../architettura/MAPPA-FLUSSI.md) — tenendo presente che alcune parti sono da riallineare
4. [Mappa dati](../architettura/MAPPA-DATI.md)
5. [Standard progetto](../architettura/STANDARD-PROGETTO.md)

Se hai ancora tempo, prosegui con:

6. [Come leggere il codice](COME-LEGGERE-IL-CODICE.md)
7. [Glossario dominio](../architettura/GLOSSARIO-DOMINIO.md)
8. [Roadmap refactor](../architettura/ROADMAP-REFACTOR.md)

---

## 2. Cosa fa il progetto, in una frase

SpediamoFacile e' una piattaforma che permette di:

- ottenere un preventivo di spedizione;
- configurare colli, servizi e indirizzi;
- aggiungere la spedizione a carrello o procedere a checkout;
- pagare;
- generare la spedizione reale con BRT;
- tracciare e gestire l'ordine;
- amministrare contenuti, immagini hero, prezzi e impostazioni dal pannello admin.

---

## 3. Dove vive ogni parte importante

## Frontend

Cartella base: `nuxt-spedizionefacile-master/`

- `components/` = pezzi di interfaccia riusabili
- `pages/` = pagine vere e proprie
- `composables/` = logica riusabile lato frontend
- `stores/` = stato condiviso (Pinia)
- `middleware/` = regole di accesso/navigazione
- `tests/` = test frontend

## Backend

Cartella base: `laravel-spedizionefacile-main/`

- `app/Http/Controllers/` = endpoint e orchestration HTTP
- `app/Services/` = logica di dominio e integrazioni
- `app/Models/` = modelli Eloquent
- `app/Http/Resources/` = shape delle risposte JSON
- `app/Http/Requests/` = validazione request
- `app/Listeners/` e `app/Events/` = catene post-pagamento e altri eventi
- `tests/` = test backend
- `routes/api.php` = mappa endpoint API

---

## 4. Il flusso principale da capire per primo

### Flusso 1 — Preventivo

1. la homepage mostra hero + preventivo rapido;
2. l'utente inserisce tratta e colli;
3. il frontend mostra un totale preview;
4. il backend salva i dati in sessione (`/api/session/first-step`).

### Flusso 2 — Wizard spedizione

1. l'utente entra in `/la-tua-spedizione/[step]`;
2. completa colli, servizi, indirizzi e PUDO;
3. arriva al riepilogo e poi al carrello/checkout.

### Flusso 3 — Pagamento e BRT

1. il checkout crea l'ordine;
2. il pagamento crea una transazione;
3. l'evento `OrderPaid` attiva la catena post-pagamento;
4. BRT genera etichetta/tracking;
5. l'ordine entra nel flusso operativo vero.

Se capisci questi tre flussi, hai capito l'80% del progetto.

---

## 5. Da dove parte un dato e dove arriva

### Prezzo

- parte dai bands e dalle regole di pricing;
- viene usato nel preventivo;
- attraversa sessione/store/riepilogo;
- rientra in ordine e checkout;
- deve restare coerente fino al pagamento.

### Pacco/Collo

- nasce nel preventivo;
- viene arricchito nello wizard;
- entra in carrello e ordine;
- viene letto dal backend per BRT.

### Indirizzi

- possono arrivare da compilazione diretta, rubrica o PUDO;
- vengono normalizzati per il backend e per BRT;
- finiscono su `PackageAddress`, riepilogo e ordini.

### PUDO

- nasce come ricerca geografica in UI;
- passa nella selezione del punto;
- entra in `service_data` o dati ordine;
- viene usato dal backend per BRT e tracking.

---

## 6. I 10 file da leggere per capire davvero il progetto

1. `nuxt-spedizionefacile-master/components/Preventivo.vue`
2. `nuxt-spedizionefacile-master/pages/la-tua-spedizione/[step].vue`
3. `nuxt-spedizionefacile-master/pages/riepilogo.vue`
4. `nuxt-spedizionefacile-master/pages/checkout.vue`
5. `nuxt-spedizionefacile-master/composables/usePriceBands.js`
6. `laravel-spedizionefacile-main/routes/api.php`
7. `laravel-spedizionefacile-main/app/Http/Controllers/SessionController.php`
8. `laravel-spedizionefacile-main/app/Http/Controllers/StripeController.php`
9. `laravel-spedizionefacile-main/app/Services/PriceEngineService.php`
10. `laravel-spedizionefacile-main/app/Services/BrtService.php`

Questi file non sono necessariamente i migliori dal punto di vista della pulizia, ma sono i piu' centrali per capire il sistema.

---

## 7. Come leggere il progetto senza confondersi

### Regola pratica 1
Non partire dal file piu' grande. Parti dal flusso.

### Regola pratica 2
Quando trovi un dato importante, chiediti sempre:

- chi lo crea?
- chi lo trasforma?
- chi lo salva?
- chi lo mostra?

### Regola pratica 3
Se trovi lo stesso concetto in 3 punti, fermati: potrebbe essere una duplicazione vera, non una coincidenza.

### Regola pratica 4
Per soldi, peso e volume, verifica sempre l'unita' prima di capire il valore.

---

## 8. Rischi attuali da sapere subito

Se entri oggi sul progetto, i rischi piu' importanti da non sottovalutare sono:

1. il pricing non e' ancora perfettamente unificato ovunque;
2. alcuni file core sono troppo densi;
3. store, sessione e stato pagina convivono in aree delicate;
4. parte della documentazione e' utile ma non sempre aggiornata al 100%.

Questo non significa che il progetto sia ingestibile. Significa che bisogna leggerlo con metodo e non fare modifiche "a intuito".

---

## 9. Prima di cambiare qualcosa

Checklist minima:

1. leggi il file `LEGGERE-QUI.md` della cartella dove stai entrando, se esiste;
2. apri il documento architetturale relativo;
3. individua la sorgente di verita' del dato che stai toccando;
4. cerca se esiste gia' una utility o un service simile;
5. controlla se c'e' un test di caratterizzazione gia' presente;
6. aggiorna la documentazione se cambi un flusso o un significato.

---

## 10. Percorso consigliato per diventare autonomo

### Giorno 1
- capire i moduli
- capire il flusso preventivo -> checkout -> BRT
- leggere standard progetto

### Giorno 2
- leggere i file core del pricing
- capire store/sessione/order
- eseguire o leggere i test di caratterizzazione

### Giorno 3
- entrare in un modulo specifico (wizard, BRT, admin, checkout)
- seguire un dato dall'input utente fino al database o alla risposta finale

Obiettivo realistico:

- dopo 1 giorno sai orientarti;
- dopo 3 giorni puoi fare modifiche piccole con sicurezza;
- dopo 1 settimana puoi partecipare al refactor con criterio.

