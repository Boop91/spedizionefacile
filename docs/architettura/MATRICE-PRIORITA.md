# Matrice di Priorita' — Audit e Refactor

Data: 2026-03-20  
Scopo: ordinare i problemi per rischio, costo, beneficio e dipendenze

---

## Scala usata

- **Rischio**: impatto se resta com'e'
- **Costo**: sforzo stimato del refactor
- **Beneficio**: miglioramento atteso su stabilita' + leggibilita'
- **Ordine consigliato**: sequenza di attacco raccomandata

Valori: `Basso`, `Medio`, `Alto`, `Molto alto`

---

| # | Problema | Categoria | Rischio | Costo | Beneficio | Ordine consigliato | Dipende da |
|---|----------|-----------|---------|-------|-----------|--------------------|------------|
| 1 | Pricing duplicato e divergente tra backend/frontend | stabilita' + business | Molto alto | Alto | Molto alto | 1 | test baseline |
| 2 | Nomi denaro con unita' ambigue (`single_price`, `weight_price`, `volume_price`) | comprensibilita' + bug rischio | Alto | Medio | Alto | 2 | pricing unico |
| 3 | `Preventivo.vue` troppo denso | leggibilita' + regressione UI | Alto | Alto | Alto | 3 | baseline tests |
| 4 | `[step].vue` troppo denso | leggibilita' + stabilita' wizard | Alto | Alto | Molto alto | 4 | contratti dati piu' chiari |
| 5 | `BrtService.php` monolitico | stabilita' + integrazione esterna | Alto | Alto | Molto alto | 5 | baseline BRT/PUDO |
| 6 | `StripeController.php`, `CartController.php`, `OrderController.php` multi-responsabilita' | stabilita' checkout | Alto | Alto | Alto | 6 | pricing + contratti dati |
| 7 | Documentazione flussi/dati fuori sync col codice | onboarding + drift | Alto | Medio | Alto | 0 e poi 7 | audit master |
| 8 | Stato frontend distribuito tra store/sessione/page state senza ownership esplicita | stabilita' + bug UX | Alto | Alto | Molto alto | 3 | pricing + contratti |
| 9 | Utility duplicate (`formatPrice`, mapping colli, `serviceData -> service_data`) | manutenibilita' | Medio | Basso | Alto | 8 | standard progetto |
| 10 | Pattern wallet movement ripetuto | backend consistency | Medio | Medio | Medio | 9 | controller split |
| 11 | Pattern HTTP BRT ripetuto | integrazione esterna | Medio | Basso | Medio | 10 | BrtService split |
| 12 | Preview admin e runtime hero non sempre speculari | UI stability | Medio | Medio | Alto | 11 | standard preview parity |
| 13 | Guest/auth con logiche parallele invece che adapter chiari | chiarezza flusso | Medio | Medio | Medio | 12 | contratti condivisi |
| 14 | Commenti troppo centrati sul "cosa" | stile leggibilita' | Basso | Basso | Medio | 13 | standard progetto |
| 15 | Naming file incoerente (`UseAdminImage.js`) | pulizia | Basso | Basso | Basso | 14 | nessuna |

---

## Lettura pratica della matrice

### Da fare subito

1. allineare il pricing a una sola sorgente di verita';
2. introdurre chiarezza su nomi/unita';
3. preparare test di caratterizzazione e segnare i documenti in drift.

### Da fare appena il pricing e' stabile

1. spezzare i file piu' grandi (`Preventivo.vue`, `[step].vue`, `BrtService.php`);
2. ridurre i controller che contengono troppe responsabilita';
3. rendere esplicita l'ownership dello stato frontend.

### Da fare in coda, ma prima della chiusura del programma

1. centralizzare utility duplicate;
2. rifinire commenti e naming minori;
3. riallineare tutta la documentazione ai flussi finali.

---

## Regola di priorita' operativa

Se un'attivita' migliora insieme:

- stabilita' del prezzo,
- chiarezza del flusso ordine,
- sorgente di verita',

ha priorita' maggiore rispetto a un refactor puramente estetico del codice.

Se invece un'attivita' e' solo cosmetica e non riduce rischio o ambiguita', va rimandata.

