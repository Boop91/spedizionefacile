# Homepage Hero “Editorial Overlap” Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendere l’hero della homepage visivamente “da top designer” con un layout a strati (overlap controllato tra testo e immagine), mantenendo i colori brand (#095866 / #E44203), senza rompere il sistema attuale di hero image configurabile e il badge prezzo.

**Architecture:** Manteniamo l’attuale sorgente dati (hero image configurabile con viewport config + pricing badge) e interveniamo solo sulla **composizione UI** nel blocco homepage: aggiungiamo un “bridge layer” (shape) e un contenitore immagine mascherato con overlay; riallineiamo baseline e spaziature. Nessuna nuova dipendenza.

**Tech Stack:** Nuxt 3 (Vue 3), Tailwind CSS (utility classes + breakpoint custom `tablet/desktop/desktop-xl`), composables `usePriceBands()` e config hero via `/api/public/homepage-image`.

---

## File map (cosa tocchiamo e perché)

**Modify**
- `nuxt-spedizionefacile-master/components/ContenutoHeader.vue`
  - Sezione homepage hero: struttura e classi Tailwind.
  - Aggiunta di 1–2 wrapper div per layering.
  - Piccole regole CSS scoped (solo se indispensabili per mask/clip/noise).

**No changes (dipendenze da rispettare)**
- Caricamento immagine hero (`heroImageUrl`, `heroImageStyle`) e preview route `/preview/home-hero`.
- Logica `usePriceBands()` per il badge prezzo.

---

## Success criteria (definizione di “eccellente”)

- Desktop (≥1024px): titolo e immagine percepiti come **un’unica composizione**, con overlap elegante e allineamento visivo coerente.
- Mobile (<1024px): nessun elemento “incastrato male”; overlap ridotto ma presente (micro-overlap), gerarchia chiara.
- Nessuna regressione su route non-home (`/servizi`, `/contatti`, ecc.).
- Nessun `<Icon>` inserito (regola progetto).
- Lint e build Nuxt senza errori.

---

## Chunk 0: Safety / contesto (prima di toccare UI)

### Task 0: Isolare il lavoro e capire i vincoli reali

**Files:**
- Read: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue`
- Read (se presente): `nuxt-spedizionefacile-master/tailwind.config.*`

- [ ] **Step 1: Creare un branch di lavoro**

Run:
```bash
git checkout -b feat/ui-hero-editorial-overlap
```
Expected: branch creato.

- [ ] **Step 2: Verificare che le modifiche restano SOLO nel blocco homepage**
  - Confermare che il markup che tocchiamo è dentro `v-if="isHomepageHeroRoute"`.

- [ ] **Step 3: (Solo lettura) confermare supporto ad arbitrary values Tailwind**
  - Se il progetto usa già classi tipo `w-[140px]`, allora gli arbitrary values sono ok.
  - Se ci sono limitazioni, preferire classi standard o CSS scoped minimale.

---

## Chunk 1: Baseline & snapshot di riferimento

### Task 1: Snapshot e guardrail (prima di cambiare struttura)

**Files:**
- Modify: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue:295-362` (circa)

- [ ] **Step 1: Mappare i blocchi chiave (NOTE, nessuna modifica visiva)**
  - Elencare (in note esterne o commenti temporanei da rimuovere prima del commit):
    - wrapper hero
    - colonna sinistra (h1 + price label + promo)
    - colonna destra (container immagine)
    - decorazione teal esistente

- [ ] **Step 2: Eseguire grep obbligatori (vincoli progetto)**

Run:
```bash
grep -rn "<Icon" nuxt-spedizionefacile-master/pages/
grep -rn "<Icon" nuxt-spedizionefacile-master/components/
grep -rn "import.*from.*@iconify" nuxt-spedizionefacile-master/
grep -rn "import.*Icon" nuxt-spedizionefacile-master/
```
Expected: **0 occorrenze** di `<Icon>` e **0 import** iconify/Icon. Se esistono già, fermarsi e concordare scope.

---

## Chunk 2: Design implementation (Editorial Overlap)

### Task 2: Creare la composizione a strati (layering) con “bridge shape”

**Files:**
- Modify: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue` (blocchi homepage hero)

- [ ] **Step 1: Inserire wrapper di composizione (nessun cambiamento visivo)**
  - Aggiungere i wrapper `relative`/`absolute` ma con bridge/overlay “spenti” (es. `opacity-0`) per verificare che non cambia nulla.

- [ ] **Step 2: Attivare stacking/z-index espliciti**
  - Impostare chiaramente 3 layer:
    - bridge/backdrop: `z-[1]`
    - immagine: `z-[2]`
    - contenuto (titolo + label): `z-[5]`

- [ ] **Step 3: Accendere il bridge su desktop (prima desktop-only)**
  - Aggiungere bridge shape con:
    - `pointer-events-none`
    - gradiente teal → trasparente
    - target: “entra” 24–32px nella zona immagine su desktop.

- [ ] **Step 4: Estendere bridge a tablet/mobile (micro-overlap)**
  - Ridurre opacità e ampiezza su mobile.
  - Verificare che non peggiora la leggibilità.

- [ ] **Step 5: Commit**

```bash
git add nuxt-spedizionefacile-master/components/ContenutoHeader.vue
git commit -m "feat(ui): hero homepage editorial overlap layering"
```

---

### Task 3: Maschera immagine + overlay brand (senza nuove dipendenze)

**Files:**
- Modify: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue` (container immagine)

- [ ] **Step 1: Rendere il container immagine “designer”**
  - Sostituire il semplice box con uno shape più riconoscibile:
    - `rounded-[24px]` su desktop, `rounded-[18px]` su mobile
    - `border` più sottile/soft
    - shadow più “cinematica” (più blur, meno nero)

- [ ] **Step 2: Aggiungere overlay brand per fondere immagine e palette**
  - Dentro al container immagine, aggiungere un `div` assoluto sopra l’img:
    - `pointer-events-none` (fondamentale)
    - `bg-gradient-to-tr from-[#095866]/25 via-transparent to-[#E44203]/10` (valori da calibrare)
    - opacità diversa per mobile/desktop
    - Verificare che l’overlay NON copra/abbassi il contrasto del testo (testo sempre su layer separato).

- [ ] **Step 3: (Opzionale, se necessario) Clip/mask via CSS**
  - Se Tailwind non basta, usare `style scoped` con `clip-path` semplice (es. poligono leggero o inset con corner accent).
  - Vincolo: fallback ok su browser moderni (Chrome/Safari). Se clip-path crea problemi, rimuoverla e tenere solo rounded.

- [ ] **Step 4: Commit**

```bash
git add nuxt-spedizionefacile-master/components/ContenutoHeader.vue
git commit -m "feat(ui): hero image mask and brand overlay"
```

---

### Task 4: Gerarchia tipografica + price badge “integrato”

**Files:**
- Modify: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue` (h1 + price card)

- [ ] **Step 1: Correggere la baseline visiva tra testo e immagine**
  - Obiettivo: eliminare la percezione “immagine più su”.
  - Strategia: allineare i due blocchi tramite `desktop:mt-[...]` sulla colonna destra (o sinistra) in modo che:
    - top immagine ≈ top del blocco titolo (o leggermente sotto, 4–8px)

- [ ] **Step 2: Trasformare la card prezzo in label/placchetta incastrata**
  - Senza cambiare contenuto, cambiare proporzioni per farla sembrare “designer label”:
    - ridurre leggermente la larghezza su desktop se troppo invasiva, oppure agganciarla con `desktop:-ml-[...]` / `desktop:translate-x-[...]`
    - aggiungere un micro “notch” (solo CSS) oppure un bordo interno (`ring-1 ring-white/20`) per qualità.

- [ ] **Step 3: Microtipografia**
  - `a partire da`: tracking più raffinato su desktop.
  - `IVA e ritiro incluso`: aumentare leggibilità su mobile (font-size/line-height) senza occupare più altezza.

- [ ] **Step 4: Commit**

```bash
git add nuxt-spedizionefacile-master/components/ContenutoHeader.vue
git commit -m "feat(ui): hero typography and integrated price label"
```

---

## Chunk 3: Responsive calibration (mobile-first)

### Task 5: Rifinire mobile (densità, overlap ridotto, leggibilità)

**Files:**
- Modify: `nuxt-spedizionefacile-master/components/ContenutoHeader.vue`

- [ ] **Step 1: Ridurre densità senza perdere struttura**
  - aumentare di poco `gap-x` su mobile.
  - assicurare che il titolo abbia line-height coerente e non “schiacciato”.

- [ ] **Step 2: Overlap mobile “micro”**
  - il bridge shape rimane ma più leggero.
  - la label prezzo non deve coprire l’immagine.

- [ ] **Step 3: Commit**

```bash
git add nuxt-spedizionefacile-master/components/ContenutoHeader.vue
git commit -m "feat(ui): hero mobile spacing and overlap tuning"
```

---

## Chunk 4: Verification (OBBLIGATORIA prima di dire “fatto”)

### Task 6: Verifiche progetto (Icon/import/lint/build)

- [ ] **Step 1: Verifica Icon (obbligatoria)**

Run:
```bash
grep -rn "<Icon" nuxt-spedizionefacile-master/pages/
grep -rn "<Icon" nuxt-spedizionefacile-master/components/
```
Expected: nessuna occorrenza nuova.

- [ ] **Step 2: Verifica import mancanti (obbligatoria)**

Run:
```bash
grep -rn "import.*from.*@iconify" nuxt-spedizionefacile-master/
grep -rn "import.*Icon" nuxt-spedizionefacile-master/
```
Expected: **0 import** (non solo “nessun nuovo import”). Se esistono già, fermarsi e concordare scope.

- [ ] **Step 3: Lint Vue (obbligatoria)**

Run:
```bash
cd nuxt-spedizionefacile-master
npm run lint 2>&1 | head -50
```
Expected: 0 errori (o comunque nessun errore legato a `ContenutoHeader.vue`).

- [ ] **Step 4: Build (se possibile)**

Run:
```bash
cd nuxt-spedizionefacile-master
npm run build
```
Expected: comando termina con exit code 0 (nessun errore).

Se fallisce: catturare un estratto utile del log.
```bash
cd nuxt-spedizionefacile-master
npm run build 2>&1 | head -120
```

- [ ] **Step 5: Smoke test visivo**
  - Aprire homepage su desktop e mobile.
  - Verificare che il bridge shape e l’overlay non rendano il testo illeggibile.
  - Verificare route non-home (servizi/contatti) non alterate.

- [ ] **Step 6: Commit finale (se ci sono fix da verification)**

```bash
git add -A
git commit -m "fix(ui): hero overlap verification adjustments"
```

---

## Note operative
- Non introdurre nuove librerie.
- Non cambiare API o composables.
- Tutto deve stare in `ContenutoHeader.vue` e rimanere compatibile col sistema `heroImageStyle` (zoom/x/y/mode).
