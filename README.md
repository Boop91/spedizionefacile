# SpediamoFacile Monorepo

Questo repository contiene:

- **Backend Laravel** in `laravel-spedizionefacile-main`
- **Frontend Nuxt** in `nuxt-spedizionefacile-master`

## Struttura canonica della repo

Per tenere la repo leggibile anche quando cresce, il root deve restare minimale.

- `nuxt-spedizionefacile-master/` -> frontend attivo
- `laravel-spedizionefacile-main/` -> backend attivo
- `docs/` -> documentazione viva, con indice canonico in `docs/INDICE.md`
- `docs/_archivio/` -> documenti storici non piu' canonici
- `docs/riferimento/operativo/` -> manuali tecnici e procedure operative non di runtime
- `docs/riferimento/vendor/` -> reference esterne o documentazione vendor
- `reports/` -> report storici e diagnostica non canonica
- `reports/runtime/` -> dump locali, output diagnostici e file di supporto non canonici
- `output/` -> screenshot, snapshot e artefatti locali
- `scripts/pudo/` -> helper manuali PUDO e fallback locale
- `_backup/` -> backup espliciti
- `_LOG/` -> log operativi locali correnti
- `reports/runtime/legacy-logs/` -> storico di log e diagnostica locale non piu' attiva
- `reports/runtime/diagnostica/` -> report diagnostici correnti generati dagli script
- `.devcontainer/` -> configurazione ambiente di sviluppo
- `_SQUADRA_DIARIO.md` -> diario obbligatorio dei turni

Cartelle locali di tooling come `.claude/` e `.codex/` possono esistere nel workspace, ma non sono sorgente di verita' del prodotto e non devono contenere materiale canonico del progetto. In particolare:

- `.claude/worktrees/` puo' contenere **al massimo una worktree attiva** se serve per un lavoro locale non ancora consolidato;
- worktree duplicate o stale vanno archiviate o rimosse;
- log tecnici di tooling non devono tornare nel root: vanno sotto `reports/runtime/legacy-logs/`.

Se un file non serve al runtime, all'ingresso del progetto o alla documentazione viva, non deve restare sciolto nel root.

## Ingressi canonici

Per non perdersi tra launcher e script, questi sono gli ingressi da usare davvero:

- `AVVIA_LOCALE.bat` -> avvio locale rapido su Windows
- `PANNELLO.bat` -> pannello operativo Windows
- `CONDIVIDI_ONLINE.bat` -> avvio con link pubblico Cloudflare su Windows
- `bash scripts/avvia-tutto.sh` -> avvio locale da shell
- `bash scripts/verifica-baseline.sh` -> verifica tecnica canonica

Note utili:

- `AVVIA_TUTTO.bat` non ha logica propria: e' solo un alias di `AVVIA_LOCALE.bat`
- `APRI_LOG.bat` e `pannello.sh` restano solo come scorciatoie locali di supporto, non come percorso principale
- i launcher root `.bat` servono come ingressi rapidi
- la logica vera deve vivere negli script sotto `scripts/`

## Documentazione da leggere davvero

Per non disperdersi in decine di file, la documentazione canonica parte da:

1. `docs/INDICE.md`
2. `docs/impara/KIT-ONBOARDING.md`
3. `docs/architettura/AUDIT-MASTER.md`
4. `docs/architettura/STANDARD-PROGETTO.md`

Il resto di `docs/` va considerato materiale secondario o di approfondimento.

## Avvio con GitHub Codespaces (solo UI, senza terminale)

1. **Crea un Codespace**  
   - Vai su GitHub → *Code* → *Codespaces* → *Create codespace on main*.

2. **Attendi la configurazione automatica**  
   - Lo script `scripts/avvia-tutto.sh` installa le dipendenze mancanti e avvia Laravel (8000) e Nuxt (3001).
   - Se Node di sistema e' troppo vecchio, lo script usa automaticamente `node@20` via `npx`.

3. **Apri il sito**  
   - Apri il link della porta **3001** dalla scheda *Ports* del Codespace.

### Se vedi errore 502 sulla porta 3001

- Aspetta 20-40 secondi e ricarica la pagina: Nuxt può impiegare qualche secondo al primo avvio.
- Se resta 502, dal Codespace usa **Command Palette → Codespaces: Rebuild Container** e riapri la porta **3001**.
- Il backend API deve rispondere sulla porta **8000**; se 8000 è su e 3001 no, il problema è solo nel processo Nuxt e il rebuild lo riallinea.

4. **Backend collegato automaticamente**  
   - `NUXT_PUBLIC_API_BASE` viene costruita usando `CODESPACE_NAME` e `GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN`, così il frontend usa l’URL pubblico della porta 8000.

## API Portafoglio (backend)

Il backend espone gli endpoint seguenti (base URL = `NUXT_PUBLIC_API_BASE`):

- `GET /api/wallet/balance` → saldo calcolato dai movimenti confermati.
- `GET /api/wallet/movements` → lista movimenti.
- `POST /api/wallet/top-up` → ricarica (idempotente).
- `POST /api/wallet/payment` → pagamento (idempotente, crea movimento in stato `pending`).
- `POST /api/wallet/payment-confirmation` → conferma pagamento tramite riferimento.

La logica è idempotente: lo stesso `idempotency_key` non crea movimenti duplicati e il saldo deriva sempre dai movimenti confermati.

## Soluzione definitiva con Cloudflare Tunnel (gratis)

Se Codespaces termina i minuti o non vuoi usare Netlify/Render, puoi pubblicare **frontend e backend** con Cloudflare Tunnel.

### Cosa fa questa soluzione

- Usa `CONDIVIDI_ONLINE.bat` come ingresso rapido Windows.
- In PowerShell, la logica equivalente e' in `scripts/avvia-cloudflare.ps1`.
- Crea due URL pubblici `trycloudflare.com`:
  - uno per il backend API
  - uno per il frontend sito
- Imposta automaticamente `NUXT_PUBLIC_API_BASE` sul tunnel backend, così registrazione/login/form e chiamate API puntano all’URL giusto.

### Passi rapidi (Codespaces)

1. Apri Codespace sul branch aggiornato.
2. Esegui script unico:
   - `CONDIVIDI_ONLINE.bat`
   - oppure, se sei gia' in PowerShell:
   - `powershell -ExecutionPolicy Bypass -File .\scripts\avvia-cloudflare.ps1`
3. Copia il link mostrato come **Frontend pubblico** e aprilo.

### Note importanti

- Gli URL `trycloudflare.com` sono comodi e gratuiti, ma possono cambiare al riavvio.
- Se vuoi URL stabili “per sempre”, crea un tunnel Cloudflare dal dashboard Zero Trust e associa due hostname (es. `app.tuodominio.it` e `api.tuodominio.it`) verso le porte 3001/8000.
- Non inserire mai token o credenziali nel repository: usa variabili ambiente nel provider/ambiente di esecuzione.


### Diagnostica automatica (quando non si connette)

Se usi Cloudflare Tunnel, `http://127.0.0.1:8787` può essere la porta metrics di `cloudflared`; se invece usi Caddy locale, `8787` è il sito principale.

Esegui questo comando unico per raccogliere tutto lo stato in automatico:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\raccogli-stato.ps1
```

Il comando crea `reports/runtime/diagnostica/report.txt`: incolla quel file e possiamo capire subito dove si blocca (frontend, backend o tunnel) senza altri passaggi manuali.


## Avvio locale consigliato (Caddy su 8787)

Se stai già vedendo il sito su `http://127.0.0.1:8787`, questa è la modalità corretta: origine unica per frontend + API.

1. Avvio automatico completo:

```bash
AVVIA_LOCALE.bat
```

Oppure, se sei gia' in PowerShell:

```bash
powershell -ExecutionPolicy Bypass -File .\scripts\avvia-locale.ps1
```

2. Apri il sito:

- `http://127.0.0.1:8787`

Lo script avvia Nuxt (3001), Laravel (8000) e Caddy (8787) se disponibile.

Se Caddy non e' disponibile, lo stesso avvio locale usa il fallback corretto:

- frontend su `http://127.0.0.1:3001`
- backend/API su `http://127.0.0.1:8000`

In quel caso il frontend deve parlare direttamente con Laravel su `:8000`, non con `:3001`.

## Verifica baseline tecnica

Requisiti minimi:

- Node `>=20`
- PHP `>=8.2`
- Composer funzionante

Comando canonico di verifica:

```bash
bash scripts/verifica-baseline.sh
```

Lo script:

- risolve automaticamente `php` e `composer` anche in setup misti WSL/Windows;
- usa `node@20` via `npx` se il Node locale e' troppo vecchio;
- esegue `nuxt build`;
- esegue i test backend minimi `AuthAndAdminAccountsTest` e `CartFlowTest`.

### Bundle automatico di supporto (Windows)

Per condividere tutto in un colpo solo (config + log + check HTTP), esegui in PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\support-bundle.ps1
```

Output atteso: `OK: creato ...support_bundle_*.zip`

> Nota sicurezza: il bundle copia solo file di configurazione di esempio (`.env.example`), non i tuoi `.env` reali.

## Account di prova e controllo amministrativo

Dopo `php artisan migrate --seed` trovi questi account pronti:

- **Admin**: `admin@spediamofacile.it` / `Admin2026!`
- **Cliente**: `cliente@spediamofacile.it` / `Cliente2026!`
- **Cliente test**: `prova@spediamofacile.it` / `Prova2026!`
- **Partner Pro**: `pro@spediamofacile.it` / `Partner2026!`

### Dove controllare tutti gli account registrati

1. Accedi con l'utente admin.
2. Apri **Il tuo account → Amministrazione**.
3. Vai al tab **Account** (`/account/amministrazione`):
   - vedi elenco completo utenti registrati,
   - approvi account non verificati,
   - elimini account in caso di bug/registrazioni errate.

> Nota: l'eliminazione dell'utente amministratore attualmente loggato è bloccata per sicurezza.


## Nota PR refresh regressioni UI/Preventivo

È disponibile una PR di refresh dedicata alle regressioni segnalate su:
- layout preventivo/carrello compresso,
- immagine hero home non visibile,
- stato sessione/navbar al ritorno in home,
- blocco del bottone **Continua** nello step spedizione,
- wrapping dell’icona cestino nello step 1.

Se nel repository vedi più PR aperte, usa quella con titolo che inizia con **"PR refresh"**.


## Ripristino rapido errore Vue "Element is missing end tag"

Se in locale compare un errore 500 con `Element is missing end tag` su `pages/la-tua-spedizione/[step].vue`, allinea il file al branch corrente prima di riavviare:

```bash
git checkout -- nuxt-spedizionefacile-master/pages/la-tua-spedizione/[step].vue
cd nuxt-spedizionefacile-master
npm run build
```

Questo evita che una modifica locale non chiusa correttamente blocchi tutta l'app.

Se l'errore resta:

1. verifica prima `git status --short` per capire se hai modifiche locali importanti;
2. confronta il file con il branch corrente usando `git diff`;
3. se serve davvero riallineare il singolo file, chiedi una revisione esplicita prima di usare comandi distruttivi.

Evita `git reset --hard` come primo tentativo: puo' cancellare lavoro locale non ancora salvato o non ancora capito.

In alternativa puoi usare lo script automatico:

```bash
bash scripts/ripristina-vue.sh
```
